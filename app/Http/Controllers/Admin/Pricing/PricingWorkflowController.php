<?php

/**
 * Path: app/Http/Controllers/Admin/Pricing/PricingWorkflowController.php
 */

namespace App\Http\Controllers\Admin\Pricing;

use App\Http\Controllers\Controller;
use App\Jobs\Vehicle\Pricing\CalculatePricingSessionJob;
use App\Models\Vehicle\Pricing\Affected;
use App\Models\Vehicle\Pricing\ChangeFlag;
use App\Models\Vehicle\Pricing\ImportSession;
use App\Models\Vehicle\Pricing\Pricing;
use App\Models\Vehicle\Pricing\Profile;
use App\Services\Vehicle\Pricing\PricingSessionService;
use App\Services\Vehicle\Pricing\RulesWorkbookService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PricingWorkflowController extends Controller
{
    public function __construct(
        protected PricingSessionService $sessions,
        protected RulesWorkbookService $rules
    ) {}

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
            'progress_url' => route('pricing.workflow.session-status', $session->id),
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
}
