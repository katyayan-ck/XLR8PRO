<?php

namespace App\Http\Controllers\Admin;

use App\Models\CRM\Enquiry;
use App\Models\CRM\Lead;
use App\Models\CRM\LeadSource;
use App\Services\OrgService;
use App\Jobs\ImportEnquiriesJob;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use App\Models\CRM\Campaign;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Prologue\Alerts\Facades\Alert;
use Illuminate\Support\Facades\Log;
use App\Models\Module\Booking\XlFinancier;
use Throwable;

class EnquiryCrudController extends CrudController
{
    use CreateOperation, DeleteOperation, ListOperation, UpdateOperation;

    public function setup()
    {
        CRUD::setModel(Enquiry::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/enquiries');
        CRUD::setEntityNameStrings('enquiry', 'enquiries');
    }

    protected function setupListOperation()
    {
        $this->crud->setListView('admin.enquiry.list');
    }

    public function index()
    {
        $this->crud->setListView('admin.enquiry.list');

        $filters = [
            'missed_fup',
            'today_fup',
            'birthday',
            'anniversary',
            'exchange',
            'pending_eval',
            'delayed',
            'wrong_assign',
            'finance',
            'stage_mismatch',
            'lost_verif'
        ];

        $highlightCounts = [];
        foreach ($filters as $filter) {
            $query = \App\Models\CRM\Enquiry::query();
            \App\Services\OrgService::applyHighlightFilter($query, $filter);
            $highlightCounts[$filter] = $query->count();
        }

        return view('admin.enquiry.list', [
            'title' => 'Xlr8 Enquiries',
            'gridConfig' => [
                'columns' => $this->getColumns('all'),
                'data' => []
            ],
            'highlightCounts' => $highlightCounts
        ]);
    }

    public function data(Request $request)
    {
        $startRow = max(0, (int) $request->input('startRow', 0));
        $limit = max(1, (int) $request->input('endRow', $startRow + 100)) - $startRow;

        $searchText = trim((string) $request->input('searchText', ''));
        $highlightFilter = trim((string) $request->input('highlightFilter', ''));
        $filterModel = (array) $request->input('filterModel', []);
        $listType = trim((string) $request->input('list_type', 'all'));

        $query = match ($listType) {
            'reference' => Enquiry::reference(),
            'virtual' => Enquiry::virtual(),
            'whatsapp' => Enquiry::whatsapp(),
            'hyperlocal' => Enquiry::hyperlocal(),
            'assigned_long' => Enquiry::assignedLong(),
            'unassigned_long' => Enquiry::unassignedLong(),
            'assigned_quick' => Enquiry::assignedQuick(),
            'unassigned_quick' => Enquiry::unassignedQuick(),
            'exchange' => Enquiry::where('purchase_type', 'Exchange Buy'),
            'scrappage' => Enquiry::where('purchase_type', 'Scrappage'),
            'exchange_not_interested' => Enquiry::whereIn('purchase_type', ['First Time Buy', 'Additional Buy', 'No Consideration']),
            'finance' => Enquiry::where('fin_mode', 'In-house'),
            'finance_not_interested' => Enquiry::whereIn('fin_mode', ['Cash', 'Customer Self', 'Yet To Decide', 'Purchase Plan Cancelled']),
            default => Enquiry::query(),
        };

        $query->with(['segment', 'model', 'variant', 'color', 'campaign']);

        $this->applyEnquirySearch($query, $searchText);
        $this->applyEnquirySort($query, (array) $request->input('sortModel', []));
        $this->applyEnquiryFilter($query, $filterModel);
        OrgService::applyHighlightFilter($query, $highlightFilter);

        $total = (clone $query)->count();

        $mapType = in_array($listType, ['assigned_long', 'unassigned_long']) ? 'long' : (in_array($listType, ['assigned_quick', 'unassigned_quick']) ? 'quick' : (in_array($listType, ['reference', 'virtual', 'whatsapp', 'exchange', 'scrappage', 'exchange_not_interested', 'finance', 'finance_not_interested']) ? $listType : 'all'));

        $lpMap = collect(OrgService::keywordValueByCode('LIKELY_PURCHASE_DATE'))
            ->pluck('value', 'code')
            ->toArray();

        $fuelMap = collect(OrgService::getKeyValuesByCode('FUEL_TYPE'))
            ->pluck('value', 'id')
            ->toArray();

        $finMap = collect(\App\Models\Module\Booking\XlFinancier::select('id', 'name')->get())
            ->pluck('name', 'id')
            ->toArray();



        $scUsers = OrgService::getUsers(
            'ALL',
            'ALL',
            'ALL',
            'ALL',
            'ALL',
            'ALL',
            'ALL',
            'ALL',
            'ALL',
            'ALL',
            null,
            true
        );

        $scByCode = collect($scUsers)
            ->filter(
                fn($user) =>
                !empty($user['employee_code']) ||
                    !empty($user['person_code'])
            )
            ->flatMap(function ($user) {
                $result = [];

                if (!empty($user['employee_code'])) {
                    $result[$user['employee_code']] = $user;
                }

                if (!empty($user['person_code'])) {
                    $result[$user['person_code']] = $user;
                }

                return $result;
            });

        $scByMileId = collect($scUsers)
            ->filter(fn($user) => !empty($user['mile_id']))
            ->keyBy('mile_id');

        $gridData = $query->skip($startRow)->take($limit)->get()
            ->map(fn($e, $i) => $this->mapData(
                $e,
                $startRow + $i,
                $mapType,
                $lpMap,
                $fuelMap,
                $finMap,
                $scByCode,
                $scByMileId
            ))
            ->all();

        return response()->json(['rows' => $gridData, 'lastRow' => $total]);
    }

    public function export(Request $request)
    {
        $searchText = trim((string) $request->input('searchText', ''));
        $highlightFilter = trim((string) $request->input('highlightFilter', ''));
        $filterModel = json_decode((string) $request->input('filterModel', '{}'), true) ?: [];
        $listType = trim((string) $request->input('list_type', 'all'));

        $query = match ($listType) {
            'reference' => Enquiry::reference(),
            'virtual' => Enquiry::virtual(),
            'whatsapp' => Enquiry::whatsapp(),
            'hyperlocal' => Enquiry::hyperlocal(),
            'assigned_long' => Enquiry::assignedLong(),
            'unassigned_long' => Enquiry::unassignedLong(),
            'assigned_quick' => Enquiry::assignedQuick(),
            'unassigned_quick' => Enquiry::unassignedQuick(),
            'exchange' => Enquiry::where('purchase_type', 'Exchange Buy'),
            'scrappage' => Enquiry::where('purchase_type', 'Scrappage'),
            'exchange_not_interested' => Enquiry::whereIn('purchase_type', ['First Time Buy', 'Additional Buy', 'No Consideration']),
            'finance' => Enquiry::where('fin_mode', 'In-house'),
            'finance_not_interested' => Enquiry::whereIn('fin_mode', ['Cash', 'Customer Self', 'Yet To Decide', 'Purchase Plan Cancelled']),
            default => Enquiry::query(),
        };

        $query->with(['segment', 'model', 'variant', 'color', 'campaign']);

        $this->applyEnquirySearch($query, $searchText);
        $this->applyEnquiryFilter($query, $filterModel);
        OrgService::applyHighlightFilter($query, $highlightFilter);

        $query->orderByDesc('created_at');

        $mapType = in_array($listType, ['assigned_long', 'unassigned_long']) ? 'long' : (in_array($listType, ['assigned_quick', 'unassigned_quick']) ? 'quick' : (in_array($listType, ['reference', 'virtual', 'whatsapp', 'exchange', 'scrappage', 'exchange_not_interested', 'finance', 'finance_not_interested']) ? $listType : 'all'));

        $columns = array_values(array_filter(
            $this->getColumns($mapType),
            fn($col) => ($col['field'] ?? null) !== 'action'
        ));

        $lpMap = collect(OrgService::keywordValueByCode('LIKELY_PURCHASE_DATE'))->pluck('value', 'code')->toArray();
        $fuelMap = collect(OrgService::getKeyValuesByCode('FUEL_TYPE'))->pluck('value', 'id')->toArray();
        $finMap = collect(\App\Models\Module\Booking\XlFinancier::select('id', 'name')->get())->pluck('name', 'id')->toArray();
        $scUsers = OrgService::getUsers(
            'ALL',
            'ALL',
            'ALL',
            'ALL',
            'ALL',
            'ALL',
            'ALL',
            'ALL',
            'ALL',
            'ALL',
            null,
            true
        );

        $scByCode = collect($scUsers)
            ->filter(
                fn($user) =>
                !empty($user['employee_code']) ||
                    !empty($user['person_code'])
            )
            ->flatMap(function ($user) {
                $result = [];

                if (!empty($user['employee_code'])) {
                    $result[$user['employee_code']] = $user;
                }

                if (!empty($user['person_code'])) {
                    $result[$user['person_code']] = $user;
                }

                return $result;
            });

        $scByMileId = collect($scUsers)
            ->filter(fn($user) => !empty($user['mile_id']))
            ->keyBy('mile_id');
        return response()->streamDownload(function () use (
            $query,
            $columns,
            $mapType,
            $lpMap,
            $fuelMap,
            $finMap,
            $scByCode,
            $scByMileId
        ) {
            $out = fopen('php://output', 'w');

            fputcsv($out, array_merge(['S.No.'], array_map(fn($c) => $c['headerName'], $columns)));

            $serial = 0;
            $query->chunk(500, function ($chunk) use (
                $out,
                &$serial,
                $columns,
                $mapType,
                $lpMap,
                $fuelMap,
                $finMap,
                $scByCode,
                $scByMileId
            ) {
                foreach ($chunk as $e) {
                    $rowData = $this->mapData(
                        $e,
                        $serial,
                        $mapType,
                        $lpMap,
                        $fuelMap,
                        $finMap,
                        $scByCode,
                        $scByMileId
                    );
                    $serial++;
                    fputcsv($out, array_merge(
                        [$serial],
                        array_map(fn($c) => $rowData[$c['field']] ?? '', $columns)
                    ));
                }
            });

            fclose($out);
        }, 'enquiries-' . now()->format('Y-m-d_His') . '.csv', ['Content-Type' => 'text/csv']);
    }

    public function referenceList()
    {
        return $this->buildGrid(Enquiry::reference(), 'admin.enquiry.reference-enquiry', 'Reference Enquiries', 'reference');
    }
    public function virtualNumberList()
    {
        return $this->buildGrid(Enquiry::virtual(), 'admin.enquiry.virtual-number-enquiry', 'Virtual Number Enquiries', 'virtual');
    }

    /**
     * Display Hyperlocal Enquiries
     * 
     * @return \Illuminate\View\View
     */
    public function hyperlocalList()
    {
        $this->crud->hasAccessOrFail('list');

        $query = Enquiry::hyperlocal();

        // Get counts for the badge
        $count = (clone $query)->count();

        return $this->buildGrid(
            $query,
            'admin.enquiry.hyperlocal-enquiry',
            'Hyperlocal Enquiries (' . $count . ')',
            'hyperlocal'
        );
    }

    public function whatsappCampaignList()
    {
        return $this->buildGrid(Enquiry::whatsapp(), 'admin.enquiry.whatsapp-campaign-enquiry', 'WhatsApp Campaign Enquiries', 'whatsapp');
    }
    public function assignedLongList()
    {
        return $this->buildGrid(Enquiry::assignedLong(), 'admin.enquiry.assigned-long-enquiry', 'Assigned Long Enquiries', 'long');
    }
    public function unassignedLongList()
    {
        return $this->buildGrid(Enquiry::unassignedLong(), 'admin.enquiry.unassigned-long-enquiry', 'Unassigned Long Enquiries', 'long');
    }
    public function assignedQuickList()
    {
        return $this->buildGrid(Enquiry::assignedQuick(), 'admin.enquiry.assigned-quick-enquiry', 'Assigned Quick Enquiries', 'quick');
    }
    public function unassignedQuickList()
    {
        return $this->buildGrid(Enquiry::unassignedQuick(), 'admin.enquiry.unassigned-quick-enquiry', 'Unassigned Quick Enquiries', 'quick');
    }

    public function exchangeEnquiryList()
    {
        return $this->buildGrid(Enquiry::where('purchase_type', 'Exchange Buy'), 'admin.enquiry.exchange', 'Int in Exchange Dashboard', 'exchange');
    }
    public function scrappageEnquiryList()
    {
        return $this->buildGrid(Enquiry::where('purchase_type', 'Scrappage'), 'admin.enquiry.exchange', 'Int in Scrappage Dashboard', 'scrappage');
    }
    public function exchangeNotInterestedList()
    {
        return $this->buildGrid(Enquiry::whereIn('purchase_type', ['First Time Buy', 'Additional Buy', 'No Consideration']), 'admin.enquiry.exchange', 'Not Interested in Exchange Dashboard', 'exchange_not_interested');
    }

    public function financeEnquiryList()
    {
        return $this->buildGrid(Enquiry::where('fin_mode', 'In-house'), 'admin.enquiry.finance-list', 'Enquiries - Int in Finance', 'finance');
    }
    public function financeNotInterestedList()
    {
        return $this->buildGrid(Enquiry::whereIn('fin_mode', ['Cash', 'Customer Self', 'Yet To Decide', 'Purchase Plan Cancelled']), 'admin.enquiry.finance-list', 'Enquiries - Finance Not Interested', 'finance_not_interested');
    }

    private function buildGrid($query, $view, $title, $type)
    {
        $this->crud->setListView($view);

        $lpMap = collect(OrgService::keywordValueByCode('LIKELY_PURCHASE_DATE'))
            ->pluck('value', 'code')
            ->toArray();

        $fuelMap = collect(OrgService::getKeyValuesByCode('FUEL_TYPE'))
            ->pluck('value', 'id')
            ->toArray();

        $finMap = collect(
            \App\Models\Module\Booking\XlFinancier::select('id', 'name')->get()
        )->pluck('name', 'id')->toArray();

        // Get Sales Consultant users from OrgService
        $scUsers = OrgService::getUsers(
            'ALL',
            'ALL',
            'ALL',
            'ALL',
            'ALL',
            'ALL',
            'ALL',
            'ALL',
            'ALL',
            'ALL',
            null,
            true
        );

        // Lookup by SC code
        $scByCode = collect($scUsers)
            ->filter(
                fn($user) =>
                !empty($user['employee_code']) ||
                    !empty($user['person_code'])
            )
            ->flatMap(function ($user) {
                $result = [];

                if (!empty($user['employee_code'])) {
                    $result[$user['employee_code']] = $user;
                }

                if (!empty($user['person_code'])) {
                    $result[$user['person_code']] = $user;
                }

                return $result;
            });

        // Lookup by Mile ID
        $scByMileId = collect($scUsers)
            ->filter(fn($user) => !empty($user['mile_id']))
            ->keyBy('mile_id');

        $enquiries = $query->with(['segment', 'model', 'variant', 'color', 'campaign'])
            ->orderByDesc('created_at')
            ->limit(500)
            ->get();

        return view($view, [
            'title' => $title,
            'segments' => OrgService::segments(),
            'gridConfig' => [
                'columns' => $this->getColumns($type),

                'data' => $enquiries->map(fn($e, $i) => $this->mapData(
                    $e,
                    $i,
                    $type,
                    $lpMap,
                    $fuelMap,
                    $finMap,
                    $scByCode,
                    $scByMileId
                ))->values()
            ]
        ]);
    }

    private function getAssignedSc($code, $mileId, $scByCode, $scByMileId): ?array
    {
        $assignedSc = null;

        if (!empty($code) && $scByCode) {
            $assignedSc = $scByCode->get($code);
        }

        if (!$assignedSc && !empty($mileId) && $scByMileId) {
            $assignedSc = $scByMileId->get($mileId);
        }

        return $assignedSc;
    }

    private function mapData(
        $e,
        $i,
        $type,
        $lpMap = [],
        $fuelMap = [],
        $finMap = [],
        $scByCode = null,
        $scByMileId = null
    ) {
        $x8AssignedSc = $this->getAssignedSc(
            $e->x8_sc_code ?? null,
            $e->x8_sc_mile_id ?? null,
            $scByCode,
            $scByMileId
        );

        $oemAssignedSc = $this->getAssignedSc(
            $e->sc_code ?? null,
            $e->sc_mile_id ?? null,
            $scByCode,
            $scByMileId
        );
        $c = function ($d, $f) {
            try {
                return (!empty(trim((string) $d)) && !str_starts_with((string) $d, '0000'))
                    ? Carbon::parse($d)->format($f)
                    : '—';
            } catch (\Throwable $th) {
                return '—';
            }
        };

        $editUrl = backpack_url("enquiry/{$e->id}/edit");
        $quotUrl = backpack_url("quotation-form/create?id={$e->id}");
        $bookUrl = backpack_url("booking/create?enquiry_id={$e->id}");

        // ============================================================
        // HYPERLOCAL SPECIFIC MAPPING - RETURN EARLY
        // ============================================================
        if ($type === 'hyperlocal') {
            $actionBtns = '
            <a href="' . $editUrl . '" class="btn btn-sm btn-primary">Edit</a>
            <a href="' . $quotUrl . '" class="btn btn-sm btn-success">Quote</a>
            <a href="' . $bookUrl . '" class="btn btn-sm btn-warning" title="Convert to Booking">Process</a>
        ';

            return [
                'serial_no' => $i + 1,
                'lead_id' => $e->lead_id ?? $e->id ?? '—',
                'name' => trim(($e->first_name ?? '') . ' ' . ($e->last_name ?? '')) ?: ($e->name ?? '—'),
                'email' => $e->email ?? '—',
                'phone_number' => $e->mobile ?? $e->phone_number ?? '—',
                'call_start_time' => $c($e->call_start_time ?? $e->created_at, 'd-M-Y H:i:s'),
                'call_end_time' => $c($e->call_end_time, 'd-M-Y H:i:s'),
                'call_recording_url' => $e->call_recording_url ?? '—',
                'call_duration' => $e->call_duration ?? $e->call_duration_in_seconds ?? '—',
                'call_status' => $e->call_status ?? '—',
                'call_type' => $e->call_type ?? '—',
                'notes' => $e->notes ?? $e->remarks ?? '—',
                'lead_status' => $e->lead_status ?? $e->status ?? '—',
                'multi_visit' => $e->multi_visit ?? '—',
                'lead_intent' => $e->lead_intent ?? '—',
                'lead_type' => $e->lead_type ?? '—',
                'lead_for' => $e->lead_for ?? '—',
                'medium' => $e->medium ?? '—',
                'source' => $e->source_code ?? $e->source_name ?? '—',
                'client_crm_status' => $e->client_crm_status ?? '—',
                'client_crm_message' => $e->client_crm_message ?? '—',
                'enquiry_status' => $e->enquiry_status ?? $e->status ?? '—',
                'enquiry_date' => $c($e->enquiry_date ?? $e->created_at, 'd-M-Y'),
                'model' => $e->model_name ?? $e->model ?? '—',
                'dealer_code' => $e->dealer_code ?? $e->dealer_branch ?? '—',
                'dms_enquiry_stage' => $e->stage ?? '—',
                'cre_enquiry_stage' => '—',
                'cre_next_fup_date' => '—',
                'cre_next_fup_time' => '—',
                'cre_next_fup_remarks' => '—',
                'x8_quotation_no' => $e->x8_quotation_no ?? $e->quotation_no ?? '—',
                'x8_booking_no' => $e->x8_booking_no ?? $e->booking_no ?? '—',
                'x8_booking_date' => $c($e->x8_booking_date ?? $e->booking_date, 'd-M-Y'),
                'oem_booking_no' => $e->oem_booking_no ?? '—',
                'oem_booking_date' => $c($e->oem_booking_date, 'd-M-Y'),
                'oem_otf_no' => $e->oem_otf_no ?? '—',
                'oem_test_drive_no' => $e->oem_test_drive_no ?? $e->test_drive_no ?? '—',
                'territory' => $e->territory ?? '—',
                'fup_count' => $e->fup_count ?? '—',
                'td_date' => $c($e->td_date, 'd-M-Y'),
                'lost_reason' => $e->lost_reason ?? '—',
                'followup_status' => $e->fup_status ?? '—',
                'action' => '<div class="d-flex justify-content-center gap-2">' . $actionBtns . '</div>',
            ];
        }

        // ============================================================
        // ACTION BUTTONS FOR OTHER TYPES
        // ============================================================
        $actionBtns = '<a href="' . $editUrl . '" class="btn btn-sm btn-primary">Edit</a>';

        if ($type === 'all') {
            $actionBtns .= '<a href="' . $quotUrl . '" class="btn btn-success btn-sm">Quote</a>';
            $actionBtns .= '<a href="' . $bookUrl . '" class="btn btn-warning btn-sm" title="Convert to Booking">Process</a>';
        }

        if (in_array($type, ['exchange', 'scrappage', 'exchange_not_interested'])) {
            $exchUrl = backpack_url("exchange/enquiry/{$e->id}/edit");
            $actionBtns = '<a href="' . $exchUrl . '" class="btn btn-sm btn-primary">Process</a>';
        }

        if (in_array($type, ['finance', 'finance_not_interested'])) {
            $finUrl = backpack_url("finance/enquiry/{$e->id}/edit");
            $actionBtns = '<a href="' . $finUrl . '" class="btn btn-sm btn-primary">Process</a>';
        }

        if (in_array($type, ['all', 'reference', 'virtual', 'whatsapp', 'long', 'quick'])) {
            $actionBtns = '<a href="' . $editUrl . '" class="btn btn-sm btn-primary">Edit</a>';
            $actionBtns .= '<a href="' . $quotUrl . '" class="btn btn-success btn-sm">Quote</a>';
            $actionBtns .= '<a href="' . $bookUrl . '" class="btn btn-warning btn-sm" title="Convert to Booking">Process</a>';
        }

        // ============================================================
        // BASE ROW FOR OTHER TYPES
        // ============================================================
        $row = [
            'serial_no' => $i + 1,
            'x8_enquiry_no' => 'XENQ-' . $e->id,
            'x8_enquiry_date' => $c($e->created_at, 'd-M-Y H:i'),
            'x8_enquiry_assign_date' => $c($e->x8_enquiry_assign_date ?? $e->enq_assign_date, 'd-M-Y'),
            'oem_enquiry_no' => $e->x8_enquiry_no ?? $e->enquiry_no ?? $e->oem_enquiry_no ?? '—',
            'oem_enquiry_date' => $c($e->x8_enquiry_date ?? $e->enquiry_date ?? $e->oem_enquiry_date, 'd-M-Y'),
            'oem_enquiry_assign_date' => $c($e->oem_enquiry_assign_date ?? $e->enq_assign_date, 'd-M-Y'),
            'segment_name' => $e->segment_code ? ($e->getRelation('segment')?->name ?? $e->segment ?? $e->segment_code) : ($e->segment ?? '—'),
            'model_name' => $e->model_code ? ($e->getRelation('model')?->name ?? $e->model ?? $e->model_code) : ($e->model ?? '—'),
            'variant_name' => $e->variant_code ? ($e->getRelation('variant')?->display_name ?? $e->getRelation('variant')?->custom_name ?? $e->getRelation('variant')?->oem_name ?? $e->variant ?? $e->variant_code) : ($e->variant ?? '—'),
            'color_name' => $e->color_code ? ($e->getRelation('color')?->name ?? $e->color ?? $e->color_code) : ($e->color ?? '—'),
            'mobile' => $e->mobile ?? '—',
            'dms_enquiry_stage' => $e->stage ?? '—',
            'cre_enquiry_stage' => '—',
            'cre_next_fup_date' => '—',
            'cre_next_fup_time' => '—',
            'cre_next_fup_remarks' => '—',
            'x8_quotation_no' => $e->x8_quotation_no ?? $e->quotation_no ?? '—',
            'x8_booking_no' => $e->x8_booking_no ?? $e->booking_no ?? '—',
            'x8_booking_date' => $c($e->x8_booking_date ?? $e->booking_date, 'd-M-Y'),
            'oem_booking_no' => $e->oem_booking_no ?? '—',
            'oem_booking_date' => $c($e->oem_booking_date, 'd-M-Y'),
            'oem_otf_no' => $e->oem_otf_no ?? '—',
            'oem_test_drive_no' => $e->oem_test_drive_no ?? $e->test_drive_no ?? '—',
            'territory' => $e->territory ?? '—',
            'fup_count' => $e->fup_count ?? '—',
            'td_date' => $c($e->td_date, 'd-M-Y'),
            'lost_reason' => $e->lost_reason ?? '—',
            'followup_status' => $e->fup_status ?? '—',
            'action' => '<div class="d-flex justify-content-center gap-2">' . $actionBtns . '</div>',
        ];

        // ============================================================
        // TYPE-SPECIFIC ADDITIONS
        // ============================================================
        if ($type === 'reference') {
            $row['referee_name'] = $e->referee_name ?? '—';
            $row['referee_phone'] = $e->referee_phone ?? '—';
            $row['first_name'] = trim(($e->first_name ?? '') . ' ' . ($e->last_name ?? '')) ?: '—';
        } elseif ($type === 'virtual') {
            $row['virtual_no'] = $e->virtual_no ?? '—';
            $row['call_date_and_time'] = $c($e->virtual_call_date, 'd-M-Y H:i');
            $row['call_date'] = $c($e->virtual_call_date, 'd-M-Y');
            $row['call_nature'] = $e->call_nature ?? '—';
            $row['remarks'] = $e->remarks ?? '—';
        } elseif ($type === 'whatsapp') {
            $row['campaign_name'] = $e->wapp_campaign_name ?? '—';
            $row['campaign_date'] = $c($e->wapp_campaign_date, 'd-M-Y');
        }

        if (in_array($type, ['long', 'quick', 'all', 'exchange', 'scrappage', 'exchange_not_interested', 'finance', 'finance_not_interested'])) {
            $row += [
                'oem_long_enquiry_no' => $e->oem_long_enquiry_no ?? '—',
                'oem_long_enquiry_date' => $c($e->oem_long_enquiry_date, 'd-M-Y'),
                'oem_long_enquiry_assign_date' => $c($e->oem_long_enquiry_assign_date, 'd-M-Y'),
                'oem_quick_enquiry_no' => $e->oem_quick_enquiry_no ?? $e->quick_enquiry_no ?? '—',
                'oem_quick_enquiry_date' => $c($e->oem_quick_enquiry_date ?? $e->quick_enquiry_date, 'd-M-Y'),
                'oem_quick_enquiry_status' => $e->oem_quick_enquiry_status ?? $e->quick_status ?? '—',
                'oem_quick_enquiry_assign_date' => $c($e->oem_quick_enquiry_assign_date ?? $e->quick_enq_assign_date, 'd-M-Y'),
                'first_name' => $e->first_name ?? '—',
                'last_name' => $e->last_name ?? '—',
                'full_name' => $e->full_name ?? trim(($e->first_name ?? '') . ' ' . ($e->last_name ?? '')),
                'email' => $e->email ?? '—',
                'gender' => $e->gender ?? '—',
                'enquiry_type' => $e->enquiry_type ?? '—',
                'source_name' => $e->source?->name ?? $e->source_code ?? '—',
                'sub_source' => $e->sub_source ?? '—',
                'likely_purchase_in_days' => $lpMap[$e->likely_purchase_date] ?? $e->likely_purchase_date ?? '—',
                'fuel_type' => $fuelMap[$e->fuel_type] ?? $e->fuel_type ?? '—',
                'transmission' => $e->transmission ?? '—',
                'drivetrain' => $e->drivetrain ?? '—',
                'seating' => $e->seating ?? '—',
                'tehsil' => $e->tehsil ?? '—',
                'district' => $e->district ?? '—',
                'city' => $e->city ?? '—',
                'x8_sc_code' => $e->x8_sc_code ?? '—',

                'x8_sc_mile_id' => $e->x8_sc_mile_id
                    ?? $x8AssignedSc['mile_id']
                    ?? '—',

                'x8_sc_branch' => !empty($x8AssignedSc['primary_branch_code'])
                    ? OrgService::branchName($x8AssignedSc['primary_branch_code'])
                    : '—',

                'x8_sc_location' => !empty($x8AssignedSc['primary_loc_code'])
                    ? OrgService::locationName($x8AssignedSc['primary_loc_code'])
                    : '—',

                'sc_code' => $e->sc_code
                    ? OrgService::getUserNameByCode($e->sc_code, null, $e->sc_code)
                    : '—',

                'sc_mile_id' => $e->sc_mile_id
                    ?? $oemAssignedSc['mile_id']
                    ?? '—',

                'oem_sc_branch' => !empty($oemAssignedSc['primary_branch_code'])
                    ? OrgService::branchName($oemAssignedSc['primary_branch_code'])
                    : '—',

                'oem_sc_location' => !empty($oemAssignedSc['primary_loc_code'])
                    ? OrgService::locationName($oemAssignedSc['primary_loc_code'])
                    : '—',
                'dealer_branch' => $e->dealer_branch ?? '—',
                'dealer_location' => $e->dealer_location ?? '—',
                'followup_type' => $e->followup_type ? (OrgService::getKeyValueByCode($e->followup_type)?->value ?? $e->followup_type) : '—',
                'followup_date' => $c($e->followup_date, 'd-M-Y'),
                'followup_time' => $e->followup_time ?? '—',
                'occupation_type' => $e->occupation_type ?? '—',
                'customer_type' => $e->customer_type ?? '—',
                'occupation_sub_type' => $e->occupation_sub_type ?? '—',
                'company_name' => $e->company_name ?? '—',
                'dob' => $c($e->dob, 'd-M-Y'),
                'marital_status' => $e->marital_status ?? '—',
                'marriage_date' => $c($e->marriage_date, 'd-M-Y'),
                'age_group' => $e->age_group ?? '—',
                'usage_area' => $e->usage_area ?? '—',
                'km_travelled_daily' => $e->km_travelled_daily ?? '—',
                'application_type' => $e->application_type ?? '—',
                'application' => $e->application ?? '—',
                'pincode' => $e->pincode ?? $e->zipcode ?? '—',
                'address' => $e->address ?? $e->customer_address ?? '—',
                'has_ev' => $e->has_ev ?? '—',
                'purchase_type' => $e->purchase_type ?? '—',
                'remarks' => $e->remarks ?? '—',
                'consider_make' => $e->consid_brand ?? $e->consider_make ?? '—',
                'consider_model' => $e->consid_model ?? $e->consider_model ?? '—',
                'consider_variant' => $e->consid_variant ?? $e->consider_variant ?? '—',
                'expected_price' => $e->expected_price ?? '—',
                'offered_price' => $e->offered_price ?? '—',
                'exchange_bonus' => $e->exchange_bonus ?? '—',
                'price_gap' => ($e->expected_price ?? 0) - ($e->offered_price ?? 0) - ($e->exchange_bonus ?? 0),
                'fin_mode' => $e->fin_mode ?? '—',
                'financier_name' => $finMap[$e->financier] ?? $e->financier ?? '—',
                'loan_status' => $e->loan_status ?? '—',
            ];
        }

        return $row;
    }

    private function getColumns($type)
    {
        $commonEnd = [
            // ['field' => 'dms_enquiry_stage', 'headerName' => 'DMS Stage'],
            // ['field' => 'cre_enquiry_stage', 'headerName' => 'CRE Stage'],
            // ['field' => 'followup_status', 'headerName' => 'FOLLOW UP STATUS'],
            // ['field' => 'cre_next_fup_date', 'headerName' => 'Next FUP Date'],
            // ['field' => 'cre_next_fup_time', 'headerName' => 'Next FUP Time'],
            // ['field' => 'cre_next_fup_remarks', 'headerName' => 'FUP Remarks'],
            // ['field' => 'x8_quotation_no', 'headerName' => 'X8 Quotation No.'],
            // ['field' => 'x8_booking_no', 'headerName' => 'X8 Booking No.'],
            // ['field' => 'x8_booking_date', 'headerName' => 'X8 Booking Date'],
            // ['field' => 'oem_booking_no', 'headerName' => 'OEM Booking No.'],
            // ['field' => 'oem_booking_date', 'headerName' => 'OEM Booking Date'],
            // ['field' => 'oem_otf_no', 'headerName' => 'OEM OTF No.'],
            // ['field' => 'oem_test_drive_no', 'headerName' => 'OEM Test Drive No.'],
            
                'field'         => 'action',
                'headerName'    => 'Action',
                'width'         => 220,
                'minWidth'      => 220,
                'pinned'        => 'right',
                'sortable'      => false,
                'filter'        => false,
                'cellClass'     => 'text-center p-0'
            
        ];

        if ($type === 'reference')
            return array_merge([
                ['field' => 'serial_no', 'headerName' => 'S.No.'],
                ['field' => 'x8_enquiry_no', 'headerName' => 'X8 Enquiry No.'],
                ['field' => 'x8_enquiry_date', 'headerName' => 'X8 Enquiry Date'],
                ['field' => 'x8_enquiry_assign_date', 'headerName' => 'X8 Enquiry Assign Date'],
                ['field' => 'oem_enquiry_no', 'headerName' => 'OEM Enquiry No.'],
                ['field' => 'oem_enquiry_date', 'headerName' => 'OEM Enquiry Date'],
                ['field' => 'referee_name', 'headerName' => 'Referee Name'],
                ['field' => 'referee_phone', 'headerName' => 'Referee Mobile'],
                ['field' => 'first_name', 'headerName' => 'Customer Name'],
                ['field' => 'mobile', 'headerName' => 'Customer Mobile'],
                ['field' => 'segment_name', 'headerName' => 'Segment'],
                ['field' => 'model_name', 'headerName' => 'Model'],
                ['field' => 'variant_name', 'headerName' => 'Variant']
            ], $commonEnd);

        if ($type === 'virtual')
            return array_merge([
                ['field' => 'serial_no', 'headerName' => 'S.No.'],
                ['field' => 'x8_enquiry_no', 'headerName' => 'X8 Enquiry No.'],
                ['field' => 'x8_enquiry_date', 'headerName' => 'X8 Enquiry Date'],
                ['field' => 'x8_enquiry_assign_date', 'headerName' => 'X8 Enquiry Assign Date'],
                ['field' => 'oem_enquiry_no', 'headerName' => 'OEM Enquiry No.'],
                ['field' => 'oem_enquiry_date', 'headerName' => 'OEM Enquiry Date'],
                ['field' => 'virtual_no', 'headerName' => 'Virtual Number'],
                ['field' => 'call_date_and_time', 'headerName' => 'Call Date & Time'],
                ['field' => 'call_date', 'headerName' => 'Call Date'],
                ['field' => 'call_nature', 'headerName' => 'Call Nature'],
                ['field' => 'mobile', 'headerName' => 'Customer Mobile'],
                ['field' => 'remarks', 'headerName' => 'Remarks'],
            ], $commonEnd);

        if ($type === 'hyperlocal') {
            return array_merge([
                ['field' => 'serial_no', 'headerName' => 'S.No.', 'width' => 80, 'pinned' => 'left'],
                ['field' => 'lead_id', 'headerName' => 'Leads-ID', 'width' => 120],
                ['field' => 'name', 'headerName' => 'Name', 'width' => 180],
                ['field' => 'email', 'headerName' => 'Email', 'width' => 200],
                ['field' => 'phone_number', 'headerName' => 'Phone-Number', 'width' => 150],
                ['field' => 'call_start_time', 'headerName' => 'Call-Start-Time', 'width' => 180, 'type' => 'date'],
                ['field' => 'call_end_time', 'headerName' => 'Call-End-Time', 'width' => 180, 'type' => 'date'],
                ['field' => 'call_recording_url', 'headerName' => 'Call-Recording-URL', 'width' => 250],
                ['field' => 'call_duration', 'headerName' => 'Call-Duration-in-Seconds', 'width' => 180, 'type' => 'number'],
                ['field' => 'call_status', 'headerName' => 'Call-Status', 'width' => 150],
                ['field' => 'call_type', 'headerName' => 'Call-Type', 'width' => 150],
                ['field' => 'notes', 'headerName' => 'Notes', 'width' => 250],
                ['field' => 'lead_status', 'headerName' => 'Lead-Status', 'width' => 150],
                ['field' => 'multi_visit', 'headerName' => 'Multi-Visit', 'width' => 130],
                ['field' => 'lead_intent', 'headerName' => 'Lead-Intent', 'width' => 150],
                ['field' => 'lead_type', 'headerName' => 'Lead-Type', 'width' => 150],
                ['field' => 'lead_for', 'headerName' => 'Lead-For', 'width' => 150],
                ['field' => 'medium', 'headerName' => 'Medium', 'width' => 150],
                ['field' => 'source', 'headerName' => 'Source', 'width' => 150],
                ['field' => 'client_crm_status', 'headerName' => 'Client-CRM-Status', 'width' => 180],
                ['field' => 'client_crm_message', 'headerName' => 'Client-CRM-Message', 'width' => 250],
                ['field' => 'enquiry_status', 'headerName' => 'Enquiry-Status', 'width' => 150],
                ['field' => 'enquiry_date', 'headerName' => 'Enquiry-Date', 'width' => 150, 'type' => 'date'],
                ['field' => 'model', 'headerName' => 'Model', 'width' => 180],
                ['field' => 'dealer_code', 'headerName' => 'Dealer-Code', 'width' => 150],
            ], $commonEnd);
        }

        if ($type === 'whatsapp')
            return array_merge([
                ['field' => 'serial_no', 'headerName' => 'S.No.'],
                ['field' => 'x8_enquiry_no', 'headerName' => 'X8 Enquiry No.'],
                ['field' => 'x8_enquiry_date', 'headerName' => 'X8 Enquiry Date'],
                ['field' => 'x8_enquiry_assign_date', 'headerName' => 'X8 Enquiry Assign Date'],
                ['field' => 'oem_enquiry_no', 'headerName' => 'OEM Enquiry No.'],
                ['field' => 'oem_enquiry_date', 'headerName' => 'OEM Enquiry Date'],
                ['field' => 'campaign_name', 'headerName' => 'Campaign Name'],
                ['field' => 'campaign_date', 'headerName' => 'Campaign Date'],
                ['field' => 'segment_name', 'headerName' => 'Segment'],
                ['field' => 'model_name', 'headerName' => 'Model'],
                ['field' => 'variant_name', 'headerName' => 'Variant'],
                ['field' => 'mobile', 'headerName' => 'Customer Mobile'],
            ], $commonEnd);

        $baseCols = [
            ['field' => 'serial_no', 'headerName' => 'S.No.'],
            ['field' => 'x8_enquiry_no', 'headerName' => 'X8 Enquiry No.'],
            ['field' => 'x8_enquiry_date', 'headerName' => 'X8 Enquiry Date'],
            ['field' => 'x8_enquiry_assign_date', 'headerName' => 'X8 Enquiry Assign Date'],
            ['field' => 'oem_enquiry_no', 'headerName' => 'OEM Enquiry No.'],
            ['field' => 'oem_enquiry_date', 'headerName' => 'OEM Enquiry Date'],
            ['field' => 'oem_enquiry_assign_date', 'headerName' => 'OEM Enquiry Assign Date'],
            ['field' => 'oem_quick_enquiry_no', 'headerName' => 'OEM Quick Enquiry No.'],
            ['field' => 'oem_quick_enquiry_date', 'headerName' => 'OEM Quick Enquiry Date'],
            ['field' => 'oem_quick_enquiry_assign_date', 'headerName' => 'OEM Quick Enquiry Assign Date'],
            ['field' => 'x8_enq_source', 'headerName' => 'X8 Enquiry Source'],
            ['field' => 'enquiry_type', 'headerName' => 'OEM Enquiry Type'],
            ['field' => 'source_code', 'headerName' => 'OEM Enquiry Source'],
            ['field' => 'sub_source', 'headerName' => 'OEM Enquiry Sub Source'],
            ['field' => 'likely_purchase_date', 'headerName' => 'Likely Purchase In Days'],
            ['field' => 'quotation_no', 'headerName' => 'X8 Quotation No.'],
            ['field' => 'quotation_date', 'headerName' => 'X8 Quotation Date'],
            ['field' => 'oem_booking_no', 'headerName' => 'OEM Booking No.'],
            ['field' => 'oem_booking_date', 'headerName' => 'OEM Booking Date'],
            ['field' => 'oem_otf_no', 'headerName' => 'OEM OTF No.'],
            ['field' => 'x8_booking_no', 'headerName' => 'X8 Booking No.'],
            ['field' => 'x8_booking_date', 'headerName' => 'X8 Booking Date'],
        ];

        // if ($type === 'all' || in_array($type, ['exchange', 'scrappage', 'exchange_not_interested', 'finance', 'finance_not_interested'])) {
        //     $baseCols = array_merge($baseCols, [
        //         ['field' => 'oem_quick_enquiry_no', 'headerName' => 'OEM Quick Enquiry No.'],
        //         ['field' => 'oem_quick_enquiry_date', 'headerName' => 'OEM Quick Enquiry Date'],
        //         ['field' => 'oem_quick_enquiry_assign_date', 'headerName' => 'OEM Quick Enquiry Assign Date'],
        //     ]);
        // } elseif ($type === 'quick') {
        //     $baseCols = array_merge($baseCols, [
        //         ['field' => 'oem_quick_enquiry_no', 'headerName' => 'OEM Quick Enquiry No.'],
        //         ['field' => 'oem_quick_enquiry_date', 'headerName' => 'OEM Quick Enquiry Date'],
        //     ]);
        // } elseif ($type === 'long') {
        //     $baseCols = array_merge($baseCols, [
        //         ['field' => 'oem_long_enquiry_no', 'headerName' => 'OEM Long Enquiry No.'],
        //         ['field' => 'oem_long_enquiry_date', 'headerName' => 'OEM Long Enquiry Date'],
        //         ['field' => 'oem_long_enquiry_assign_date', 'headerName' => 'OEM Enquiry Assign Date'],
        //     ]);
        // }

        $midCols = [
            ['field' => 'segment_name', 'headerName' => 'Segment'],
            ['field' => 'model_name', 'headerName' => 'Model'],
            ['field' => 'variant_name', 'headerName' => 'Variant'],
            ['field' => 'color_name', 'headerName' => 'Color'],
            ['field' => 'fuel_type', 'headerName' => 'Fuel Type'],
            ['field' => 'transmission', 'headerName' => 'Transmission'],
            ['field' => 'drivetrain', 'headerName' => 'Drivetrain'],
            ['field' => 'seating', 'headerName' => 'Seating'],
            ['field' => 'usage_area', 'headerName' => 'Usage Area'],
            ['field' => 'km_travelled_daily', 'headerName' => 'KM Travelled Daily'],
            ['field' => 'application_type', 'headerName' => 'Application Type'],
            ['field' => 'application', 'headerName' => 'Application'],
            ['field' => 'has_ev', 'headerName' => 'Has EV'],

            ['field' => 'first_name', 'headerName' => 'First Name'],
            ['field' => 'last_name', 'headerName' => 'Last Name'],
            ['field' => 'full_name', 'headerName' => 'Full Name'],
            ['field' => 'mobile', 'headerName' => 'Contact No.'],
            ['field' => 'alternate_mobile', 'headerName' => 'AlternateContact No.'],
            ['field' => 'email', 'headerName' => 'Email'],
            ['field' => 'gender', 'headerName' => 'Gender'],
            ['field' => 'pincode', 'headerName' => 'Pincode'],
            ['field' => 'territory', 'headerName' => 'Territory'],
            ['field' => 'tehsil', 'headerName' => 'Tehsil'],
            ['field' => 'district', 'headerName' => 'District'],
            ['field' => 'city', 'headerName' => 'State'],
            ['field' => 'address', 'headerName' => 'Address'],
            ['field' => 'purchase_type', 'headerName' => 'Purchase Type'],
            ['field' => 'expected_price', 'headerName' => 'Expected Price'],
            ['field' => 'offered_price', 'headerName' => 'Offered Price'],
            ['field' => 'exchange_bonus', 'headerName' => 'Exchange Bonus'],
            ['field' => 'price_gap', 'headerName' => 'Price Gap'],
            ['field' => 'fin_mode', 'headerName' => 'Finance Mode'],
            ['field' => 'financier_name', 'headerName' => 'Financier'],
            ['field' => 'loan_status', 'headerName' => 'Loan Status'],
            ['field' => 'x8_sc_code', 'headerName' => 'X8 Assigned SC'],
            ['field' => 'x8_sc_mile_id', 'headerName' => 'X8 Assigned SC Mile ID'],
            ['field' => 'x8_sc_branch', 'headerName' => 'X8 Assigned SC Branch'],
            ['field' => 'x8_sc_location', 'headerName' => 'X8 Assigned SC Location'],
            ['field' => 'sc_code', 'headerName' => 'OEM Assigned SC'],
            ['field' => 'sc_mile_id', 'headerName' => 'OEM Assigned SC Mile ID'],
            ['field' => 'oem_sc_branch', 'headerName' => 'OEM Assigned SC Branch'],
            ['field' => 'oem_sc_location', 'headerName' => 'OEM Assigned SC Location'],
            ['field' => 'occupation_type', 'headerName' => 'Occupation Type'],
            ['field' => 'customer_type', 'headerName' => 'Customer Type'],
            ['field' => 'occupation_sub_type', 'headerName' => 'Occupation Sub Type'],
            ['field' => 'company_name', 'headerName' => 'Company Name'],
            ['field' => 'dob', 'headerName' => 'D.O.B.'],
            ['field' => 'marital_status', 'headerName' => 'Marital Status'],
            ['field' => 'marriage_date', 'headerName' => 'Marriage Date'],
            ['field' => 'age_group', 'headerName' => 'Age Group'],
            ['field' => 'consid_brand', 'headerName' => 'Consideration Make'],
            ['field' => 'consid_model', 'headerName' => 'Consideration Model'],
            ['field' => 'consid_variant', 'headerName' => 'Consideration Variant'],

            ['field' => 'fup_count', 'headerName' => 'SC Follow-up Count'],
            ['field' => 'followup_type', 'headerName' => 'SC Followup Type'],
            ['field' => 'recent_planned_followup_date', 'headerName' => 'Planned FUP Date & Time'],
            ['field' => 'recent_actual_followup_date', 'headerName' => 'Actual FUP Date & Time'],
            ['field' => 'call_duration', 'headerName' => 'FUP Call Duration'],
            //['field' => 'deviation_stage', 'headerName' => 'FUP Deviation Stage'],
            ['field' => 'remarks', 'headerName' => 'FUP Remarks'],
            ['field' => 'followup_remarks_type', 'headerName' => 'FUP Remarks Type'],
            //['field' => 'comments', 'headerName' => 'FUP Comments'],

            ['field' => 'stage', 'headerName' => 'SC Enquiry Stage'],
            ['field' => 'td_count', 'headerName' => 'Test Drive Count'],
            ['field' => 'test_drive_no', 'headerName' => 'Test Drive No.'],
            ['field' => 'td_date', 'headerName' => 'Test drive Date'],
            ['field' => 'lost_reason', 'headerName' => 'SC Lost Reason'],
            ['field' => 'lost_sub_reason', 'headerName' => 'SC Lost Sub Reason'],
            ['field' => 'lost_detail_reason', 'headerName' => 'SC Lost Detail Reason'],
            ['field' => 'lost_remarks', 'headerName' => 'SC Lost Remarks'],

            ['field' => 'cre_fup_count', 'headerName' => 'CRE FUP Count'],
            ['field' => 'cre_planned_fup_date', 'headerName' => 'CRE Planned FUP Date & Time'],
            ['field' => 'cre_actual_fup_date', 'headerName' => 'CRE Actual FUP Date & Time'],
            ['field' => 'cre_fup_call_duration', 'headerName' => 'CRE FUP Call Duration'],
            ['field' => 'cre_fup_deviation_stage', 'headerName' => 'CRE FUP Deviation Stage'],
            ['field' => 'cre_enq_stage', 'headerName' => 'CRE Enquiry Satge'],
            ['field' => 'cre_customer_stage', 'headerName' => 'CRE Customer Stage'],
            ['field' => 'cre_fup_remarks', 'headerName' => 'CRE FUP Remarks'],
            ['field' => 'cre_next_fup_date', 'headerName' => 'CRE Next FUP Date'],


        ];

        // if ($type === 'quick') {
        //     $remove = ['drivetrain', 'seating', 'tehsil', 'district', 'occupation_sub_type', 'company_name', 'dob', 'marital_status', 'marriage_date', 'age_group', 'usage_area', 'km_travelled_daily', 'application_type', 'application', 'pincode', 'address', 'has_ev', 'consider_make', 'consider_model', 'consider_variant', 'full_name'];
        //     $midCols = array_values(array_filter($midCols, fn($c) => !in_array($c['field'], $remove)));
        // } elseif ($type === 'long') {
        //     $remove = ['drivetrain', 'seating', 'usage_area', 'application_type', 'application', 'has_ev', 'full_name'];
        //     $midCols = array_values(array_filter($midCols, fn($c) => !in_array($c['field'], $remove)));
        // }

        return array_merge($baseCols, $midCols, $commonEnd);
    }

    private function applyEnquirySearch($query, string $searchText): void
    {
        if ($searchText === '') return;

        $like = "%{$searchText}%";
        $isXenq = str_starts_with(strtoupper($searchText), 'XENQ-');
        $xenqId = $isXenq ? (int) substr(strtoupper($searchText), 5) : null;

        $query->where(function ($q) use ($like, $xenqId, $isXenq, $searchText) {
            if ($isXenq && $xenqId) {
                $q->where('id', $xenqId);
            } else {
                $q->where('id', (int) $searchText)
                    ->orWhere('enquiry_no', 'like', $like)
                    ->orWhere('oem_enquiry_no', 'like', $like)
                    ->orWhere('x8_enquiry_no', 'like', $like)
                    ->orWhere('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('mobile', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('source_code', 'like', $like)
                    ->orWhere('sub_source', 'like', $like)
                    ->orWhere('company_name', 'like', $like)
                    ->orWhere('vehicle_no', 'like', $like)
                    ->orWhere('city', 'like', $like)
                    ->orWhere('pincode', 'like', $like)
                    ->orWhereHas('model', fn($q2) => $q2->where('name', 'like', $like))
                    ->orWhereHas('segment', fn($q2) => $q2->where('name', 'like', $like))
                    ->orWhereHas('color', fn($q2) => $q2->where('name', 'like', $like))
                    ->orWhereHas('variant', fn($q2) => $q2->where('display_name', 'like', $like)->orWhere('custom_name', 'like', $like)->orWhere('oem_name', 'like', $like));
            }
        });
    }

    private function applyEnquirySort($query, array $sortModel): void
    {
        $cols = ['enquiry_no', 'enquiry_type', 'sub_source', 'person_code', 'first_name', 'last_name', 'mobile', 'email', 'occupation_type', 'customer_type', 'company_name', 'gender', 'dob', 'marital_status', 'city', 'district', 'purchase_type', 'created_at'];
        $sortApplied = false;

        foreach ($sortModel as $sort) {
            $colId = $sort['colId'] ?? null;
            if ($colId && in_array($colId, $cols, true)) {
                $query->orderBy($colId, strtolower($sort['sort'] ?? 'asc') === 'desc' ? 'desc' : 'asc');
                $sortApplied = true;
            }
        }
        if (!$sortApplied)
            $query->orderByDesc('created_at');
    }

    private const FILTER_FIELD_MAP = [
        'full_name' => null,
        'action' => null,
        'source_name' => 'source_code',
        'segment_name' => null,
        'model_name' => null,
        'variant_name' => null,
        'color_name' => null,
        'exchange_make' => 'brand_make',
        'exchange_model' => 'brand_model',
        'consider_make' => 'consid_brand',
        'consider_model' => 'consid_model',
        'consider_variant' => 'consid_variant',
        'likely_purchase_in_days' => 'likely_purchase_date',
    ];

    private const FILTER_CODE_COLUMN_MAP = [
        'segment_name' => ['code' => 'segment_code', 'relation' => 'segment', 'relCols' => ['name'], 'rawCol' => 'segment'],
        'model_name' => ['code' => 'model_code', 'relation' => 'model', 'relCols' => ['name'], 'rawCol' => 'model'],
        'variant_name' => ['code' => 'variant_code', 'relation' => 'variant', 'relCols' => ['display_name', 'custom_name', 'oem_name'], 'rawCol' => 'variant'],
        'color_name' => ['code' => 'color_code', 'relation' => 'color', 'relCols' => ['name'], 'rawCol' => 'color'],
    ];

    private function applyEnquiryFilter($query, array $filterModel): void
    {
        if (empty($filterModel)) return;

        foreach ($filterModel as $field => $condition) {
            if (!is_array($condition)) continue;

            if (isset(self::FILTER_CODE_COLUMN_MAP[$field])) {
                $map = self::FILTER_CODE_COLUMN_MAP[$field];
                $query->where(function ($q) use ($map, $condition) {
                    $q->where(function ($q2) use ($map, $condition) {
                        $q2->whereNotNull($map['code'])->where($map['code'], '!=', '')
                            ->whereHas($map['relation'], function ($q3) use ($map, $condition) {
                                $this->applyFilterConditionAnyColumn($q3, $map['relCols'], $condition);
                            });
                    })->orWhere(function ($q2) use ($map, $condition) {
                        $q2->where(function ($q3) use ($map) {
                            $q3->whereNull($map['code'])->orWhere($map['code'], '');
                        });
                        $this->applyFilterCondition($q2, $map['rawCol'], $condition);
                    });
                });
                continue;
            }

            $column = array_key_exists($field, self::FILTER_FIELD_MAP) ? self::FILTER_FIELD_MAP[$field] : $field;
            if ($column === null) continue;
            $this->applyFilterCondition($query, $column, $condition);
        }
    }

    private function applyFilterConditionAnyColumn($query, array $columns, array $condition): void
    {
        $query->where(function ($q) use ($columns, $condition) {
            foreach ($columns as $col) {
                $q->orWhere(function ($q2) use ($col, $condition) {
                    $this->applyFilterCondition($q2, $col, $condition);
                });
            }
        });
    }

    private function applyFilterCondition($query, string $column, array $condition): void
    {
        if (isset($condition['conditions']) && is_array($condition['conditions'])) {
            $operator = strtoupper($condition['operator'] ?? 'AND') === 'OR' ? 'orWhere' : 'where';
            $query->where(function ($q) use ($column, $condition, $operator) {
                foreach ($condition['conditions'] as $sub) {
                    $q->{$operator}(function ($q2) use ($column, $sub) {
                        $this->applyFilterCondition($q2, $column, $sub);
                    });
                }
            });
            return;
        }

        $type = $condition['type'] ?? 'contains';
        $value = $condition['filter'] ?? null;

        switch ($type) {
            case 'equals':
                $query->where($column, $value);
                break;
            case 'notEqual':
                $query->where($column, '!=', $value);
                break;
            case 'startsWith':
                $query->where($column, 'like', "{$value}%");
                break;
            case 'endsWith':
                $query->where($column, 'like', "%{$value}");
                break;
            case 'blank':
                $query->where(function ($q) use ($column) {
                    $q->whereNull($column)->orWhere($column, '');
                });
                break;
            case 'notBlank':
                $query->whereNotNull($column)->where($column, '!=', '');
                break;
            case 'contains':
            default:
                if ($value !== null && $value !== '') {
                    $query->where($column, 'like', "%{$value}%");
                }
                break;
        }
    }

    public function create()
    {
        return view('admin.enquiry.create', [
            'title' => 'Add New Enquiry',
            'enquiry' => null,
            'fups' => []
        ] + $this->getEnquiryFormData());
    }

    public function createReference()
    {
        return view('admin.enquiry.reference-create', [
            'title' => 'Add Reference Enquiry',
            'enquiry' => null,
            'fups' => []
        ] + $this->getEnquiryFormData());
    }

    public function edit($id)
    {
        $data = $this->getEnquiryFormData();
        $data['title'] = 'Edit Enquiry';
        $enquiry = Enquiry::with(['campaign', 'segment', 'model', 'variant', 'color'])->findOrFail($id);

        $fups = [];
        if (strtoupper($enquiry->current_origin ?? '') === 'LONG') {
            $fups = DB::table('xlr8_crm_enquiries_fup')
                ->where('enquiry_no', $enquiry->enquiry_no)
                ->orderBy('id', 'asc')
                ->get();
        }

        $data['enquiry'] = $enquiry;
        $data['fups'] = $fups;

        return view('admin.enquiry.create', $data);
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate($this->getValidationRules());
            $this->processEntityRelations($validated);
            $validated['created_by'] = backpack_user()->id;
            $validated['origin'] = 'QUICK';
            $validated['current_origin'] = 'QUICK';
            $validated['cne'] = 1;

            Enquiry::create($validated);
            Alert::success('Enquiry created successfully.')->flash();
            return redirect(backpack_url('enquiry'));
        } catch (\Throwable $e) {
            Log::error($e->getMessage());
            throw $e;
        }
    }

    public function update(Request $request, $id)
    {
        $enquiry = Enquiry::findOrFail($id);
        $validated = $request->validate($this->getValidationRules($id));
        $this->processEntityRelations($validated);
        $validated['updated_by'] = backpack_user()->id;

        $enquiry->update($validated);
        Alert::success('Enquiry updated successfully.')->flash();
        return redirect(backpack_url('enquiry'));
    }

    public function exchangeEnquiryEdit($id)
    {
        $enquiry = Enquiry::with(['segment', 'model', 'variant'])->findOrFail($id);
        $existing_car_oems = OrgService::keywordValueByCode('EXISTING_CAR_OEM');
        return view('admin.enquiry.exchange-edit', compact('enquiry', 'existing_car_oems'));
    }

    public function exchangeEnquiryUpdate(Request $request, $id)
    {
        $enquiry = Enquiry::findOrFail($id);
        $enquiry->update($request->only([
            'brand_make',
            'brand_model',
            'vehicle_no',
            'lost_reason',
            'make_year',
            'odo_reading',
            'expected_price',
            'offered_price',
            'exchange_bonus'
        ]));
        Alert::success('Exchange Details Updated successfully.')->flash();

        if ($enquiry->purchase_type === 'Scrappage') {
            return redirect(backpack_url('exchange/enquiry/int-in-scrappage'));
        }
        return redirect(backpack_url('exchange/enquiry/int-in-exchange'));
    }

    public function financeEnquiryEdit($id)
    {
        $enquiry = Enquiry::with(['segment', 'model', 'variant'])->findOrFail($id);
        $finance = \App\Models\Module\Finance\XFinance::where('enq_no', $enquiry->enquiry_no)->first();
        $financiers = \App\Models\Module\Booking\XlFinancier::select('id', 'name', 'short_name')->get()->toArray();

        return view('admin.enquiry.finance-edit', compact('enquiry', 'finance', 'financiers'));
    }

    public function financeEnquiryUpdate(Request $request, $id)
    {
        $enquiry = Enquiry::findOrFail($id);

        $enquiry->update([
            'fin_mode' => $request->fin_mode,
            'financier' => $request->financier,
            'loan_status' => $request->loan_status,
        ]);

        $finance = \App\Models\Module\Finance\XFinance::firstOrNew(['enq_no' => $enquiry->enquiry_no]);

        $finance->bid = $enquiry->id;
        $finance->fin_mode = $request->fin_mode;
        $finance->financier = $request->financier;
        $finance->loan_status = $request->loan_status;
        $finance->case_status = $request->case_status ?? 1;
        $finance->verification_status = $request->verification_status ?? 1;
        $finance->case_lost_reason = $request->case_lost_reason;

        if (!in_array($request->fin_mode, ['Cash', 'Customer Self', 'Yet To Decide', 'Purchase Plan Cancelled'])) {
            $finance->instrument_type = $request->instrument_type;
            $finance->instrument_ref_no = $request->instrument_ref_no;
            $finance->loan_amount = $request->loan_amount;
            $finance->margin = $request->margin_money;
            $finance->file_charge = $request->file_charge;
        } else {
            $finance->instrument_type = null;
            $finance->instrument_ref_no = null;
            $finance->loan_amount = null;
            $finance->margin = null;
            $finance->file_charge = null;
        }

        $finance->updated_by = backpack_auth()->id();
        $finance->status = ($finance->fin_mode === 'In-house' && $finance->case_status == 2) ? 2 : 1;
        $finance->save();

        if ($request->hasFile('instrument_proof')) {
            $finance->clearMediaCollection('instrument_proof');
            $finance->addMediaFromRequest('instrument_proof')->toMediaCollection('instrument_proof');
        }

        Alert::success('Finance Details Updated successfully.')->flash();

        if (in_array($enquiry->fin_mode, ['Cash', 'Customer Self', 'Yet To Decide', 'Purchase Plan Cancelled'])) {
            return redirect(backpack_url('finance/enquiry/not-interested'));
        }
        return redirect(backpack_url('finance/enquiry/int-in-finance'));
    }

    public function storeReference(Request $request)
    {
        try {
            $validated = $request->validate([
                'referee_name' => 'required|max:100',
                'referee_phone' => 'required|numeric|digits:10',
                'first_name' => 'required|max:100',
                'last_name' => 'nullable|max:100',
                'mobile' => 'required|numeric|digits:10',
                'segment_code' => 'required',
                'model_code' => 'required',
                'variant_code' => 'nullable',
            ]);

            $validated['enquiry_no'] = 'REF-' . strtoupper(uniqid());
            $this->processEntityRelations($validated);

            $validated['source_code'] = 'REFERENCE';
            $validated['created_by'] = backpack_user()->id;
            $validated['origin'] = 'REFERENCE';
            $validated['current_origin'] = 'REFERENCE';
            $validated['cne'] = 1;

            Enquiry::create($validated);
            Alert::success('Reference Enquiry created successfully.')->flash();
            return redirect(backpack_url('enquiries/reference'));
        } catch (\Throwable $e) {
            Log::error($e->getMessage());
            throw $e;
        }
    }

    private function processEntityRelations(array &$validated)
    {
        $validated['segment'] = OrgService::segments()[$validated['segment_code']] ?? null;
        $validated['model'] = OrgService::models($validated['segment_code'])[$validated['model_code']] ?? null;
        if (!empty($validated['variant_code']))
            $validated['variant'] = OrgService::variants($validated['model_code'])[$validated['variant_code']]['name'] ?? null;
        if (!empty($validated['color_code']))
            $validated['color'] = OrgService::colors($validated['variant_code'])[$validated['color_code']] ?? null;
    }

    private function getValidationRules($id = null)
    {
        $fullFormActive = request()->has('segment_code') || !request()->has('call_nature');
        $req = $fullFormActive ? 'required' : 'nullable';

        return [
            'enquiry_type' => $req,
            'source_code' => $req,
            'sub_source' => 'nullable',
            'person_code' => 'nullable',
            'reference_details' => 'nullable|max:255',
            'referred_by' => 'nullable|max:100',
            'referee_phone' => 'nullable|max:15',
            'referee_name' => 'nullable|max:100',
            'planned_campaign' => 'nullable|max:150',
            'likely_purchase_date' => 'nullable|max:150',
            'activity_type' => 'nullable',
            'activity_segment' => 'nullable',
            'activity_model' => 'nullable',
            'activity_start_date' => 'nullable|date',
            'activity_end_date' => 'nullable|date',
            'activity_branch' => 'nullable',
            'activity_location' => 'nullable',
            'first_name' => $req . '|max:100',
            'last_name' => 'nullable|max:100',
            'mobile' => 'required|max:15',
            'email' => 'nullable|email|max:150',
            'occupation_type' => 'nullable',
            'customer_type' => 'nullable',
            'occupation_sub_type' => 'nullable',
            'company_name' => 'nullable|max:150',
            'gender' => 'nullable',
            'dob' => 'nullable|date',
            'marital_status' => 'nullable',
            'marriage_date' => 'nullable|date',
            'age_group' => 'nullable',
            'pincode' => 'nullable|max:10',
            'vpo' => 'nullable|max:150', // FIXED: Validating 'vpo' instead of 'bpo'
            'tehsil' => 'nullable|max:100',
            'district' => 'nullable|max:100',
            'city' => 'nullable|max:100',
            'territory' => 'nullable|string|max:100',
            'has_ev' => 'nullable',
            'purchase_type' => 'nullable',
            'purchase_type_crm' => 'nullable|string|max:100',
            'consider_make' => 'nullable|max:100',
            'consider_model' => 'nullable|max:100',
            'consider_variant' => 'nullable|max:100',
            'vehicle_no' => 'nullable|max:30',
            'remarks' => 'nullable',
            'segment_code' => $req,
            'model_code' => $req,
            'variant_code' => $req,
            'color_code' => $req,
            'fuel_type' => 'nullable',
            'transmission' => 'nullable',
            'drivetrain' => 'nullable',
            'seating' => 'nullable',
            'usage_area' => 'nullable',
            'km_travelled_daily' => 'nullable',
            'application_type' => 'nullable',
            'application' => 'nullable',
            'place_of_registration' => 'nullable|max:100',
            'dealer_branch' => $req,
            'dealer_location' => $req,
            'sc_code' => $req,
            'followup_type' => 'nullable',
            'followup_date' => 'nullable|date',
            'followup_time' => 'nullable',
            'actual_fup_date' => 'nullable|date',
            'actual_fup_duration' => 'nullable|string|max:100',
            'followup_status' => 'nullable|string|max:100',
            'latest_fup_status' => 'nullable|string|max:100',
            'fup_count' => 'nullable|integer',
            'recent_actual_followup_date' => 'nullable|date',
            'recent_fup_remarks' => 'nullable|string|max:255',
            'recent_fup_remarks_type' => 'nullable|string|max:255',
            'recent_fup_comments' => 'nullable|string|max:255',
            'test_drive_count' => 'nullable|integer',
            'test_drive_no' => 'nullable|string|max:100',
            'td_date' => 'nullable|date',
            'lost_reason' => 'nullable|string|max:100',
            'lost_sub_reason' => 'nullable|string|max:100',
            'lost_detail_reason' => 'nullable|string|max:100',
            'lost_remarks' => 'nullable|string|max:100',
            'make_year' => 'nullable|integer',
            'odo_reading' => 'nullable|numeric',
            'expected_price' => 'nullable|numeric',
            'offered_price' => 'nullable|numeric',
            'exchange_bonus' => 'nullable|numeric',
            'fin_mode' => 'nullable|string|max:50',
            'financier' => 'nullable|integer',
            'brand_make' => 'nullable|string|max:100',
            'brand_model' => 'nullable|string|max:100',
            'call_nature' => 'nullable|string',
        ];
    }

    private function getEnquiryFormData()
    {
        $kw = fn($k) => OrgService::keywordValueByCode($k);
        return [
            'segments' => OrgService::segments(),
            'models' => [],
            'variants' => [],
            'colors' => [],
            'locations' => [],
            'saleconsultants' => OrgService::getUsers(desigCode: 'CNS'),
            'branches' => OrgService::branches(),
            'campaigns' => Campaign::orderBy('name')->pluck('name')->toArray(),
            'likely_purchase_dates' => $kw('LIKELY_PURCHASE_DATE'),
            'enquiry_types' => $kw('ENQUIRY_TYPE'),
            'activity_types' => $kw('ACTIVITY_TYPE'),
            'follow_up_types' => $kw('FOLLOW_UP_TYPE'),
            'occupation_types' => $kw('OCCUPATION_TYPE'),
            'occupation_sub_types' => $kw('OCCUPATION_SUB_TYPE'),
            'customer_types' => $kw('CUSTOMER_TYPE'),
            'genders' => $kw('GENDER'),
            'marital_statuses' => $kw('MARITAL_STATUS'),
            'age_groups' => $kw('AGE_GROUP'),
            'usage_areas' => $kw('USAGE_AREA'),
            'km_travelled_daily' => $kw('KM_TRAVELLED_DAILY'),
            'application_types' => $kw('APPLICATION_TYPE'),
            'applications' => $kw('APPLICATION'),
            'call_nature_virtual' => $kw('CALL_NATURE_VIRTUAL'),
            'sc_fup_remarks' => $kw('SC_FUP_REMARKS'),
            'sc_fup_remarks_types' => $kw('SC_FUP_REMARKS_TYPE'),
            'enquiry_sources' => $kw('ENQ_SOURCE'),
            'enquiry_sub_sources' => $kw('ENQUIRY_SUB_SOURCE'),
            'existing_car_oems' => $kw('EXISTING_CAR_OEM'),
            'existing_car_models' => $kw('EXISTING_CAR_MODEL'),
            'existing_car_variants' => $kw('EXISTING_CAR_VARIANT'),
            'fuel_types' => $kw('FUEL_TYPE'),
            'transmission_types' => $kw('TRANSMISSION_TYPE'),
            'finance_types' => $kw('FINANCE_TYPE'),
            'purchase_reasons' => $kw('PURCHASE_REASON'),
            'financiers' => collect(XlFinancier::select('id', 'name', 'short_name')->get()->toArray())->map(fn($f) => (object) $f),
        ];
    }

    public function getSources()
    {
        return response()->json(LeadSource::active()->orderBy('sort_order')->orderBy('name')->pluck('name', 'code')->toArray());
    }
    public function getVariants($modelCode)
    {
        return response()->json(OrgService::variants($modelCode));
    }
    public function getColors($variantCode)
    {
        return response()->json(OrgService::colors($variantCode));
    }
    public function getModels(string $segmentCode)
    {
        return response()->json(OrgService::models($segmentCode));
    }
    public function getLocations($branchCode)
    {
        return response()->json(OrgService::locations($branchCode));
    }
    public function getKeywordValues($keyword, $parent)
    {
        return response()->json(OrgService::keywordValueByParentCode($keyword, $parent));
    }
    public function getReferenceUsers(Request $request)
    {
        return response()->json(OrgService::getReferenceUsers($request->type, $request->mobile));
    }
    public function locationByPincode(Request $request)
    {
        return response()->json(OrgService::getLocationByPincode($request->pincode));
    }

    public function getLead($leadNo)
    {
        $lead = Lead::where('lead_no', $leadNo)->firstOrFail();
        return response()->json($lead->only(['source_code', 'referral_details', 'first_name', 'last_name', 'mobile', 'email', 'occupation', 'segment_code', 'model_code', 'variant_code', 'color_code']));
    }

    public function checkDuplicateEnquiry(Request $request)
    {
        $enquiry = Enquiry::where('mobile', $request->mobile)->where('segment_code', $request->segment_code)->first();
        return response()->json(['exists' => (bool) $enquiry, 'enquiry_no' => $enquiry?->enquiry_no]);
    }

    public function importEnquiries(Request $request)
    {
        if (!$request->hasFile('excel_file') || !in_array($request->file('excel_file')->getClientOriginalExtension(), ['xlsx', 'xls'])) {
            Alert::error('Invalid or missing file! Only Excel files (.xlsx, .xls) allowed')->flash();
            return redirect()->back();
        }

        $absolutePath = Storage::disk('local')->path($request->file('excel_file')->store('imports', 'local'));
        $importLogId = DB::table('xlr8_crm_import_logs')->insertGetId([
            'file_name' => $request->file('excel_file')->getClientOriginalName(),
            'stored_path' => $absolutePath,
            'status' => 'queued',
            'created_by' => backpack_user()->id ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        ImportEnquiriesJob::dispatch($importLogId, $absolutePath);
        Alert::success("File uploaded and queued for processing (Import #{$importLogId}).")->flash();
        return redirect()->back();
    }

    public function importStatus($id)
    {
        if (!$log = DB::table('xlr8_crm_import_logs')->where('id', $id)->first())
            return response()->json(['error' => 'Not found'], 404);
        return response()->json([
            'id' => $log->id,
            'status' => $log->status,
            'total_rows' => $log->total_rows,
            'processed_rows' => $log->processed_rows,
            'percent' => $log->total_rows > 0 ? round(($log->processed_rows / $log->total_rows) * 100, 1) : 0,
            'stats' => $log->stats ? json_decode($log->stats, true) : null,
            'error_message' => $log->error_message,
        ]);
    }

    public function importHistory()
    {
        return response()->json(DB::table('xlr8_crm_import_logs')->orderByDesc('id')->limit(5)->get());
    }

    // ============================================================
    // OTF BOOKINGS LIST (Directly from xlr8_crm_booking)
    // ============================================================
    public function otfBookings(Request $request)
    {
        $this->crud->hasAccessOrFail('list');

        $this->data['crud'] = $this->crud;
        $this->data['title'] = 'OTF Bookings';

        // ✅ Directly query xlr8_crm_booking table - NO JOINS
        $query = \App\Models\Module\Booking\Booking::withoutGlobalScope(\Illuminate\Database\Eloquent\SoftDeletingScope::class)
            ->from('xlr8_crm_booking as crm_booking')
            ->select([
                'crm_booking.id',
                'crm_booking.booking_date',
                'crm_booking.status',
                'crm_booking.cancellation_date',
                'crm_booking.model_code',
                'crm_booking.variant_code',

                // ✅ All columns directly from xlr8_crm_booking
                'crm_booking.sc_mile_id as sc_code', // SC Code
                'crm_booking.oem_code',
                'crm_booking.customer_code',
                'crm_booking.customer_tan as tan_no',

                'crm_booking.customer_name',
                'crm_booking.customer_address',
                'crm_booking.customer_city as city',
                'crm_booking.customer_tehsil as tehsil',
                'crm_booking.customer_district as district',
                'crm_booking.customer_pan as pan_no',
                'crm_booking.customer_aadhar as adhar_no',
                'crm_booking.otf_no as otf_number',
            ])
            // ✅ Filter only records where OTF number is not empty
            ->whereNotNull('crm_booking.otf_no')
            ->where('crm_booking.otf_no', '!=', '')
            ->where('crm_booking.is_active', 1);

        $query->orderBy('crm_booking.id', 'desc');

        $paginatedBookings = $query->paginate(50);

        // Map data for grid
        $gridData = $paginatedBookings->map(function ($booking, $index) use ($paginatedBookings) {
            $mapped = [];

            $mapped['serial_no'] = ($paginatedBookings->currentPage() - 1) * $paginatedBookings->perPage() + $index + 1;
            $mapped['booking_no'] = $booking->id ?? '—';
            $mapped['booking_date'] = $booking->booking_date ? \Carbon\Carbon::parse($booking->booking_date)->format('d-M-Y') : '—';
            $mapped['sc_code'] = $booking->sc_code ?? '—';
            $mapped['booking_status'] = $booking->status ?? '—'; // Status already string
            $mapped['cancellation_date'] = $booking->cancellation_date ? \Carbon\Carbon::parse($booking->cancellation_date)->format('d-M-Y') : '—';
            $mapped['model_group'] = $booking->model_code ?? '—';
            $mapped['model'] = $booking->model_code ?? '—';
            $mapped['variant'] = $booking->variant_code ?? '—';
            $mapped['oem_model_code'] = $booking->oem_code ?? '—';
            $mapped['customer_code'] = $booking->customer_code ?? '—';
            $mapped['customer_name'] = $booking->customer_name ?? '—';
            $mapped['customer_address'] = $booking->customer_address ?? '—';
            $mapped['customer_city'] = $booking->city ?? '—';
            $mapped['customer_tehsil'] = $booking->tehsil ?? '—';
            $mapped['customer_district'] = $booking->district ?? '—';
            $mapped['pan_number'] = $booking->pan_no ?? '—';
            $mapped['tan_number'] = $booking->tan_no ?? '—';
            $mapped['aadhaar_number'] = $booking->adhar_no ?? '—';
            $mapped['otf_number'] = $booking->otf_number ?? '—';

            // No action buttons as requested

            return $mapped;
        })->values();

        // Columns as per your headers
        $columns = [
            ['field' => 'serial_no', 'headerName' => 'S.No.', 'width' => 80, 'pinned' => 'left'],
            ['field' => 'booking_no', 'headerName' => 'X8 Booking Number', 'width' => 150],
            ['field' => 'booking_date', 'headerName' => 'X8 Booking Date', 'width' => 130],
            ['field' => 'sc_code', 'headerName' => 'SC Code', 'width' => 120],
            ['field' => 'booking_status', 'headerName' => 'Booking Status', 'width' => 130],
            ['field' => 'cancellation_date', 'headerName' => 'Booking Cancellation Date', 'width' => 160],
            ['field' => 'model_group', 'headerName' => 'Model Group', 'width' => 140],
            ['field' => 'model', 'headerName' => 'Model', 'width' => 160],
            ['field' => 'variant', 'headerName' => 'Variant', 'width' => 160],
            ['field' => 'oem_model_code', 'headerName' => 'OEM Model Code', 'width' => 160],
            ['field' => 'customer_code', 'headerName' => 'Booking Customer Code', 'width' => 160],
            ['field' => 'customer_name', 'headerName' => 'Booking Customer Name', 'width' => 200],
            ['field' => 'customer_address', 'headerName' => 'Booking Customer Address', 'width' => 250],
            ['field' => 'customer_city', 'headerName' => 'Booking Customer City', 'width' => 150],
            ['field' => 'customer_tehsil', 'headerName' => 'Booking Customer Tehsil', 'width' => 150],
            ['field' => 'customer_district', 'headerName' => 'Booking Customer District', 'width' => 150],
            ['field' => 'pan_number', 'headerName' => 'Billing Customer PAN Number', 'width' => 160],
            ['field' => 'tan_number', 'headerName' => 'Billing Customer TAN Number', 'width' => 160],
            ['field' => 'aadhaar_number', 'headerName' => 'Billing Customer Aadhaar Number', 'width' => 180],
            ['field' => 'otf_number', 'headerName' => 'OTF Number', 'width' => 160],
            [
                'field'         => 'action',
                'headerName'    => 'Action',
                'width'         => 100,
                'sortable'      => false,
                'filter'        => false,
                'cellRenderer'  => 'htmlRenderer',
                'pinned'        => 'right',
                'cellClass'     => 'text-center',
            ]
        ];

        $gridConfig = [
            'columns' => $columns,
            'data'    => $gridData,
        ];

        $this->data['gridConfig'] = $gridConfig;
        $this->data['pagination'] = [
            'total'       => $paginatedBookings->total(),
            'perPage'     => $paginatedBookings->perPage(),
            'currentPage' => $paginatedBookings->currentPage(),
            'lastPage'    => $paginatedBookings->lastPage(),
        ];

        return view('admin.enquiry.otf-bookings', $this->data);
    }
}
