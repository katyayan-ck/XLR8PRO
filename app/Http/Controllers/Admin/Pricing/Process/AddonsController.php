<?php

namespace App\Http\Controllers\Admin\Pricing\Process;

use App\Http\Controllers\Controller;
use App\Jobs\Vehicle\Pricing\Process\ImportAddonsJob;
use App\Models\Vehicle\Pricing\ImportSession;
use App\Services\Vehicle\Pricing\Import\AddonDiscountWorkbookService;
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
 * Pricing process — step 5, add-ons & discounts (DEC-073 / DEC-077): export Addon-N-Discounts.xlsx for the ticked
 * groups, re-upload with a WEF (queued; ticked groups replaced), read the run's summary, continue to insurance & RTO.
 */
class AddonsController extends Controller
{
    public function __construct(
        private readonly PricingSessionService $sessions,
        private readonly AddonDiscountWorkbookService $workbook,
    ) {}

    public function show(): View|RedirectResponse
    {
        if (! backpack_user()->can('PRC_WKFL_VIEW')) {
            abort(403, 'You do not have permission to view the pricing process.');
        }
        $session = $this->sessions->gate();
        if (! $session || $session->stage()->order() < PricingStage::Addons->order()) {
            return redirect()->route('pricing.workflow.index')->with('warning', __('pricing.flash.add_ons_discounts_open_after_price'));
        }

        return view('admin.pricing.process.addons', [
            'title' => 'Add-ons & Discounts',
            'session' => $session,
            'run' => data_get($session->stats, 'addons'),
            'groups' => AddonDiscountWorkbookService::SHEETS,
            'presence' => $this->workbook->presence(),
            'canManage' => backpack_user()->can('PRC_WKFL_MANAGE') && ! $session->isPublished(),
        ]);
    }

    public function export(Request $request, int $sessionId): BinaryFileResponse
    {
        if (! backpack_user()->can('PRC_WKFL_VIEW')) {
            abort(403, 'You do not have permission to view the pricing process.');
        }
        $session = ImportSession::findOrFail($sessionId);
        $groups = array_values(array_intersect((array) $request->query('groups', array_keys(AddonDiscountWorkbookService::SHEETS)), array_keys(AddonDiscountWorkbookService::SHEETS)));
        abort_if($groups === [], 422, 'Tick at least one sheet.');

        $relative = "pricing/{$session->id}/addons-export-".now()->format('His').'.xlsx';
        Storage::disk('local')->makeDirectory("pricing/{$session->id}");
        $this->workbook->export(Storage::disk('local')->path($relative), $groups);

        return response()->download(Storage::disk('local')->path($relative), 'Addon-N-Discounts_'.now()->format('Y-m-d').'.xlsx');
    }

    public function import(Request $request): RedirectResponse
    {
        if (! backpack_user()->can('PRC_WKFL_MANAGE')) {
            abort(403, 'You do not have permission to run the pricing process.');
        }
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx', 'max:20480'],
            'groups' => ['required', 'array', 'min:1'],
            'groups.*' => ['string', Rule::in(array_keys(AddonDiscountWorkbookService::SHEETS))],
            'wef_date' => ['required', 'date'],
        ], [], __('pricing.fields'));

        $session = $this->sessions->gate();
        if (! $session || $session->stage()->order() < PricingStage::Addons->order()) {
            return redirect()->route('pricing.workflow.index')->with('warning', __('pricing.flash.add_ons_discounts_open_after_price'));
        }
        if ($session->isPublished()) {
            return redirect()->route('pricing.workflow.addons-form')->with('warning', __('pricing.flash.prices_are_already_published_in_process'));
        }
        if (($session->progress['state'] ?? null) === 'running') {
            return redirect()->route('pricing.workflow.addons-form')->with('warning', __('pricing.flash.step_still_running_wait_it_finish'));
        }

        $path = $this->sessions->storeUpload($session, $request->file('file'), 'addons');
        $this->sessions->progress($session, ['step' => 'addons', 'state' => 'running', 'message' => 'Queued — waiting for the queue worker…', 'error' => null]);
        ImportAddonsJob::dispatch($session->id, $path, $data['groups'], $data['wef_date']);

        return redirect()->route('pricing.workflow.addons-form')->with('success', __('pricing.flash.add_ons_discounts_import_started'));
    }

    public function issues(int $sessionId): BinaryFileResponse
    {
        if (! backpack_user()->can('PRC_WKFL_VIEW')) {
            abort(403, 'You do not have permission to view the pricing process.');
        }
        $session = ImportSession::findOrFail($sessionId);
        $issues = app(PricingIssueStore::class)->all($session, 'addons');

        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('Issues');
        $sheet->fromArray([['Sheet', 'Row', 'Reason']]);
        $sheet->fromArray(array_map(fn (array $i) => [$i['sheet'], $i['row'], $i['reason']], $issues), null, 'A2', true);
        $sheet->getStyle('A1:C1')->getFont()->setBold(true);
        $relative = "pricing/{$session->id}/addons-issues.xlsx";
        Storage::disk('local')->makeDirectory("pricing/{$session->id}");
        (new Xlsx($book))->save(Storage::disk('local')->path($relative));
        $book->disconnectWorksheets();

        return response()->download(Storage::disk('local')->path($relative), "Addons_Issues_{$session->id}.xlsx");
    }

    public function continue(): RedirectResponse
    {
        if (! backpack_user()->can('PRC_WKFL_MANAGE')) {
            abort(403, 'You do not have permission to run the pricing process.');
        }
        $session = $this->sessions->gate();
        if (! $session || $session->stage() !== PricingStage::Addons) {
            return redirect()->route('pricing.workflow.index')->with('warning', __('pricing.flash.process_not_at_add_ons_step'));
        }
        if (($session->progress['state'] ?? null) === 'running') {
            return redirect()->route('pricing.workflow.addons-form')->with('warning', __('pricing.flash.step_still_running_wait_it_finish'));
        }
        // either imported in this process, or the stored add-ons are kept as they are
        if (! data_get($session->stats, 'addons') && array_sum($this->workbook->presence()) === 0) {
            return redirect()->route('pricing.workflow.addons-form')->with('warning', __('pricing.flash.no_add_ons_or_discounts_are'));
        }
        $this->sessions->advance($session, PricingStage::Rules, [], backpack_user()->id);

        return redirect()->route('pricing.workflow.rules-form')->with('success', __('pricing.flash.add_ons_discounts_done_insurance_rto'));
    }
}
