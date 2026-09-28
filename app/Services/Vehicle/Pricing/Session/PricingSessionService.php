<?php

declare(strict_types=1);

namespace App\Services\Vehicle\Pricing\Session;

use App\Models\Vehicle\Pricing\ImportSession;
use App\Services\Vehicle\Pricing\PricingHoldService;
use App\Support\Result;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * The pricing process session (DEC-073) — the only writer of xlr8_vehicle_pricing_import_sessions.
 *
 *  gate()                    the one active session, or null (a new process may start only when null)
 *  start(...)                upload + selected price lists + WEF + optional holds → stage Detecting
 *  advance($s, Stage)        move forward (never back past the current stage, except the Vehicle Info loop)
 *  record($s, fn)            run work that writes pricing / vehicle rows — every change is logged for Discard
 *  markPublished($s)         first snapshot written: from now on the session can only be completed
 *  discard($s)               before publish only: undo exactly this session's changes, release the gate
 *  complete($s, $reopen)     after the summary: reopen chosen held lists, release the gate
 */
class PricingSessionService
{
    public function __construct(private readonly PricingChangeRecorder $recorder, private readonly PricingHoldService $holds) {}

    public function gate(): ?ImportSession
    {
        return ImportSession::query()->active()->latest('id')->first();
    }

    /**
     * @param  list<string>  $sheets  price-list sheet titles chosen by the user
     * @param  list<string>  $holdLists  lists to put on hold now (optional)
     */
    public function start(UploadedFile $file, array $sheets, string $wefDate, array $holdLists = [], ?int $userId = null): Result
    {
        if ($active = $this->gate()) {
            return Result::fail('ALREADY_ACTIVE', "Pricing process #{$active->id} is still open — resume or discard it first.", ['session_id' => $active->id]);
        }
        if ($sheets === []) {
            return Result::fail('NO_SHEETS', 'Choose at least one price list sheet.');
        }

        $session = DB::transaction(function () use ($file, $sheets, $wefDate, $holdLists, $userId) {
            $session = ImportSession::create([
                'status' => ImportSession::STATUS_ACTIVE,
                'current_stage' => PricingStage::Detecting->value,
                'selected_sheets' => $sheets,
                'wef_date' => $wefDate,
                'hold_lists' => $holdLists,
                'source_filename' => $file->getClientOriginalName(),
                'stats' => [],
                'progress' => [],
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
            $session->forceFill(['upload_path' => $this->storeUpload($session, $file, 'pricing')])->save();

            if ($holdLists !== []) {
                $this->record($session, fn () => $this->holds->hold($holdLists, $session, null, $userId));
            }

            return $session;
        });

        Log::info('[Pricing] session started', ['session_id' => $session->id, 'sheets' => $sheets, 'wef' => $wefDate, 'holds' => $holdLists]);

        return Result::ok(['session' => $session], "Pricing process #{$session->id} started.");
    }

    /** Store a workbook for this session (pricing, vehicle-info, addons, insurance, rto); returns the storage path. */
    public function storeUpload(ImportSession $session, UploadedFile $file, string $kind): string
    {
        return $file->storeAs("pricing/{$session->id}", $kind.'-'.now()->format('His').'.'.($file->getClientOriginalExtension() ?: 'xlsx'), 'local');
    }

    public function uploadAbsolutePath(ImportSession $session, ?string $path = null): string
    {
        return Storage::disk('local')->path((string) ($path ?? $session->upload_path));
    }

    /**
     * @template T
     *
     * @param  callable(): T  $work
     * @return T
     */
    public function record(ImportSession $session, callable $work)
    {
        return $this->recorder->within($session->id, $work);
    }

    /** @param  array<string, mixed>  $stats  merged into the session stats */
    public function advance(ImportSession $session, PricingStage $to, array $stats = [], ?int $userId = null): ImportSession
    {
        $from = $session->stage();
        if ($from->isTerminal()) {
            return $session;
        }
        // forward-only, except that later steps may send the user back to the Vehicle Info loop
        if ($to->order() < $from->order() && $to !== PricingStage::VehicleInfo) {
            $to = $from;
        }
        $session->forceFill([
            'current_stage' => $to->value,
            'stats' => array_replace_recursive($session->stats ?? [], $stats),
            'updated_by' => $userId ?? $session->updated_by,
        ])->save();

        return $session;
    }

    /** @param  array<string, mixed>  $progress  e.g. ['step' => 'detect', 'done' => 120, 'total' => 900, 'message' => …] */
    public function progress(ImportSession $session, array $progress): void
    {
        $session->forceFill(['progress' => array_merge($session->progress ?? [], $progress, ['at' => now()->toIso8601String()])])->saveQuietly();
    }

    public function markPublished(ImportSession $session): void
    {
        if ($session->published_at === null) {
            $session->forceFill(['published_at' => now()])->saveQuietly();
        }
    }

    public function discard(ImportSession $session, ?int $userId = null): Result
    {
        if ($session->isTerminal()) {
            return Result::fail('TERMINAL', 'This pricing process is already closed.');
        }
        if ($session->isPublished()) {
            return Result::fail('PUBLISHED', 'Prices from this process are already published — complete it instead of discarding.');
        }

        $undone = DB::transaction(function () use ($session, $userId) {
            $undone = $this->recorder->rollback($session->id);
            $session->forceFill([
                'status' => ImportSession::STATUS_CANCELLED,
                'current_stage' => PricingStage::Discarded->value,
                'cancelled_at' => now(),
                'cancelled_by' => $userId,
                'updated_by' => $userId,
            ])->save();

            return $undone;
        });
        Log::info('[Pricing] session discarded', ['session_id' => $session->id, 'changes_undone' => $undone]);

        return Result::ok(['undone' => $undone], "Pricing process #{$session->id} discarded — {$undone} change(s) undone.");
    }

    /** @param  list<string>  $reopenLists  held lists to reopen now */
    public function complete(ImportSession $session, array $reopenLists = [], ?int $userId = null): Result
    {
        if ($session->isTerminal()) {
            return Result::fail('TERMINAL', 'This pricing process is already closed.');
        }
        if ($session->stage() !== PricingStage::Summary) {
            return Result::fail('NOT_READY', 'Finish Calculate & Publish before completing the process.');
        }

        DB::transaction(function () use ($session, $reopenLists, $userId) {
            if ($reopenLists !== []) {
                $this->holds->reopen($reopenLists, "Pricing process #{$session->id} completed", $userId);
            }
            $session->forceFill([
                'status' => ImportSession::STATUS_COMPLETED,
                'current_stage' => PricingStage::Completed->value,
                'completed_at' => now(),
                'completed_by' => $userId,
                'updated_by' => $userId,
            ])->save();
        });

        return Result::ok([], "Pricing process #{$session->id} completed.");
    }
}
