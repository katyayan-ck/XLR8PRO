<?php

namespace App\Services\Vehicle\Pricing;

use App\Models\Vehicle\Pricing\ImportSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Vehicle\Pricing\Profile;
use RuntimeException;

class PricingSessionService
{
    public function activeSession(): ?ImportSession
    {
        return ImportSession::query()->active()->latest('id')->first();
    }

    /**
     * @param  list<string>  $selectedSheets
     */
        public function start(
        array $selectedSheets,
        ?string $wefDate = null,
        ?string $notes = null,
        ?int $userId = null
    ): ImportSession {
        $userId = $userId ?? Auth::id();

        if ($this->activeSession()) {
            throw new RuntimeException(
                'Another pricing process is already active. Resume or discard it before starting a new one.'
            );
        }

        $session = ImportSession::create([
            'status'          => ImportSession::STATUS_ACTIVE,
            'current_stage'   => ImportSession::STAGE_DETECTING,
            'selected_sheets' => array_values($selectedSheets),
            'wef_date'        => $wefDate,
            'hold_scopes'     => [],
            'stats'           => [
                'fresh_created'      => 0,
                'already_known'      => 0,
                'incomplete'         => 0,
                'prices_written'     => 0,
                'price_changes'      => 0,
                'calculated'         => 0,
                'skipped_incomplete' => 0,
            ],
            'notes'      => $notes,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        Log::info('[PricingSession] started', [
            'session_id' => $session->id,
            'sheets'     => $selectedSheets,
            'wef'        => $wefDate,
            'user_id'    => $userId,
        ]);

        return $session;
    }

    public function advance(ImportSession $session, string $stage, array $statsMerge = [], ?int $userId = null): ImportSession
    {
        $userId = $userId ?? Auth::id();
        $stats  = array_merge($session->stats ?? [], $statsMerge);

        $session->fill([
            'current_stage' => $stage,
            'stats'         => $stats,
            'updated_by'    => $userId,
        ]);

        if ($stage === ImportSession::STAGE_COMPLETED) {
            $session->status = ImportSession::STATUS_COMPLETED;
        }

        $session->save();

        Log::info('[PricingSession] advance', [
            'session_id' => $session->id,
            'stage'      => $stage,
            'stats'      => $stats,
        ]);

        return $session->fresh();
    }

    public function discard(ImportSession $session, ?int $userId = null): ImportSession
    {
        $userId = $userId ?? Auth::id();

        if ($session->isTerminal()) {
            throw new RuntimeException('Session is already completed or cancelled.');
        }

        DB::transaction(function () use ($session, $userId) {
            $sid = $session->id;

            $tables = [
                'xlr8_vehicle_pricing_change_flags',
                'xlr8_vehicle_pricing_draft',
                'xlr8_vehicle_pricing_affected',
                'xlr8_vehicle_pricing',
                'xlr8_vehicle_pricing_addons',
                'xlr8_vehicle_pricing_discounts',
                'xlr8_vehicle_pricing_dealer_charges',
            ];

            foreach ($tables as $table) {
                if (!DB::getSchemaBuilder()->hasTable($table)) {
                    continue;
                }
                if (!DB::getSchemaBuilder()->hasColumn($table, 'import_session_id')) {
                    continue;
                }

                $q = DB::table($table)->where('import_session_id', $sid);

                if (DB::getSchemaBuilder()->hasColumn($table, 'deleted_at')) {
                    $q->whereNull('deleted_at')->update([
                        'deleted_at' => now(),
                        'deleted_by' => $userId,
                        'updated_at' => now(),
                        'updated_by' => $userId,
                    ]);
                } elseif (DB::getSchemaBuilder()->hasColumn($table, 'is_active')) {
                    $q->update([
                        'is_active'  => 0,
                        'updated_at' => now(),
                        'updated_by' => $userId,
                    ]);
                } else {
                    $q->delete();
                }
            }

            // Incomplete profiles from this session
            Profile::query()
                ->where('import_session_id', $sid)
                ->where(function ($q) {
                    $q->where('is_vehicle_master_complete', false)
                      ->orWhereNull('is_vehicle_master_complete');
                })
                ->update([
                    'deleted_at' => now(),
                    'deleted_by' => $userId,
                    'updated_at' => now(),
                    'updated_by' => $userId,
                ]);

            $session->fill([
                'status'        => ImportSession::STATUS_CANCELLED,
                'current_stage' => ImportSession::STAGE_CANCELLED,
                'cancelled_at'  => now(),
                'cancelled_by'  => $userId,
                'updated_by'    => $userId,
            ]);
            $session->save();
        });

        Log::info('[PricingSession] discarded', [
            'session_id' => $session->id,
            'user_id'    => $userId,
        ]);

        return $session->fresh();
    }

    public function updateStats(ImportSession $session, array $statsMerge, ?int $userId = null): ImportSession
    {
        $userId = $userId ?? Auth::id();
        $session->stats = array_merge($session->stats ?? [], $statsMerge);
        $session->updated_by = $userId;
        $session->save();

        return $session->fresh();
    }

    public function setHoldScopes(ImportSession $session, array $scopes, ?int $userId = null): ImportSession
    {
        $userId = $userId ?? Auth::id();
        $session->hold_scopes = array_values($scopes);
        $session->updated_by = $userId;
        $session->save();

        return $session->fresh();
    }
}
