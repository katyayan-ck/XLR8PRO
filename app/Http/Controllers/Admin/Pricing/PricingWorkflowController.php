<?php

/**
 * Path: app/Http/Controllers/Admin/Pricing/PricingWorkflowController.php
 */

namespace App\Http\Controllers\Admin\Pricing;

use App\Http\Controllers\Controller;
use App\Jobs\Vehicle\Pricing\CalculatePricingSessionJob;
use App\Jobs\Vehicle\Pricing\DetectPricingWorkbookJob;
use App\Jobs\Vehicle\Pricing\ImportPriceListsJob;
use App\Models\Vehicle\Pricing\Affected;
use App\Models\Vehicle\Pricing\ChangeFlag;
use App\Models\Vehicle\Pricing\ImportSession;
use App\Models\Vehicle\Pricing\Pricing;
use App\Models\Vehicle\Pricing\Profile;
use App\Services\Vehicle\Pricing\AddonDiscountExportService;
use App\Services\Vehicle\Pricing\AddonDiscountImportService;
use App\Services\Vehicle\Pricing\PricingSessionService;
use App\Services\Vehicle\Pricing\RulesWorkbookService;
use App\Services\Vehicle\Pricing\VehicleInfoExportService;
use App\Services\Vehicle\Pricing\VehicleInfoImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PricingWorkflowController extends Controller
{
    public function __construct(
        protected PricingSessionService $sessions,
        protected VehicleInfoExportService $vehicleInfoExport,
        protected VehicleInfoImportService $vehicleInfoImport,
        protected AddonDiscountImportService $addonsImport,
        protected AddonDiscountExportService $addonsExport,
        protected RulesWorkbookService $rules
    ) {}

    public function index()
    {
        if (! backpack_user()->can('PRC_WKFL_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view the pricing workflow.');
        }

        return view('admin.pricing.workflow.index', [
            'title' => 'Pricing Workflow',
            'session' => $this->sessions->activeSession(),
        ]);
    }

    public function startForm()
    {
        if (! backpack_user()->can('PRC_WKFL_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view the pricing workflow.');
        }

        if ($this->sessions->activeSession()) {
            return redirect()
                ->route('pricing.workflow.index')
                ->with('warning', 'A pricing process is already active. Resume or discard it first.');
        }

        return view('admin.pricing.workflow.start', [
            'title' => 'Start Pricing Process — Upload Price Lists',
            'sheetOptions' => $this->sheetOptions(),
        ]);
    }

    public function startDetect(Request $request)
    {
        if (! backpack_user()->can('PRC_WKFL_MANAGE')) {
            abort(403, 'Unauthorized. You do not have permission to start a pricing workflow.');
        }

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls',
            'sheet_types' => 'required|array|min:1',
            'sheet_types.*' => 'string',
            'wef_date' => 'nullable|date',
        ]);

        if ($this->sessions->activeSession()) {
            return response()->json([
                'success' => false,
                'message' => 'Another pricing process is active. Resume or discard it first.',
            ], 409);
        }

        $path = $request->file('file')->store('pricing-uploads');
        $absolute = Storage::path($path);
        $sheets = array_map('strtoupper', $request->input('sheet_types', []));
        $wef = $request->input('wef_date', now()->toDateString());
        $userId = auth()->id();

        try {
            $session = $this->sessions->start(
                $sheets,
                $wef,
                'Price list upload: '.$request->file('file')->getClientOriginalName()
            );
            $this->sessions->updateStats($session, [
                'workbook_path' => $absolute,
                'workbook_disk' => $path,
            ]);

            Cache::put('pricing_progress_'.$session->id, [
                'phase' => 'queued',
                'message' => 'Queued detect job — start queue worker if not running',
                'percent' => 1,
                'processed' => 0,
                'total' => 0,
                'done' => false,
                'failed' => false,
                'logs' => ['['.now()->format('H:i:s').'] Detect job dispatched for session #'.$session->id],
            ], now()->addHours(6));

            DetectPricingWorkbookJob::dispatch(
                $session->id,
                $absolute,
                $sheets,
                $wef,
                $userId
            );

            return response()->json([
                'success' => true,
                'async' => true,
                'session_id' => $session->id,
                'message' => 'Detect started in background. Watch the progress panel.',
                'progress_url' => route('pricing.workflow.progress', $session->id),
            ]);
        } catch (\Throwable $e) {
            Log::error('[PricingWorkflow] startDetect failed', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function progress(int $sessionId)
    {
        if (! backpack_user()->can('PRC_WKFL_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view the pricing workflow.');
        }

        $data = Cache::get('pricing_progress_'.$sessionId, [
            'phase' => 'unknown',
            'message' => 'No progress data yet — is queue:work running?',
            'percent' => 0,
            'done' => false,
            'failed' => false,
            'logs' => [],
        ]);

        $session = ImportSession::find($sessionId);
        $data['session_stage'] = $session?->current_stage;
        $data['session_status'] = $session?->status;
        $data['stats'] = $session?->stats;

        if (! empty($data['done']) && empty($data['failed']) && $session) {
            $data['export_url'] = route('pricing.workflow.vehicle-info-export', $sessionId);
            $data['vehicle_info_form'] = route('pricing.workflow.vehicle-info-form');
            $data['prices_form'] = route('pricing.workflow.prices-form');
        }

        return response()->json($data);
    }

    public function vehicleInfoForm()
    {
        if (! backpack_user()->can('PRC_WKFL_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view the pricing workflow.');
        }

        $session = $this->sessions->activeSession();
        if (! $session) {
            return redirect()->route('pricing.workflow.index')
                ->with('warning', 'No active pricing session.');
        }

        return view('admin.pricing.workflow.vehicle-info', [
            'title' => 'Complete Vehicle Info',
            'session' => $session,
        ]);
    }

    public function vehicleInfoExport(int $sessionId)
    {
        if (! backpack_user()->can('PRC_WKFL_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view the pricing workflow.');
        }

        $session = ImportSession::findOrFail($sessionId);
        $export = $this->vehicleInfoExport->exportForSession($session);

        return response()->download($export['path'], $export['filename'])->deleteFileAfterSend(false);
    }

    public function vehicleInfoImport(Request $request)
    {
        if (! backpack_user()->can('PRC_WKFL_MANAGE')) {
            abort(403, 'Unauthorized. You do not have permission to run the pricing workflow.');
        }

        $request->validate(['file' => 'required|file|mimes:xlsx,xls']);

        $session = $this->sessions->activeSession();
        if (! $session) {
            return response()->json(['success' => false, 'message' => 'No active session.'], 409);
        }

        $path = $request->file('file')->store('pricing-uploads');
        $absolute = Storage::path($path);

        try {
            $stats = $this->vehicleInfoImport->importFile($absolute, $session);
            $this->sessions->updateStats($session, [
                'incomplete' => $stats['left_inactive'] ?? 0,
                'completed' => $stats['completed'] ?? 0,
            ]);
            $this->sessions->advance($session, ImportSession::STAGE_IMPORTING_PRICES);

            $rejected = $stats['rejected'] ?? [];
            $statsOut = $stats;
            $statsOut['rejected_total'] = count($rejected);
            $statsOut['rejected'] = array_slice($rejected, 0, 50);

            return response()->json([
                'success' => true,
                'session_id' => $session->id,
                'stats' => $statsOut,
                'stage' => ImportSession::STAGE_IMPORTING_PRICES,
                'message' => sprintf(
                    'Vehicle Info updated. Completed: %d | Still incomplete: %d | Rejected: %d',
                    $stats['completed'] ?? 0,
                    $stats['left_inactive'] ?? 0,
                    count($rejected)
                ),
                'next_step' => 'prices',
                'prices_url' => route('pricing.workflow.prices-form'),
            ]);
        } catch (\Throwable $e) {
            Log::error('[PricingWorkflow] vehicleInfoImport failed', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function vehicleInfoProgress(int $sessionId)
    {
        if (! backpack_user()->can('PRC_WKFL_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view the pricing workflow.');
        }

        return response()->json(Cache::get('pricing_vi_progress_'.$sessionId, [
            'phase' => 'idle',
            'message' => 'Waiting…',
            'percent' => 0,
            'done' => false,
            'logs' => [],
        ]));
    }

    public function pricesForm()
    {
        if (! backpack_user()->can('PRC_WKFL_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view the pricing workflow.');
        }

        $session = $this->sessions->activeSession();
        if (! $session) {
            return redirect()->route('pricing.workflow.index')
                ->with('warning', 'No active pricing session.');
        }

        return view('admin.pricing.workflow.prices', [
            'title' => 'Import Price Lists',
            'session' => $session,
            'sheetOptions' => $this->sheetOptions(),
        ]);
    }

    public function pricesImport(Request $request)
    {
        if (! backpack_user()->can('PRC_WKFL_MANAGE')) {
            abort(403, 'Unauthorized. You do not have permission to run the pricing workflow.');
        }

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls',
            'sheet_types' => 'required|array|min:1',
            'sheet_types.*' => 'string',
            'wef_date' => 'nullable|date',
        ]);

        $session = $this->sessions->activeSession();
        if (! $session) {
            return response()->json(['success' => false, 'message' => 'No active session.'], 409);
        }

        $path = $request->file('file')->store('pricing-uploads');
        $absolute = Storage::path($path);
        $sheets = array_map('strtoupper', $request->input('sheet_types', $session->selected_sheets ?? []));
        $wef = $request->input('wef_date', $session->wef_date?->format('Y-m-d') ?? now()->toDateString());
        $userId = $request->user()?->id;

        $this->sessions->advance($session, ImportSession::STAGE_IMPORTING_PRICES);
        $this->sessions->updateStats($session, ['price_workbook_path' => $absolute]);

        Cache::put('pricing_progress_'.$session->id, [
            'phase' => 'queued',
            'message' => 'Queued price import — waiting for worker…',
            'percent' => 1,
            'done' => false,
            'failed' => false,
            'logs' => ['['.now()->format('H:i:s').'] ImportPriceListsJob queued'],
        ], now()->addHours(6));

        ImportPriceListsJob::dispatch($session->id, $absolute, $sheets, $wef, $userId);

        return response()->json([
            'success' => true,
            'session_id' => $session->id,
            'queued' => true,
            'message' => 'Price import queued. Keep this page open — progress updates live.',
            'progress_url' => route('pricing.workflow.progress', $session->id),
        ]);
    }

    public function discard(Request $request)
    {
        if (! backpack_user()->can('PRC_WKFL_MANAGE')) {
            abort(403, 'Unauthorized. You do not have permission to run the pricing workflow.');
        }

        $session = $this->sessions->activeSession();
        if (! $session) {
            return redirect()->route('pricing.workflow.index')
                ->with('warning', 'No active session to discard.');
        }

        try {
            $this->sessions->discard($session);
            Cache::forget('pricing_progress_'.$session->id);
            Cache::forget('pricing_vi_progress_'.$session->id);
            \Alert::success('Pricing process discarded. Session-tagged data rolled back.')->flash();
        } catch (\Throwable $e) {
            \Alert::error($e->getMessage())->flash();
        }

        return redirect()->route('pricing.workflow.index');
    }

    public function impactSummary(int $sessionId)
    {
        if (! backpack_user()->can('PRC_WKFL_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view the pricing workflow.');
        }

        $session = ImportSession::findOrFail($sessionId);
        $flags = ChangeFlag::query()
            ->where('import_session_id', $sessionId)
            ->where('is_processed', false)
            ->get();

        $byType = $flags->groupBy('change_type')->map->count();
        $flagCodes = $flags->pluck('model_code')->filter()->unique()->values();

        $complete = Profile::query()
            ->where('is_vehicle_master_complete', true)
            ->count();
        $priced = Pricing::query()
            ->where('is_active', true)
            ->count();

        $can = $complete > 0 && $priced > 0;

        return response()->json([
            'success' => true,
            'session' => [
                'id' => $session->id,
                'wef_date' => $session->wef_date?->format('Y-m-d'),
                'status' => $session->status,
                'current_stage' => $session->current_stage,
                'stats' => $session->stats,
            ],
            'total_affected' => $flagCodes->count(),
            'complete_masters' => $complete,
            'active_price_rows' => $priced,
            'by_type' => $byType,
            'can_calculate' => $can,
            'message' => $can
                ? "Review impact and run Calculate when ready. Complete masters: {$complete}, active prices: {$priced}."
                : 'Nothing to calculate — need at least one complete master with an active price row.',
        ]);
    }

    public function impactSummaryView(int $sessionId)
    {
        if (! backpack_user()->can('PRC_WKFL_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view the pricing workflow.');
        }

        return view('admin.pricing.workflow.impact-summary', [
            'title' => 'Impact Summary',
            'sessionId' => $sessionId,
        ]);
    }

    public function calculateAndPublish(Request $request, int $sessionId)
    {
        if (! backpack_user()->can('PRC_WKFL_MANAGE')) {
            abort(403, 'Unauthorized. You do not have permission to run the pricing workflow.');
        }

        $session = ImportSession::findOrFail($sessionId);
        if ($session->isTerminal()) {
            return response()->json(['success' => false, 'message' => 'Session already closed.'], 409);
        }

        Cache::put('pricing_progress_'.$session->id, [
            'phase' => 'queued',
            'message' => 'Calculate queued…',
            'percent' => 1,
            'done' => false,
            'failed' => false,
            'logs' => ['['.now()->format('H:i:s').'] CalculatePricingSessionJob dispatched'],
        ], now()->addHours(6));

        CalculatePricingSessionJob::dispatch($session->id, 'normal', auth()->id());

        return response()->json([
            'success' => true,
            'session_id' => $session->id,
            'queued' => true,
            'message' => 'Calculate & Publish queued. Watch progress.',
            'progress_url' => route('pricing.workflow.progress', $session->id),
        ]);
    }

    public function sessionStatus(int $sessionId)
    {
        if (! backpack_user()->can('PRC_WKFL_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view the pricing workflow.');
        }

        $session = ImportSession::findOrFail($sessionId);
        $affected = Affected::forSession($sessionId)->get();

        $total = $affected->count();
        $done = $affected->where('status', 'done')->count();
        $failed = $affected->where('status', 'error')->count();
        $pending = $affected->where('status', 'pending')->count();
        $processing = $affected->where('status', 'processing')->count();

        return response()->json([
            'session_id' => $sessionId,
            'status' => $session->status,
            'stage' => $session->current_stage,
            'total' => $total,
            'done' => $done,
            'failed' => $failed,
            'pending' => $pending,
            'processing' => $processing,
            'is_finished' => ($pending + $processing) === 0,
            'progress' => $total > 0 ? round(($done + $failed) / $total * 100, 1) : 100,
            'stats' => $session->stats,
        ]);
    }

    public function failedVehicles(int $sessionId)
    {
        if (! backpack_user()->can('PRC_WKFL_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view the pricing workflow.');
        }

        $failed = Affected::forSession($sessionId)
            ->where('status', 'error')
            ->get(['model_code', 'variant_code', 'error_message', 'updated_at']);

        return response()->json([
            'success' => true,
            'count' => $failed->count(),
            'vehicles' => $failed,
        ]);
    }

    public function addonsForm()
    {
        if (! backpack_user()->can('PRC_WKFL_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view the pricing workflow.');
        }

        $session = $this->sessions->activeSession();
        if (! $session) {
            return redirect()->route('pricing.workflow.index')
                ->with('warning', 'No active pricing session.');
        }

        return view('admin.pricing.workflow.addons', [
            'title' => 'Addons & Discounts',
            'session' => $session,
            'sheetOptions' => [
                'DEALER_CHARGES' => 'Dealer Charges',
                'RSA' => 'RSA',
                'SHIELD' => 'Shield',
                'EXCHANGE' => 'Exchange',
                'CORPORATE' => 'Corporate',
            ],
        ]);
    }

    public function addonsExport(int $sessionId)
    {
        if (! backpack_user()->can('PRC_WKFL_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view the pricing workflow.');
        }

        $session = ImportSession::findOrFail($sessionId);
        $export = $this->addonsExport->exportForSession($session);

        return response()->download($export['path'], $export['filename'])->deleteFileAfterSend(false);
    }

    public function addonsImport(Request $request)
    {
        if (! backpack_user()->can('PRC_WKFL_MANAGE')) {
            abort(403, 'Unauthorized. You do not have permission to run the pricing workflow.');
        }

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls',
            'sheet_types' => 'required|array|min:1',
            'sheet_types.*' => 'string',
            'wef_date' => 'nullable|date',
        ]);

        $session = $this->sessions->activeSession();
        if (! $session) {
            return response()->json(['success' => false, 'message' => 'No active session.'], 409);
        }

        $path = $request->file('file')->store('pricing-uploads');
        $absolute = Storage::path($path);
        $sheets = array_map('strtoupper', $request->input('sheet_types', []));
        $wef = $request->input('wef_date', $session->wef_date?->format('Y-m-d') ?? now()->toDateString());

        try {
            $this->sessions->advance($session, ImportSession::STAGE_IMPORTING_ADDONS);
            $stats = $this->addonsImport->importFile($absolute, $session, $sheets, $wef, auth()->id());
            $this->sessions->updateStats($session, ['addons' => $stats]);
            $this->sessions->advance($session, ImportSession::STAGE_AWAITING_RULES);

            return response()->json([
                'success' => true,
                'session_id' => $session->id,
                'stats' => $stats,
                'stage' => ImportSession::STAGE_AWAITING_RULES,
                'message' => 'Addons / discounts imported.',
                'next_step' => 'rules',
                'rules_url' => route('pricing.workflow.rules-form'),
            ]);
        } catch (\Throwable $e) {
            Log::error('[PricingWorkflow] addonsImport failed', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function rulesForm()
    {
        if (! backpack_user()->can('PRC_WKFL_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view the pricing workflow.');
        }

        $session = $this->sessions->activeSession();
        if (! $session) {
            return redirect()->route('pricing.workflow.index')
                ->with('warning', 'No active pricing session.');
        }

        return view('admin.pricing.workflow.rules', [
            'title' => 'Insurance & RTO Rules',
            'session' => $session,
            'presence' => $this->rules->presence(),
        ]);
    }

    public function rulesExport(int $sessionId)
    {
        if (! backpack_user()->can('PRC_WKFL_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view the pricing workflow.');
        }

        $session = ImportSession::findOrFail($sessionId);
        $export = $this->rules->exportCurrent($session);

        return response()->download($export['path'], $export['filename'])->deleteFileAfterSend(false);
    }

    public function rulesKeep()
    {
        if (! backpack_user()->can('PRC_WKFL_MANAGE')) {
            abort(403, 'Unauthorized. You do not have permission to run the pricing workflow.');
        }

        $session = $this->sessions->activeSession();
        if (! $session) {
            return response()->json(['success' => false, 'message' => 'No active session.'], 409);
        }
        $presence = $this->rules->presence();
        if (! $presence['any']) {
            return response()->json([
                'success' => false,
                'message' => 'No Insurance / RTO rules in database. Import a workbook first.',
            ], 422);
        }
        $this->sessions->updateStats($session, ['rules' => ['kept_existing' => true] + $presence]);
        $this->sessions->advance($session, ImportSession::STAGE_AWAITING_RULES);

        return response()->json([
            'success' => true,
            'message' => 'Keeping existing Insurance / RTO rules.',
            'impact_url' => route('pricing.workflow.impact-summary-view', $session->id),
        ]);
    }

    public function rulesImport(Request $request)
    {
        if (! backpack_user()->can('PRC_WKFL_MANAGE')) {
            abort(403, 'Unauthorized. You do not have permission to run the pricing workflow.');
        }

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls',
            'kinds' => 'required|array|min:1',
            'kinds.*' => 'in:rto,insurance',
            'wef_date' => 'nullable|date',
        ]);

        $session = $this->sessions->activeSession();
        if (! $session) {
            return response()->json(['success' => false, 'message' => 'No active session.'], 409);
        }

        $path = $request->file('file')->store('pricing-uploads');
        $absolute = Storage::path($path);
        $wef = $request->input('wef_date', $session->wef_date?->format('Y-m-d') ?? now()->toDateString());

        try {
            $stats = $this->rules->importFile(
                $absolute,
                $session,
                $request->input('kinds', ['rto', 'insurance']),
                $wef,
                auth()->id()
            );
            $this->sessions->updateStats($session, ['rules' => $stats]);

            return response()->json([
                'success' => true,
                'stats' => $stats,
                'message' => 'Rules imported.',
                'impact_url' => route('pricing.workflow.impact-summary-view', $session->id),
            ]);
        } catch (\Throwable $e) {
            Log::error('[PricingWorkflow] rulesImport failed', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    protected function sheetOptions(): array
    {
        return [
            'PRICE_LIST_PV' => 'Price List PV',
            'PRICE_LIST_CV' => 'Price List CV',
            'PRICE_LIST_BEV' => 'Price List BEV',
            'PRICE_LIST_LMM' => 'Price List LMM',
            'PRICE_LIST_LMM_TZU' => 'Price List LMM TZU',
            'PRICE_LIST_CSD' => 'Price List CSD',
        ];
    }
}
