<?php

namespace App\Http\Controllers\Admin\Pricing\Process;

use App\Http\Controllers\Controller;
use App\Jobs\Vehicle\Pricing\Process\ImportVehicleInfoJob;
use App\Models\Vehicle\Pricing\ImportSession;
use App\Services\Vehicle\Pricing\Import\VehicleInfoWorkbookService;
use App\Services\Vehicle\Pricing\Session\PricingIssueStore;
use App\Services\Vehicle\Pricing\Session\PricingSessionService;
use App\Services\Vehicle\Pricing\Session\PricingStage;
use App\Services\Vehicle\VehicleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Pricing process — step 3, Vehicle Info (DEC-073): download every vehicle, fill the masters, re-import (queued), read
 * the round's summary (completed / still incomplete / rejected), repeat, then continue to the price import.
 */
class VehicleInfoController extends Controller
{
    public function __construct(
        private readonly PricingSessionService $sessions,
        private readonly VehicleInfoWorkbookService $workbook,
        private readonly VehicleService $vehicles,
    ) {}

    public function show(): View|RedirectResponse
    {
        if (! backpack_user()->can('PRC_WKFL_VIEW')) {
            abort(403, 'You do not have permission to view the pricing process.');
        }
        $session = $this->sessions->gate();
        if (! $session || $session->stage()->order() < PricingStage::VehicleInfo->order()) {
            return redirect()->route('pricing.workflow.index')->with('warning', 'Vehicle Info opens after Detect has finished.');
        }

        return view('admin.pricing.process.vehicle-info', [
            'title' => 'Vehicle Info',
            'session' => $session,
            'round' => data_get($session->stats, 'vehicle_info'),
            'detect' => data_get($session->stats, 'detect.totals'),
            'counts' => $this->vehicles->statusCounts(),
            'canManage' => backpack_user()->can('PRC_WKFL_MANAGE'),
            'canImport' => ! $session->isPublished(),
        ]);
    }

    public function export(int $sessionId): BinaryFileResponse
    {
        if (! backpack_user()->can('PRC_WKFL_VIEW')) {
            abort(403, 'You do not have permission to view the pricing process.');
        }
        $session = ImportSession::findOrFail($sessionId);
        $relative = "pricing/{$session->id}/vehicle-info-export-".now()->format('His').'.xlsx';
        Storage::disk('local')->makeDirectory("pricing/{$session->id}");
        $this->workbook->export(Storage::disk('local')->path($relative));

        return response()->download(Storage::disk('local')->path($relative), "Vehicle_Info_{$session->id}_".now()->format('Y-m-d').'.xlsx');
    }

    public function import(Request $request): RedirectResponse
    {
        if (! backpack_user()->can('PRC_WKFL_MANAGE')) {
            abort(403, 'You do not have permission to run the pricing process.');
        }
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx', 'max:20480']], [], ['file' => __('pricing.fields.vehicle_info_file')]);
        $session = $this->sessions->gate();
        if (! $session || $session->stage()->order() < PricingStage::VehicleInfo->order()) {
            return redirect()->route('pricing.workflow.index')->with('warning', 'Vehicle Info opens after Detect has finished.');
        }
        if ($session->isPublished()) {
            return redirect()->route('pricing.workflow.vehicle-info-form')->with('warning', 'Prices are already published — Vehicle Info can no longer change in this process.');
        }
        if (($session->progress['state'] ?? null) === 'running') {
            return redirect()->route('pricing.workflow.vehicle-info-form')->with('warning', 'A step is still running — wait for it to finish.');
        }

        $path = $this->sessions->storeUpload($session, $request->file('file'), 'vehicle-info');
        // a Vehicle Info change after a later step sends the process back to this step (forward-only otherwise)
        $this->sessions->advance($session, PricingStage::VehicleInfo, [], backpack_user()->id);
        $this->sessions->progress($session, ['step' => 'vehicle_info', 'state' => 'running', 'message' => 'Queued — waiting for the queue worker…', 'error' => null]);
        ImportVehicleInfoJob::dispatch($session->id, $path);

        return redirect()->route('pricing.workflow.vehicle-info-form')->with('success', 'Vehicle Info import started.');
    }

    /** This round's row issues (incomplete + rejected) as a workbook. */
    public function issues(int $sessionId): BinaryFileResponse
    {
        if (! backpack_user()->can('PRC_WKFL_VIEW')) {
            abort(403, 'You do not have permission to view the pricing process.');
        }
        $session = ImportSession::findOrFail($sessionId);
        $issues = app(PricingIssueStore::class)->all($session, 'vehicle_info');

        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('Issues');
        $sheet->fromArray([['Row', 'Model Code', 'Result', 'Reason']]);
        $sheet->fromArray(array_map(fn (array $i) => [$i['row'], $i['code'], ucfirst($i['result']), $i['reason']], $issues), null, 'A2', true);
        $sheet->getStyle('A1:D1')->getFont()->setBold(true);
        $relative = "pricing/{$session->id}/vehicle-info-issues.xlsx";
        Storage::disk('local')->makeDirectory("pricing/{$session->id}");
        (new Xlsx($book))->save(Storage::disk('local')->path($relative));
        $book->disconnectWorksheets();

        return response()->download(Storage::disk('local')->path($relative), "Vehicle_Info_Issues_{$session->id}.xlsx");
    }

    public function continue(): RedirectResponse
    {
        if (! backpack_user()->can('PRC_WKFL_MANAGE')) {
            abort(403, 'You do not have permission to run the pricing process.');
        }
        $session = $this->sessions->gate();
        if (! $session || $session->stage() !== PricingStage::VehicleInfo) {
            return redirect()->route('pricing.workflow.index')->with('warning', 'The process is not at the Vehicle Info step.');
        }
        if (($session->progress['state'] ?? null) === 'running') {
            return redirect()->route('pricing.workflow.vehicle-info-form')->with('warning', 'A step is still running — wait for it to finish.');
        }
        $this->sessions->advance($session, PricingStage::Prices, [], backpack_user()->id);

        return redirect()->route('pricing.workflow.prices-form')->with('success', 'Vehicle Info done — import the prices next. Incomplete vehicles are skipped.');
    }
}
