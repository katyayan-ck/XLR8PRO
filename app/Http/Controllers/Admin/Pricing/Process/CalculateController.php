<?php

namespace App\Http\Controllers\Admin\Pricing\Process;

use App\Http\Controllers\Controller;
use App\Models\Vehicle\Pricing\CalcResult;
use App\Models\Vehicle\Pricing\ImportSession;
use App\Services\Vehicle\Pricing\Engine\PricingCalculationService;
use App\Services\Vehicle\Pricing\PricingHoldService;
use App\Services\Vehicle\Pricing\Session\PricingSessionService;
use App\Services\Vehicle\Pricing\Session\PricingStage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Pricing process — step 9, Calculate & Publish, and step 10, the process summary (DEC-073 / DEC-080): start the
 * queued calculation, follow its progress, read the counts and failures, retry failed vehicles, complete the process
 * (reopening chosen held lists) — which releases the one-process gate.
 */
class CalculateController extends Controller
{
    public function __construct(
        private readonly PricingSessionService $sessions,
        private readonly PricingCalculationService $calculation,
        private readonly PricingHoldService $holds,
    ) {}

    public function start(): RedirectResponse
    {
        if (! backpack_user()->can('PRC_WKFL_MANAGE')) {
            abort(403, 'You do not have permission to run the pricing process.');
        }
        $session = $this->sessions->gate();
        if (! $session) {
            return redirect()->route('pricing.workflow.index')->with('warning', __('pricing.flash.no_open_pricing_process'));
        }
        $result = $this->calculation->start($session, backpack_user()->id);
        if (! $result->ok) {
            return redirect()->route('pricing.workflow.impact-summary-view', $session->id)->with('warning', $result->message);
        }

        return redirect()->route('pricing.workflow.summary', $session->id)->with('success', $result->message);
    }

    public function summary(int $sessionId): View|RedirectResponse
    {
        if (! backpack_user()->can('PRC_WKFL_VIEW')) {
            abort(403, 'You do not have permission to view the pricing process.');
        }
        $session = ImportSession::findOrFail($sessionId);
        if ($session->stage()->order() < PricingStage::Calculating->order() && ! $session->isTerminal()) {
            return redirect()->route('pricing.workflow.impact-summary-view', $session->id);
        }
        $results = CalcResult::query()->where('import_session_id', $session->id);

        return view('admin.pricing.process.summary', [
            'title' => 'Process Summary',
            'session' => $session,
            'counts' => $this->calculation->counts($session),
            'byList' => (clone $results)->selectRaw("COALESCE(price_list, '—') as list, status, count(*) c, sum(snapshots) s")->groupBy('list', 'status')->get()->groupBy('list'),
            'failures' => (clone $results)->where('status', CalcResult::FAILED)->orderBy('model_code')->limit(200)->get(),
            'failedTotal' => (clone $results)->where('status', CalcResult::FAILED)->count(),
            'heldLists' => $this->holds->heldLists(),
            'holdLabels' => PricingHoldService::LISTS,
            'canManage' => backpack_user()->can('PRC_WKFL_MANAGE') && ! $session->isTerminal(),
        ]);
    }

    /** Every failed or skipped vehicle of the run, with the reason. */
    public function results(int $sessionId): BinaryFileResponse
    {
        if (! backpack_user()->can('PRC_WKFL_VIEW')) {
            abort(403, 'You do not have permission to view the pricing process.');
        }
        $session = ImportSession::findOrFail($sessionId);
        $rows = CalcResult::query()->where('import_session_id', $session->id)->where('status', '!=', CalcResult::PUBLISHED)
            ->orderBy('status')->orderBy('model_code')->get(['model_code', 'price_list', 'status', 'message'])
            ->map(fn (CalcResult $r) => [$r->model_code, $r->price_list, ucfirst($r->status), $r->message])->all();

        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('Not published');
        $sheet->fromArray(array_merge([['Model Code', 'List', 'Result', 'Reason']], $rows), null, 'A1', true);
        $sheet->getStyle('A1:D1')->getFont()->setBold(true);
        $relative = "pricing/{$session->id}/calculation-results.xlsx";
        Storage::disk('local')->makeDirectory("pricing/{$session->id}");
        (new Xlsx($book))->save(Storage::disk('local')->path($relative));
        $book->disconnectWorksheets();

        return response()->download(Storage::disk('local')->path($relative), "Calculation_Not_Published_{$session->id}.xlsx");
    }

    public function retry(): RedirectResponse
    {
        if (! backpack_user()->can('PRC_WKFL_MANAGE')) {
            abort(403, 'You do not have permission to run the pricing process.');
        }
        $session = $this->sessions->gate();
        if (! $session) {
            return redirect()->route('pricing.workflow.index')->with('warning', __('pricing.flash.no_open_pricing_process'));
        }
        $result = $this->calculation->retryFailed($session);

        return redirect()->route('pricing.workflow.summary', $session->id)->with($result->ok ? 'success' : 'warning', $result->message);
    }

    public function complete(Request $request): RedirectResponse
    {
        if (! backpack_user()->can('PRC_WKFL_MANAGE')) {
            abort(403, 'You do not have permission to run the pricing process.');
        }
        $data = $request->validate([
            'reopen_lists' => ['nullable', 'array'],
            'reopen_lists.*' => ['string', Rule::in(array_keys(PricingHoldService::LISTS))],
        ], [], __('pricing.fields'));
        $session = $this->sessions->gate();
        if (! $session) {
            return redirect()->route('pricing.workflow.index')->with('warning', __('pricing.flash.no_open_pricing_process'));
        }
        $result = $this->sessions->complete($session, $data['reopen_lists'] ?? [], backpack_user()->id);
        if (! $result->ok) {
            return redirect()->route('pricing.workflow.summary', $session->id)->with('warning', $result->message);
        }

        return redirect()->route('pricing.workflow.index')->with('success', $result->message.(($data['reopen_lists'] ?? []) !== [] ? ' '.__('pricing.flash.lists_reopened', ['lists' => implode(', ', $data['reopen_lists'])]) : ''));
    }
}
