<?php

namespace App\Http\Controllers\Admin\Pricing\Process;

use App\Http\Controllers\Controller;
use App\Jobs\Vehicle\Pricing\Process\ImportPricesJob;
use App\Models\Vehicle\Pricing\ImportSession;
use App\Services\Vehicle\Pricing\Import\PriceListDetectService;
use App\Services\Vehicle\Pricing\Import\PricingWorkbookReader;
use App\Services\Vehicle\Pricing\Session\PricingIssueStore;
use App\Services\Vehicle\Pricing\Session\PricingSessionService;
use App\Services\Vehicle\Pricing\Session\PricingStage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Pricing process — step 4, price import (DEC-073 / DEC-076): the Pricing workbook uploaded at Start (or an updated one),
 * the lists and the WEF; queued; the run's per-list summary; continue to add-ons & discounts.
 */
class PricesController extends Controller
{
    public function __construct(
        private readonly PricingSessionService $sessions,
        private readonly PricingWorkbookReader $reader,
    ) {}

    public function show(): View|RedirectResponse
    {
        if (! backpack_user()->can('PRC_WKFL_VIEW')) {
            abort(403, 'You do not have permission to view the pricing process.');
        }
        $session = $this->sessions->gate();
        if (! $session || $session->stage()->order() < PricingStage::Prices->order()) {
            return redirect()->route('pricing.workflow.index')->with('warning', 'Price import opens after Vehicle Info.');
        }

        return view('admin.pricing.process.prices', [
            'title' => 'Price Import',
            'session' => $session,
            'run' => data_get($session->stats, 'prices'),
            'lists' => PricingProcessController::LISTS,
            'selected' => $this->listsOf((array) $session->selected_sheets),
            'canManage' => backpack_user()->can('PRC_WKFL_MANAGE') && ! $session->isPublished(),
        ]);
    }

    public function import(Request $request): RedirectResponse
    {
        if (! backpack_user()->can('PRC_WKFL_MANAGE')) {
            abort(403, 'You do not have permission to run the pricing process.');
        }
        $data = $request->validate([
            'source' => ['required', Rule::in(['session', 'upload'])],
            'file' => ['required_if:source,upload', 'nullable', 'file', 'mimes:xlsx', 'max:20480'],
            'lists' => ['required', 'array', 'min:1'],
            'lists.*' => ['string', Rule::in(PricingProcessController::LISTS)],
            'wef_date' => ['required', 'date'],
        ], [], __('pricing.fields'));

        $session = $this->sessions->gate();
        if (! $session || $session->stage()->order() < PricingStage::Prices->order()) {
            return redirect()->route('pricing.workflow.index')->with('warning', 'Price import opens after Vehicle Info.');
        }
        if ($session->isPublished()) {
            return redirect()->route('pricing.workflow.prices-form')->with('warning', 'Prices are already published in this process.');
        }
        if (($session->progress['state'] ?? null) === 'running') {
            return redirect()->route('pricing.workflow.prices-form')->with('warning', 'A step is still running — wait for it to finish.');
        }

        $path = $data['source'] === 'upload'
            ? $this->sessions->storeUpload($session, $request->file('file'), 'pricing')
            : (string) $session->upload_path;
        try {
            $titles = $this->reader->sheetNames($this->sessions->uploadAbsolutePath($session, $path));
        } catch (\Throwable) {
            throw ValidationException::withMessages(['file' => 'The workbook could not be read — upload a valid .xlsx file.']);
        }
        $match = PriceListDetectService::matchSheets($titles, $data['lists']);
        if ($match['missing'] !== []) {
            throw ValidationException::withMessages(['lists' => 'Not in this workbook: '.implode(', ', array_map(fn ($l) => __("pricing.lists.{$l}"), $match['missing'])).'.']);
        }

        $this->sessions->progress($session, ['step' => 'prices', 'state' => 'running', 'message' => 'Queued — waiting for the queue worker…', 'error' => null]);
        ImportPricesJob::dispatch($session->id, $path, array_values($match['found']), $data['wef_date']);

        return redirect()->route('pricing.workflow.prices-form')->with('success', 'Price import started.');
    }

    /** The run's row issues (skipped / rejected) as a workbook. */
    public function issues(int $sessionId): BinaryFileResponse
    {
        if (! backpack_user()->can('PRC_WKFL_VIEW')) {
            abort(403, 'You do not have permission to view the pricing process.');
        }
        $session = ImportSession::findOrFail($sessionId);
        $issues = app(PricingIssueStore::class)->all($session, 'prices');

        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('Issues');
        $sheet->fromArray([['Sheet', 'Model Code', 'Reason']]);
        $sheet->fromArray(array_map(fn (array $i) => [$i['sheet'], $i['code'], $i['reason']], $issues), null, 'A2', true);
        $sheet->getStyle('A1:C1')->getFont()->setBold(true);
        $relative = "pricing/{$session->id}/price-issues.xlsx";
        Storage::disk('local')->makeDirectory("pricing/{$session->id}");
        (new Xlsx($book))->save(Storage::disk('local')->path($relative));
        $book->disconnectWorksheets();

        return response()->download(Storage::disk('local')->path($relative), "Price_Import_Issues_{$session->id}.xlsx");
    }

    public function continue(): RedirectResponse
    {
        if (! backpack_user()->can('PRC_WKFL_MANAGE')) {
            abort(403, 'You do not have permission to run the pricing process.');
        }
        $session = $this->sessions->gate();
        if (! $session || $session->stage() !== PricingStage::Prices) {
            return redirect()->route('pricing.workflow.index')->with('warning', 'The process is not at the price import step.');
        }
        if (($session->progress['state'] ?? null) === 'running') {
            return redirect()->route('pricing.workflow.prices-form')->with('warning', 'A step is still running — wait for it to finish.');
        }
        if (! data_get($session->stats, 'prices')) {
            return redirect()->route('pricing.workflow.prices-form')->with('warning', 'Import the prices first.');
        }
        $this->sessions->advance($session, PricingStage::Addons, [], backpack_user()->id);

        return redirect()->route('pricing.workflow.addons-form')->with('success', 'Prices imported — add-ons & discounts next.');
    }

    /**
     * @param  list<string>  $titles
     * @return list<string> PV, CV, BEV, LMM, LMM_TZU, CSD
     */
    private function listsOf(array $titles): array
    {
        return array_values(array_filter(array_map(
            fn (string $t) => ($code = PriceListDetectService::sheetCode($t)) ? substr($code, strlen('PRICE_LIST_')) : null,
            $titles
        )));
    }
}
