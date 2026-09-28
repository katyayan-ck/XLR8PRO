<?php

namespace App\Http\Controllers\Admin\Pricing\Process;

use App\Http\Controllers\Controller;
use App\Models\Vehicle\Pricing\ImportSession;
use App\Services\Vehicle\Pricing\PricingHoldService;
use App\Services\Vehicle\Pricing\Session\PricingImpactService;
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
 * Pricing process — step 7, impact summary, and step 8, hold check (DEC-073 / DEC-079). The summary is always shown
 * before any calculation; the hold check holds or reopens lists (recorded, so Discard undoes it) before Calculate & Publish.
 */
class ImpactController extends Controller
{
    public function __construct(
        private readonly PricingSessionService $sessions,
        private readonly PricingImpactService $impact,
        private readonly PricingHoldService $holds,
    ) {}

    public function show(int $sessionId): View|RedirectResponse
    {
        if (! backpack_user()->can('PRC_WKFL_VIEW')) {
            abort(403, 'You do not have permission to view the pricing process.');
        }
        $session = ImportSession::findOrFail($sessionId);
        if ($session->stage()->order() < PricingStage::Impact->order()) {
            return redirect()->route('pricing.workflow.index')->with('warning', 'The impact summary opens after insurance & RTO.');
        }

        return view('admin.pricing.process.impact', [
            'title' => 'Impact Summary',
            'session' => $session,
            'summary' => $this->impact->summary($session),
            'holdLists' => PricingHoldService::LISTS,
            'canManage' => backpack_user()->can('PRC_WKFL_MANAGE') && ! $session->isTerminal() && ! $session->isPublished(),
        ]);
    }

    /** The incomplete vehicles the calculation skips, with their missing fields. */
    public function incomplete(int $sessionId): BinaryFileResponse
    {
        if (! backpack_user()->can('PRC_WKFL_VIEW')) {
            abort(403, 'You do not have permission to view the pricing process.');
        }
        $session = ImportSession::findOrFail($sessionId);
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('Incomplete');
        $sheet->fromArray(array_merge([['Model Code', 'OEM Variant', 'Segment', 'Missing Fields']], $this->impact->incomplete()), null, 'A1', true);
        $sheet->getStyle('A1:D1')->getFont()->setBold(true);
        $relative = "pricing/{$session->id}/incomplete-vehicles.xlsx";
        Storage::disk('local')->makeDirectory("pricing/{$session->id}");
        (new Xlsx($book))->save(Storage::disk('local')->path($relative));
        $book->disconnectWorksheets();

        return response()->download(Storage::disk('local')->path($relative), "Incomplete_Vehicles_{$session->id}.xlsx");
    }

    /** Step 7 → 8: the summary was reviewed. */
    public function continue(): RedirectResponse
    {
        if (! backpack_user()->can('PRC_WKFL_MANAGE')) {
            abort(403, 'You do not have permission to run the pricing process.');
        }
        $session = $this->sessions->gate();
        if (! $session || $session->stage() !== PricingStage::Impact) {
            return redirect()->route('pricing.workflow.index')->with('warning', 'The process is not at the impact summary.');
        }
        $this->sessions->advance($session, PricingStage::HoldCheck, ['impact' => ['reviewed_at' => now()->toIso8601String(), 'reviewed_by' => backpack_user()->id]], backpack_user()->id);

        return redirect()->route('pricing.workflow.impact-summary-view', $session->id)->with('success', 'Impact reviewed — hold or reopen lists, then calculate.');
    }

    /** Step 8: hold or reopen lists (recorded in the session, so Discard undoes it). */
    public function hold(Request $request): RedirectResponse
    {
        if (! backpack_user()->can('PRC_WKFL_MANAGE')) {
            abort(403, 'You do not have permission to run the pricing process.');
        }
        $data = $request->validate([
            'action' => ['required', Rule::in(['hold', 'reopen'])],
            'hold_lists' => ['required', 'array', 'min:1'],
            'hold_lists.*' => ['string', Rule::in(array_keys(PricingHoldService::LISTS))],
        ], [], __('pricing.fields'));
        $session = $this->sessions->gate();
        if (! $session || ! in_array($session->stage(), [PricingStage::Impact, PricingStage::HoldCheck], true)) {
            return redirect()->route('pricing.workflow.index')->with('warning', 'Holds are set at the hold check.');
        }
        $userId = backpack_user()->id;
        $this->sessions->record($session, fn () => $data['action'] === 'hold'
            ? $this->holds->hold($data['hold_lists'], $session, null, $userId)
            : $this->holds->reopen($data['hold_lists'], "Reopened in pricing process #{$session->id}", $userId));

        return redirect()->route('pricing.workflow.impact-summary-view', $session->id)
            ->with('success', ($data['action'] === 'hold' ? 'On hold: ' : 'Reopened: ').implode(', ', $data['hold_lists']).'.');
    }
}
