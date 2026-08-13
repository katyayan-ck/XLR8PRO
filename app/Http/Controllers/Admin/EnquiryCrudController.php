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
use App\Models\Admin\PinCodes;
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

        // 1. Define all highlight filter keys
        $filters = [
            'missed_fup', 'today_fup', 'birthday', 'anniversary', 'exchange',
            'pending_eval', 'delayed', 'wrong_assign', 'finance', 'stage_mismatch', 'lost_verif'
        ];

        // 2. Calculate the count for each filter
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

        // Query the correct scope based on the page
        $query = match ($listType) {
            'reference' => Enquiry::reference(),
            'virtual' => Enquiry::virtual(),
            'whatsapp' => Enquiry::whatsapp(),
            'assigned_long' => Enquiry::assignedLong(),
            'unassigned_long' => Enquiry::unassignedLong(),
            'assigned_quick' => Enquiry::assignedQuick(),
            'unassigned_quick' => Enquiry::unassignedQuick(),
            'exchange' => Enquiry::where('purchase_type', 'Exchange Buy'),
            'scrappage' => Enquiry::where('purchase_type', 'Scrappage'),
            'exchange_not_interested' => Enquiry::whereIn('purchase_type', ['First Time Buy', 'Additional Buy', 'No Consideration']),
            default => Enquiry::query(),
        };

        $query->with(['segment', 'model', 'variant', 'color', 'campaign']);

        // Apply Search & Sort
        $this->applyEnquirySearch($query, $searchText);
        $this->applyEnquirySort($query, (array) $request->input('sortModel', []));
        $this->applyEnquiryFilter($query, $filterModel);
        OrgService::applyHighlightFilter($query, $highlightFilter);

        $total = (clone $query)->count();

        // Determine the mapping format type
        $mapType = in_array($listType, ['assigned_long', 'unassigned_long']) ? 'long' : (in_array($listType, ['assigned_quick', 'unassigned_quick']) ? 'quick' : (in_array($listType, ['reference', 'virtual', 'whatsapp', 'exchange', 'scrappage', 'exchange_not_interested']) ? $listType : 'all'));

        // Pre-fetch Mappings to prevent N+1 Queries
        $lpMap = collect(OrgService::keywordValueByCode('LIKELY_PURCHASE_DATE'))->pluck('value', 'code')->toArray();
        $fuelMap = collect(OrgService::getKeyValuesByCode('FUEL_TYPE'))->pluck('value', 'id')->toArray();

        $gridData = $query->skip($startRow)->take($limit)->get()
            ->map(fn($e, $i) => $this->mapData($e, $startRow + $i, $mapType, $lpMap, $fuelMap))->all();

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
            'assigned_long' => Enquiry::assignedLong(),
            'unassigned_long' => Enquiry::unassignedLong(),
            'assigned_quick' => Enquiry::assignedQuick(),
            'unassigned_quick' => Enquiry::unassignedQuick(),
            'exchange' => Enquiry::where('purchase_type', 'Exchange Buy'),
            'scrappage' => Enquiry::where('purchase_type', 'Scrappage'),
            'exchange_not_interested' => Enquiry::whereIn('purchase_type', ['First Time Buy', 'Additional Buy', 'No Consideration']),
            default => Enquiry::query(),
        };

        $query->with(['segment', 'model', 'variant', 'color', 'campaign']);

        $this->applyEnquirySearch($query, $searchText);
        $this->applyEnquiryFilter($query, $filterModel);
        OrgService::applyHighlightFilter($query, $highlightFilter);

        $query->orderByDesc('created_at');

        $mapType = in_array($listType, ['assigned_long', 'unassigned_long']) ? 'long' : (in_array($listType, ['assigned_quick', 'unassigned_quick']) ? 'quick' : (in_array($listType, ['reference', 'virtual', 'whatsapp', 'exchange', 'scrappage', 'exchange_not_interested']) ? $listType : 'all'));

        $columns = array_values(array_filter(
            $this->getColumns($mapType),
            fn($col) => ($col['field'] ?? null) !== 'action'
        ));

        // Pre-fetch Mappings
        $lpMap = collect(OrgService::keywordValueByCode('LIKELY_PURCHASE_DATE'))->pluck('value', 'code')->toArray();
        $fuelMap = collect(OrgService::getKeyValuesByCode('FUEL_TYPE'))->pluck('value', 'id')->toArray();

        return response()->streamDownload(function () use ($query, $columns, $mapType, $lpMap, $fuelMap) {
            $out = fopen('php://output', 'w');

            fputcsv($out, array_merge(['S.No.'], array_map(fn($c) => $c['headerName'], $columns)));

            $serial = 0;
            $query->chunk(500, function ($chunk) use ($out, &$serial, $columns, $mapType, $lpMap, $fuelMap) {
                foreach ($chunk as $e) {
                    $rowData = $this->mapData($e, $serial, $mapType, $lpMap, $fuelMap);
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

    // =========================================================
    // UNIFIED LIST RENDERERS
    // =========================================================

    public function referenceList()
    {
        return $this->buildGrid(Enquiry::reference(), 'admin.enquiry.reference-enquiry', 'Reference Enquiries', 'reference');
    }
    public function virtualNumberList()
    {
        return $this->buildGrid(Enquiry::virtual(), 'admin.enquiry.virtual-number-enquiry', 'Virtual Number Enquiries', 'virtual');
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

    // Exchange Enquiry Stage Lists - Modified to use the new exchange blade
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

    private function buildGrid($query, $view, $title, $type)
    {
        $this->crud->setListView($view);

        // Pre-fetch Mappings
        $lpMap = collect(OrgService::keywordValueByCode('LIKELY_PURCHASE_DATE'))->pluck('value', 'code')->toArray();
        $fuelMap = collect(OrgService::getKeyValuesByCode('FUEL_TYPE'))->pluck('value', 'id')->toArray();

        // Safely limit the data to 500 rows. 
        $enquiries = $query->with(['segment', 'model', 'variant', 'color', 'campaign'])
            ->orderByDesc('created_at')
            ->limit(500)
            ->get();

        return view($view, [
            'title' => $title,
            'segments' => OrgService::segments(),
            'gridConfig' => [
                'columns' => $this->getColumns($type),
                'data' => $enquiries->map(fn($e, $i) => $this->mapData($e, $i, $type, $lpMap, $fuelMap))->values()
            ]
        ]);
    }

    private function mapData($e, $i, $type, $lpMap = [], $fuelMap = [])
    {
        // BULLETPROOF DATE PARSER: Updated to dd-mmm-yyyy format ('d-M-Y')
        $c = function ($d, $f) {
            try {
                return (!empty(trim((string) $d)) && !str_starts_with((string) $d, '0000'))
                    ? Carbon::parse($d)->format($f)
                    : '—';
            } catch (\Throwable $th) {
                return '—'; // Fallback if the date is corrupted
            }
        };

        $editUrl = backpack_url("enquiry/{$e->id}/edit");
        $quotUrl = backpack_url("quotation-form/create?id={$e->id}");

        $actionBtns = '<a href="' . $editUrl . '" class="btn btn-sm btn-primary">Edit</a>';

        if ($type === 'all') {
            $actionBtns .= '<a href="' . $quotUrl . '" class="btn btn-success btn-sm">Quote</a>';

            $bookUrl = backpack_url("booking/create?enquiry_id={$e->id}");
            $actionBtns .= '<a href="' . $bookUrl . '" class="btn btn-warning btn-sm" title="Convert to Booking">Process</a>';
        }

        // Send Exchange team to dedicated Custom Blade
        if (in_array($type, ['exchange', 'scrappage', 'exchange_not_interested'])) {
            $exchUrl = backpack_url("exchange/enquiry/{$e->id}/edit");
            $actionBtns = '<a href="' . $exchUrl . '" class="btn btn-sm btn-primary">Process</a>';
        }

        $row = [
            'serial_no' => $i + 1,
            
            // Render XENQ-id and created_at into the x8 columns
            'x8_enquiry_no' => 'XENQ-' . $e->id, 
            'x8_enquiry_date' => $c($e->created_at, 'd-M-Y H:i'), 
            'x8_enquiry_assign_date' => $c($e->x8_enquiry_assign_date ?? $e->enq_assign_date, 'd-M-Y'),
            
            // Move original data from DB x8_ fields into OEM columns
            'oem_enquiry_no' => $e->x8_enquiry_no ?? $e->enquiry_no ?? $e->oem_enquiry_no ?? '—',
            'oem_enquiry_date' => $c($e->x8_enquiry_date ?? $e->enquiry_date ?? $e->oem_enquiry_date, 'd-M-Y'),
            'oem_enquiry_assign_date' => $c($e->oem_enquiry_assign_date ?? $e->enq_assign_date, 'd-M-Y'),

            'segment_name' => $e->segment_code
                ? ($e->getRelation('segment')?->name ?? $e->segment ?? $e->segment_code)
                : ($e->segment ?? '—'),
            'model_name' => $e->model_code
                ? ($e->getRelation('model')?->name ?? $e->model ?? $e->model_code)
                : ($e->model ?? '—'),
            'variant_name' => $e->variant_code
                ? ($e->getRelation('variant')?->display_name
                    ?? $e->getRelation('variant')?->custom_name
                    ?? $e->getRelation('variant')?->oem_name
                    ?? $e->variant
                    ?? $e->variant_code)
                : ($e->variant ?? '—'),
            'color_name' => $e->color_code
                ? ($e->getRelation('color')?->name ?? $e->color ?? $e->color_code)
                : ($e->color ?? '—'),
            'mobile' => $e->mobile ?? '—',
            'dms_enquiry_stage' => $e->dms_enquiry_stage ?? $e->stage ?? '—',
            'cre_enquiry_stage' => $e->cre_enquiry_stage ?? '—',
            'cre_next_fup_date' => $c($e->cre_next_fup_date, 'd-M-Y'),
            'cre_next_fup_time' => $e->cre_next_fup_time ?? '—',
            'cre_next_fup_remarks' => $e->cre_next_fup_remarks ?? '—',
            'x8_quotation_no' => $e->x8_quotation_no ?? $e->quotation_no ?? '—',
            'x8_booking_no' => $e->x8_booking_no ?? $e->booking_no ?? '—',
            'x8_booking_date' => $c($e->x8_booking_date ?? $e->booking_date, 'd-M-Y'),
            'oem_booking_no' => $e->oem_booking_no ?? '—',
            'oem_booking_date' => $c($e->oem_booking_date, 'd-M-Y'),
            'oem_otf_no' => $e->oem_otf_no ?? '—',
            'oem_test_drive_no' => $e->oem_test_drive_no ?? $e->test_drive_no ?? '—',
            
            // Requirements Mappings
            'territory' => $e->territory ?? '—',
            'fup_count' => $e->fup_count ?? '—',
            'td_date' => $c($e->td_date, 'd-M-Y'),
            'lost_reason' => $e->lost_reason ?? '—',
            'followup_status' => $e->followup_status ?? $e->fup_status ?? '—', 
            
            'action' => '<div class="d-flex justify-content-center gap-2">' . $actionBtns . '</div>',
        ];

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

        // Apply data for lists (quick, long, all, exchange, scrappage)
        if (in_array($type, ['long', 'quick', 'all', 'exchange', 'scrappage', 'exchange_not_interested'])) {
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
                
                // Mapped Likely Purchase Date value
                'likely_purchase_in_days' => $lpMap[$e->likely_purchase_date] ?? $e->likely_purchase_date ?? '—',

                // Mapped Fuel Type Value
                'fuel_type' => $fuelMap[$e->fuel_type] ?? $e->fuel_type ?? '—',
                
                'transmission' => $e->transmission ?? '—',
                'drivetrain' => $e->drivetrain ?? '—',
                'seating' => $e->seating ?? '—',
                'tehsil' => $e->tehsil ?? '—',
                'district' => $e->district ?? '—',
                'city' => $e->city ?? '—',

                // Mapped Sales Consultant Name
                'sc_code' => $e->sc_code ? (OrgService::getUserNameByCode($e->sc_code, null, $e->sc_code)) : '—',

                'dealer_branch' => $e->dealer_branch ?? '—',
                'dealer_location' => $e->dealer_location ?? '—',

                // Mapped Follow-up Type via KeyValue
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
                'price_gap' => ($e->expected_price ?? 0) - ($e->offered_price ?? 0) - ($e->exchange_bonus ?? 0)
            ];
        }

        return $row;
    }

    private function getColumns($type)
    {
        // Headers common to the end of most grids
        $commonEnd = [
            ['field' => 'dms_enquiry_stage', 'headerName' => 'DMS Stage'],
            ['field' => 'cre_enquiry_stage', 'headerName' => 'CRE Stage'],
            ['field' => 'followup_status', 'headerName' => 'FOLLOW UP STATUS'], // Req 3
            ['field' => 'cre_next_fup_date', 'headerName' => 'Next FUP Date'],
            ['field' => 'cre_next_fup_time', 'headerName' => 'Next FUP Time'],
            ['field' => 'cre_next_fup_remarks', 'headerName' => 'FUP Remarks'],
            ['field' => 'x8_quotation_no', 'headerName' => 'X8 Quotation No.'],
            ['field' => 'x8_booking_no', 'headerName' => 'X8 Booking No.'],
            ['field' => 'x8_booking_date', 'headerName' => 'X8 Booking Date'],
            ['field' => 'oem_booking_no', 'headerName' => 'OEM Booking No.'],
            ['field' => 'oem_booking_date', 'headerName' => 'OEM Booking Date'],
            ['field' => 'oem_otf_no', 'headerName' => 'OEM OTF No.'],
            ['field' => 'oem_test_drive_no', 'headerName' => 'OEM Test Drive No.'],
            [
                'field'         => 'action',
                'headerName'    => 'Action',
                'width'         => 220,
                'minWidth'      => 220,
                'pinned'        => 'right',
                'sortable'      => false,
                'filter'        => false,
                'cellClass'     => 'text-center p-0'
            ]
        ];

        // Specific grid override structures
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

        // Core base columns for All, Long, Quick, Exchange, Scrappage
        $baseCols = [
            ['field' => 'serial_no', 'headerName' => 'S.No.'],
            ['field' => 'x8_enquiry_no', 'headerName' => 'X8 Enquiry No.'],
            ['field' => 'x8_enquiry_date', 'headerName' => 'X8 Enquiry Date'],
            ['field' => 'x8_enquiry_assign_date', 'headerName' => 'X8 Enquiry Assign Date'],
            ['field' => 'oem_enquiry_no', 'headerName' => 'OEM Enquiry No.'],
            ['field' => 'oem_enquiry_date', 'headerName' => 'OEM Enquiry Date'],
            ['field' => 'oem_enquiry_assign_date', 'headerName' => 'OEM Enquiry Assign Date'], 
        ];

        // Dynamically add only the relevant columns to clear out "Unnecessary Fields"
        if ($type === 'all' || in_array($type, ['exchange', 'scrappage', 'exchange_not_interested'])) {
            $baseCols = array_merge($baseCols, [
                ['field' => 'oem_quick_enquiry_no', 'headerName' => 'OEM Quick Enquiry No.'],
                ['field' => 'oem_quick_enquiry_date', 'headerName' => 'OEM Quick Enquiry Date'],
                ['field' => 'oem_quick_enquiry_assign_date', 'headerName' => 'OEM Quick Enquiry Assign Date'],
            ]);
        } elseif ($type === 'quick') {
            $baseCols = array_merge($baseCols, [
                ['field' => 'oem_quick_enquiry_no', 'headerName' => 'OEM Quick Enquiry No.'],
                ['field' => 'oem_quick_enquiry_date', 'headerName' => 'OEM Quick Enquiry Date'],
            ]);
        } elseif ($type === 'long') {
            $baseCols = array_merge($baseCols, [
                ['field' => 'oem_long_enquiry_no', 'headerName' => 'OEM Long Enquiry No.'],
                ['field' => 'oem_long_enquiry_date', 'headerName' => 'OEM Long Enquiry Date'],
                ['field' => 'oem_long_enquiry_assign_date', 'headerName' => 'OEM Enquiry Assign Date'], // RENAMED
            ]);
        }

        $midCols = [
            ['field' => 'segment_name', 'headerName' => 'Segment'],
            ['field' => 'model_name', 'headerName' => 'Model'],
            ['field' => 'variant_name', 'headerName' => 'Variant'],
            ['field' => 'color_name', 'headerName' => 'Color'],
            ['field' => 'first_name', 'headerName' => 'First Name'],
            ['field' => 'last_name', 'headerName' => 'Last Name'],
            ['field' => 'full_name', 'headerName' => 'Full Name'],
            ['field' => 'mobile', 'headerName' => 'Mobile'],
            ['field' => 'email', 'headerName' => 'Email'],
            ['field' => 'gender', 'headerName' => 'Gender'],
            ['field' => 'enquiry_type', 'headerName' => 'Enquiry Type'],
            ['field' => 'source_name', 'headerName' => 'Source'],
            ['field' => 'sub_source', 'headerName' => 'Sub Source'],
            ['field' => 'likely_purchase_in_days', 'headerName' => 'Likely Purchase In Days'],
            ['field' => 'fuel_type', 'headerName' => 'Fuel Type'],
            ['field' => 'transmission', 'headerName' => 'Transmission'],
            ['field' => 'drivetrain', 'headerName' => 'Drivetrain'],
            ['field' => 'seating', 'headerName' => 'Seating'],
            ['field' => 'territory', 'headerName' => 'Territory'], // Req 1
            ['field' => 'tehsil', 'headerName' => 'Tehsil'],
            ['field' => 'district', 'headerName' => 'District'],
            ['field' => 'city', 'headerName' => 'State'], // Req 3: City renamed to State internally for columns
            ['field' => 'sc_code', 'headerName' => 'Sales Consultant'], 
            ['field' => 'dealer_branch', 'headerName' => 'Dealer Branch'],
            ['field' => 'dealer_location', 'headerName' => 'Dealer Location'],
            ['field' => 'followup_type', 'headerName' => 'Followup Type'],
            ['field' => 'followup_date', 'headerName' => 'Followup Date'],
            ['field' => 'followup_time', 'headerName' => 'Followup Time'],
            ['field' => 'occupation_type', 'headerName' => 'Occupation Type'],
            ['field' => 'customer_type', 'headerName' => 'Customer Type'],
            ['field' => 'occupation_sub_type', 'headerName' => 'Occupation Sub Type'],
            ['field' => 'company_name', 'headerName' => 'Company Name'],
            ['field' => 'dob', 'headerName' => 'D.O.B.'],
            ['field' => 'marital_status', 'headerName' => 'Marital Status'],
            ['field' => 'marriage_date', 'headerName' => 'Marriage Date'],
            ['field' => 'age_group', 'headerName' => 'Age Group'],
            ['field' => 'usage_area', 'headerName' => 'Usage Area'],
            ['field' => 'km_travelled_daily', 'headerName' => 'KM Travelled Daily'], 
            ['field' => 'application_type', 'headerName' => 'Application Type'],
            ['field' => 'application', 'headerName' => 'Application'],
            ['field' => 'pincode', 'headerName' => 'Pincode'],
            ['field' => 'address', 'headerName' => 'Address'],
            ['field' => 'has_ev', 'headerName' => 'Has EV'],
            ['field' => 'purchase_type', 'headerName' => 'Purchase Type'],
            ['field' => 'remarks', 'headerName' => 'Remarks'],
            ['field' => 'consider_make', 'headerName' => 'Consideration Make'],
            ['field' => 'consider_model', 'headerName' => 'Consideration Model'],
            ['field' => 'consider_variant', 'headerName' => 'Consideration Variant'],
            ['field' => 'expected_price', 'headerName' => 'Expected Price'],
            ['field' => 'offered_price', 'headerName' => 'Offered Price'],
            ['field' => 'exchange_bonus', 'headerName' => 'Exchange Bonus'],
            ['field' => 'price_gap', 'headerName' => 'Price Gap']
        ];

        // Clean up "unnecessary fields" based on type to declutter customise headers menu
        if ($type === 'quick') {
            $remove = ['drivetrain', 'seating', 'tehsil', 'district', 'occupation_sub_type', 'company_name', 'dob', 'marital_status', 'marriage_date', 'age_group', 'usage_area', 'km_travelled_daily', 'application_type', 'application', 'pincode', 'address', 'has_ev', 'consider_make', 'consider_model', 'consider_variant', 'full_name'];
            $midCols = array_values(array_filter($midCols, fn($c) => !in_array($c['field'], $remove)));
        } elseif ($type === 'long') {
            $remove = ['drivetrain', 'seating', 'usage_area', 'application_type', 'application', 'has_ev', 'full_name'];
            $midCols = array_values(array_filter($midCols, fn($c) => !in_array($c['field'], $remove)));
        }

        return array_merge($baseCols, $midCols, $commonEnd);
    }

    // =========================================================
    // SEARCH & SORT LOGIC
    // =========================================================

    private function applyEnquirySearch($query, string $searchText): void
    {
        if ($searchText === '') return;

        $like = "%{$searchText}%";
        $isXenq = str_starts_with(strtoupper($searchText), 'XENQ-');
        $xenqId = $isXenq ? (int) substr(strtoupper($searchText), 5) : null;

        $query->where(function ($q) use ($like, $xenqId, $isXenq, $searchText) {
            if ($isXenq && $xenqId) {
                // If user types exactly XENQ-123 in search box
                $q->where('id', $xenqId);
            } else {
                // Regular multi-column search
                $q->where('id', (int) $searchText) // Fallback for raw IDs
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

    // =========================================================
    // COLUMN FILTER LOGIC (ag-Grid default column filters)
    // =========================================================

    /**
     * Maps ag-Grid field names that don't match a real DB column
     * directly to the actual column (accessor-redirected fields,
     * relation "name" columns, etc). null = not filterable (computed field).
     */
    private const FILTER_FIELD_MAP = [
        'full_name' => null, // computed accessor, not a column
        'action' => null,
        'source_name' => 'source_code',
        'segment_name' => null, // handled via relation below
        'model_name' => null,   // handled via relation below
        'variant_name' => null, // handled via relation below
        'color_name' => null,   // handled via relation below
        'exchange_make' => 'brand_make',
        'exchange_model' => 'brand_model',
        'consider_make' => 'consid_brand',
        'consider_model' => 'consid_model',
        'consider_variant' => 'consid_variant',
        'likely_purchase_in_days' => 'likely_purchase_date',
    ];

    /**
     * ag-Grid field => matches the same code-first, raw-column-fallback
     * priority used for display: if the *_code column is filled, filter
     * against the raw text column (segment/model/variant/color).
     */
    private const FILTER_CODE_COLUMN_MAP = [
        'segment_name' => ['code' => 'segment_code', 'relation' => 'segment', 'relCols' => ['name'], 'rawCol' => 'segment'],
        'model_name' => ['code' => 'model_code', 'relation' => 'model', 'relCols' => ['name'], 'rawCol' => 'model'],
        'variant_name' => ['code' => 'variant_code', 'relation' => 'variant', 'relCols' => ['display_name', 'custom_name', 'oem_name'], 'rawCol' => 'variant'],
        'color_name' => ['code' => 'color_code', 'relation' => 'color', 'relCols' => ['name'], 'rawCol' => 'color'],
    ];

    private function applyEnquiryFilter($query, array $filterModel): void
    {
        if (empty($filterModel))
            return;

        foreach ($filterModel as $field => $condition) {
            if (!is_array($condition))
                continue;

            // Code-priority columns (e.g. model_name -> model.name if model_code filled, else raw `model` column)
            if (isset(self::FILTER_CODE_COLUMN_MAP[$field])) {
                $map = self::FILTER_CODE_COLUMN_MAP[$field];
                $query->where(function ($q) use ($map, $condition) {
                    // Code filled -> match via related table
                    $q->where(function ($q2) use ($map, $condition) {
                        $q2->whereNotNull($map['code'])->where($map['code'], '!=', '')
                            ->whereHas($map['relation'], function ($q3) use ($map, $condition) {
                                $this->applyFilterConditionAnyColumn($q3, $map['relCols'], $condition);
                            });
                    })
                        // Code blank -> match raw text column instead
                        ->orWhere(function ($q2) use ($map, $condition) {
                            $q2->where(function ($q3) use ($map) {
                                $q3->whereNull($map['code'])->orWhere($map['code'], '');
                            });
                            $this->applyFilterCondition($q2, $map['rawCol'], $condition);
                        });
                });
                continue;
            }

            // Redirected / renamed columns, or explicitly non-filterable
            $column = array_key_exists($field, self::FILTER_FIELD_MAP)
                ? self::FILTER_FIELD_MAP[$field]
                : $field;

            if ($column === null)
                continue; // not filterable

            $this->applyFilterCondition($query, $column, $condition);
        }
    }

    /** Applies the same filter condition across multiple columns, OR'd together. */
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

    /**
     * Applies a single ag-Grid text-filter condition to a query.
     * Supports the standard agTextColumnFilter operator set.
     */
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

    // =========================================================
    // CREATE / EDIT / STORE / UPDATE
    // =========================================================

    public function create()
    {
        return view('admin.enquiry.create', ['title' => 'Add New Enquiry'] + $this->getEnquiryFormData());
    }
    public function createReference()
    {
        return view('admin.enquiry.reference-create', ['title' => 'Add Reference Enquiry'] + $this->getEnquiryFormData());
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

    // --- Exchange Edit / Update specific methods ---
    public function exchangeEnquiryEdit($id)
    {
        $enquiry = Enquiry::with(['segment', 'model', 'variant'])->findOrFail($id);
        $existing_car_oems = OrgService::keywordValueByCode('EXISTING_CAR_OEM');
        return view('admin.enquiry.exchange_edit', compact('enquiry', 'existing_car_oems'));
    }

    public function exchangeEnquiryUpdate(Request $request, $id)
    {
        $enquiry = Enquiry::findOrFail($id);
        $enquiry->update($request->only([
            'brand_make', 'brand_model', 'vehicle_no', 'lost_reason',
            'make_year', 'odo_reading', 'expected_price', 'offered_price', 'exchange_bonus'
        ]));
        Alert::success('Exchange Details Updated successfully.')->flash();
        
        // Return to whichever list they came from
        if($enquiry->purchase_type === 'Scrappage') {
            return redirect(backpack_url('exchange/enquiry/int-in-scrappage'));
        }
        return redirect(backpack_url('exchange/enquiry/int-in-exchange'));
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
            'mobile' => 'required|max:15', // Kept required because it always submits via hidden input
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
            'bpo' => 'nullable|max:150', // VPO mapped functionally
            'tehsil' => 'nullable|max:100',
            'district' => 'nullable|max:100',
            'city' => 'nullable|max:100', // State mapped functionally
            'territory' => 'nullable|string|max:100', // Req 1
            'has_ev' => 'nullable',
            'purchase_type' => 'nullable',
            'purchase_type_crm' => 'nullable|string|max:100', // Req 4
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
            
            // Follow Up Rules (Req 7, 9, 11, 13)
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
            
            // CRE Follow up
            'cre_enquiry_stage' => 'nullable|string|max:100',
            'cre_next_fup_date' => 'nullable|date',
            'cre_next_fup_time' => 'nullable',
            'cre_next_fup_remarks' => 'nullable|string|max:255',

            // Financial & Exchange Rules
            'make_year' => 'nullable|integer',
            'odo_reading' => 'nullable|numeric',
            'expected_price' => 'nullable|numeric',
            'offered_price' => 'nullable|numeric',
            'exchange_bonus' => 'nullable|numeric',
            'fin_mode' => 'nullable|string|max:50',
            'financier' => 'nullable|integer', // Req 5: Nullable/Optional
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

    // =========================================================
    // AJAX & HELPERS
    // =========================================================

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

    // =========================================================
    // EXCEL IMPORT 
    // =========================================================

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
}