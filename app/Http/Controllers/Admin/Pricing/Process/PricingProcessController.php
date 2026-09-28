<?php

namespace App\Http\Controllers\Admin\Pricing\Process;

use App\Http\Controllers\Controller;
use App\Jobs\Vehicle\Pricing\Process\DetectPriceListsJob;
use App\Models\Vehicle\Pricing\ImportSession;
use App\Services\Vehicle\Pricing\Import\PriceListDetectService;
use App\Services\Vehicle\Pricing\Import\PricingWorkbookReader;
use App\Services\Vehicle\Pricing\PricingHoldService;
use App\Services\Vehicle\Pricing\Session\PricingSessionService;
use App\Services\Vehicle\Pricing\Session\PricingStage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Pricing process — steps 0–2 (DEC-073): the one-process gate (resume / discard), Start (upload, price lists, WEF,
 * optional holds) and the Detect progress. PRC_WKFL_VIEW sees the process; PRC_WKFL_MANAGE runs it.
 */
class PricingProcessController extends Controller
{
    public const LISTS = ['PV', 'CV', 'BEV', 'LMM', 'LMM_TZU', 'CSD'];

    public function __construct(
        private readonly PricingSessionService $sessions,
        private readonly PricingHoldService $holds,
        private readonly PricingWorkbookReader $reader,
    ) {}

    public function index(): View
    {
        if (! backpack_user()->can('PRC_WKFL_VIEW')) {
            abort(403, 'You do not have permission to view the pricing process.');
        }
        $session = $this->sessions->gate();

        return view('admin.pricing.process.index', [
            'title' => 'Pricing Process',
            'session' => $session,
            'stage' => $session?->stage(),
            'steps' => PricingStage::steps(),
            'heldLists' => $this->holds->heldLists(),
            'canManage' => backpack_user()->can('PRC_WKFL_MANAGE'),
            'recent' => ImportSession::query()->whereIn('status', [ImportSession::STATUS_COMPLETED, ImportSession::STATUS_CANCELLED])
                ->latest('id')->limit(5)->get(['id', 'status', 'current_stage', 'wef_date', 'source_filename', 'created_at', 'completed_at', 'cancelled_at']),
        ]);
    }

    public function startForm(): View|RedirectResponse
    {
        if (! backpack_user()->can('PRC_WKFL_MANAGE')) {
            abort(403, 'You do not have permission to run the pricing process.');
        }
        if ($active = $this->sessions->gate()) {
            return redirect()->route('pricing.workflow.index')
                ->with('warning', "Pricing process #{$active->id} is still open — resume or discard it first.");
        }

        return view('admin.pricing.process.start', [
            'title' => 'Start Pricing Process',
            'lists' => self::LISTS,
            'holdLists' => PricingHoldService::LISTS,
            'heldLists' => $this->holds->heldLists(),
            'legacyCodes' => app(PriceListDetectService::class)->legacyCodeCount(),
        ]);
    }

    public function start(Request $request): RedirectResponse
    {
        if (! backpack_user()->can('PRC_WKFL_MANAGE')) {
            abort(403, 'You do not have permission to run the pricing process.');
        }
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx', 'max:20480'],
            'lists' => ['required', 'array', 'min:1'],
            'lists.*' => ['string', Rule::in(self::LISTS)],
            'wef_date' => ['required', 'date'],
            'hold_lists' => ['nullable', 'array'],
            'hold_lists.*' => ['string', Rule::in(array_keys(PricingHoldService::LISTS))],
        ], [], __('pricing.fields'));

        $file = $request->file('file');
        try {
            $titles = $this->reader->sheetNames($file->getRealPath());
        } catch (\Throwable) {
            throw ValidationException::withMessages(['file' => 'The workbook could not be read — upload a valid .xlsx file.']);
        }
        $match = PriceListDetectService::matchSheets($titles, $data['lists']);
        if ($match['missing'] !== []) {
            throw ValidationException::withMessages(['lists' => 'Not in this workbook: '.implode(', ', array_map(fn ($l) => __("pricing.lists.{$l}"), $match['missing'])).'.']);
        }

        $result = $this->sessions->start($file, array_values($match['found']), $data['wef_date'], $data['hold_lists'] ?? [], backpack_user()->id);
        if (! $result->ok) {
            return redirect()->route('pricing.workflow.index')->with('warning', $result->message);
        }
        DetectPriceListsJob::dispatch($result->get('session')->id);

        return redirect()->route('pricing.workflow.index')->with('success', $result->message.' Detecting new vehicles…');
    }

    /** Polled by the process screen: stage + the running step's progress. */
    public function status(int $sessionId): JsonResponse
    {
        if (! backpack_user()->can('PRC_WKFL_VIEW')) {
            abort(403, 'You do not have permission to view the pricing process.');
        }
        $session = ImportSession::findOrFail($sessionId);
        $stage = $session->stage();

        return response()->json([
            'id' => $session->id,
            'stage' => $stage->value,
            'stage_label' => $stage->label(),
            'terminal' => $stage->isTerminal(),
            'progress' => $session->progress ?? [],
            'totals' => data_get($session->stats, 'detect.totals'),
            'batch' => $this->batchProgress($session->progress['batch_id'] ?? null),
        ]);
    }

    /** @return array{total: int, processed: int, failed: int, percent: int}|null a running Calculate & Publish batch */
    private function batchProgress(?string $batchId): ?array
    {
        $batch = $batchId ? Bus::findBatch($batchId) : null;

        return $batch ? ['total' => $batch->totalJobs, 'processed' => $batch->processedJobs(), 'failed' => $batch->failedJobs, 'percent' => $batch->progress()] : null;
    }

    public function discard(): RedirectResponse
    {
        if (! backpack_user()->can('PRC_WKFL_MANAGE')) {
            abort(403, 'You do not have permission to run the pricing process.');
        }
        $session = $this->sessions->gate();
        if (! $session) {
            return redirect()->route('pricing.workflow.index')->with('warning', 'No open pricing process to discard.');
        }
        $result = $this->sessions->discard($session, backpack_user()->id);

        return redirect()->route('pricing.workflow.index')->with($result->ok ? 'success' : 'warning', $result->message);
    }
}
