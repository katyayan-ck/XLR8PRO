<?php

namespace App\Http\Controllers\Admin\Pricing\Process;

use App\Http\Controllers\Controller;
use App\Jobs\Vehicle\Pricing\Process\ImportRulesJob;
use App\Models\Vehicle\Pricing\ImportSession;
use App\Services\Vehicle\Pricing\Import\InsuranceWorkbookService;
use App\Services\Vehicle\Pricing\Import\RtoWorkbookService;
use App\Services\Vehicle\Pricing\Session\PricingIssueStore;
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
 * Pricing process — step 6, insurance & RTO (DEC-073 / DEC-078): two standalone workbooks. A kind with no rules must be
 * imported; otherwise keep it, or download → edit → re-import (queued). Continue needs both kinds present.
 */
class RulesController extends Controller
{
    public function __construct(
        private readonly PricingSessionService $sessions,
        private readonly InsuranceWorkbookService $insurance,
        private readonly RtoWorkbookService $rto,
    ) {}

    public function show(): View|RedirectResponse
    {
        if (! backpack_user()->can('PRC_WKFL_VIEW')) {
            abort(403, 'You do not have permission to view the pricing process.');
        }
        $session = $this->sessions->gate();
        if (! $session || $session->stage()->order() < PricingStage::Rules->order()) {
            return redirect()->route('pricing.workflow.index')->with('warning', __('pricing.flash.insurance_rto_open_after_add_ons'));
        }

        return view('admin.pricing.process.rules', [
            'title' => 'Insurance & RTO',
            'session' => $session,
            'runs' => (array) data_get($session->stats, 'rules', []),
            'presence' => $this->presence(),
            'canManage' => backpack_user()->can('PRC_WKFL_MANAGE') && ! $session->isPublished(),
        ]);
    }

    public function export(string $kind, int $sessionId): BinaryFileResponse
    {
        if (! backpack_user()->can('PRC_WKFL_VIEW')) {
            abort(403, 'You do not have permission to view the pricing process.');
        }
        abort_unless(in_array($kind, ImportRulesJob::KINDS, true), 404);
        $session = ImportSession::findOrFail($sessionId);
        $relative = "pricing/{$session->id}/{$kind}-export-".now()->format('His').'.xlsx';
        Storage::disk('local')->makeDirectory("pricing/{$session->id}");
        $kind === 'rto' ? $this->rto->export(Storage::disk('local')->path($relative)) : $this->insurance->export(Storage::disk('local')->path($relative));

        return response()->download(Storage::disk('local')->path($relative), ($kind === 'rto' ? 'RTO-Rules_' : 'Insurance_').now()->format('Y-m-d').'.xlsx');
    }

    public function import(Request $request): RedirectResponse
    {
        if (! backpack_user()->can('PRC_WKFL_MANAGE')) {
            abort(403, 'You do not have permission to run the pricing process.');
        }
        $data = $request->validate([
            'kind' => ['required', Rule::in(ImportRulesJob::KINDS)],
            'file' => ['required', 'file', 'mimes:xlsx', 'max:20480'],
            'wef_date' => ['required', 'date'],
        ], [], __('pricing.fields'));

        $session = $this->sessions->gate();
        if (! $session || $session->stage()->order() < PricingStage::Rules->order()) {
            return redirect()->route('pricing.workflow.index')->with('warning', __('pricing.flash.insurance_rto_open_after_add_ons'));
        }
        if ($session->isPublished()) {
            return redirect()->route('pricing.workflow.rules-form')->with('warning', __('pricing.flash.prices_are_already_published_in_process'));
        }
        if (($session->progress['state'] ?? null) === 'running') {
            return redirect()->route('pricing.workflow.rules-form')->with('warning', __('pricing.flash.step_still_running_wait_it_finish'));
        }

        $path = $this->sessions->storeUpload($session, $request->file('file'), $data['kind']);
        $this->sessions->progress($session, ['step' => 'rules', 'state' => 'running', 'message' => 'Queued — waiting for the queue worker…', 'error' => null]);
        ImportRulesJob::dispatch($session->id, $data['kind'], $path, $data['wef_date']);

        return redirect()->route('pricing.workflow.rules-form')->with('success', __('pricing.flash.rules_import_started', ['kind' => $data['kind'] === 'rto' ? 'RTO' : 'Insurance']));
    }

    public function issues(string $kind, int $sessionId): BinaryFileResponse
    {
        if (! backpack_user()->can('PRC_WKFL_VIEW')) {
            abort(403, 'You do not have permission to view the pricing process.');
        }
        abort_unless(in_array($kind, ImportRulesJob::KINDS, true), 404);
        $session = ImportSession::findOrFail($sessionId);
        $issues = app(PricingIssueStore::class)->all($session, 'rules-'.$kind);

        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('Issues');
        $sheet->fromArray([['Sheet', 'Row', 'Reason']]);
        $sheet->fromArray(array_map(fn (array $i) => [$i['sheet'], $i['row'], $i['reason']], $issues), null, 'A2', true);
        $sheet->getStyle('A1:C1')->getFont()->setBold(true);
        $relative = "pricing/{$session->id}/{$kind}-issues.xlsx";
        Storage::disk('local')->makeDirectory("pricing/{$session->id}");
        (new Xlsx($book))->save(Storage::disk('local')->path($relative));
        $book->disconnectWorksheets();

        return response()->download(Storage::disk('local')->path($relative), ucfirst($kind)."_Issues_{$session->id}.xlsx");
    }

    public function continue(): RedirectResponse
    {
        if (! backpack_user()->can('PRC_WKFL_MANAGE')) {
            abort(403, 'You do not have permission to run the pricing process.');
        }
        $session = $this->sessions->gate();
        if (! $session || $session->stage() !== PricingStage::Rules) {
            return redirect()->route('pricing.workflow.index')->with('warning', __('pricing.flash.process_not_at_insurance_rto_step'));
        }
        if (($session->progress['state'] ?? null) === 'running') {
            return redirect()->route('pricing.workflow.rules-form')->with('warning', __('pricing.flash.step_still_running_wait_it_finish'));
        }
        $missing = array_keys(array_filter($this->presence(), fn (bool $ready) => ! $ready));
        if ($missing !== []) {
            return redirect()->route('pricing.workflow.rules-form')->with('warning', __('pricing.flash.import_rules_first', ['kinds' => implode(' and ', array_map('strtoupper', $missing))]));
        }
        $this->sessions->advance($session, PricingStage::Impact, [], backpack_user()->id);

        return redirect()->route('pricing.workflow.impact-summary-view', $session->id)->with('success', __('pricing.flash.insurance_rto_ready_review_impact_summary'));
    }

    /** @return array{insurance: bool, rto: bool} kind => rules stored (insurance needs premium rules and companies) */
    private function presence(): array
    {
        $insurance = $this->insurance->presence();

        return ['insurance' => $insurance['premium'] > 0 && $insurance['companies'] > 0, 'rto' => $this->rto->presence() > 0];
    }
}
