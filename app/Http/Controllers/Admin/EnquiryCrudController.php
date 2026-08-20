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
use Illuminate\Support\Facades\Cache;
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

    /**
     * Cached Lookup Maps for Grid Enrichment
     */
    private function getEnquiryLookupMaps(): array
    {
        return Cache::remember('enquiry_lookup_maps_v2', now()->addMinutes(10), function () {
            $lpMap = collect(OrgService::keywordValueByCode('LIKELY_PURCHASE_DATE'))
                ->pluck('value', 'code')
                ->toArray();

            $fuelMap = collect(OrgService::getKeyValuesByCode('FUEL_TYPE'))
                ->pluck('value', 'id')
                ->toArray();

            $finMap = collect(XlFinancier::select('id', 'name')->get())
                ->pluck('name', 'id')
                ->toArray();

            $branchesMap = collect(OrgService::branches())->toArray();
            $segmentsMap = collect(OrgService::segments())->toArray();

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

            $scByCode = [];
            $scByMileId = [];
            $scNamesByCode = [];

            foreach ($scUsers as $user) {
                $userName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: ($user['name'] ?? '—');

                if (!empty($user['employee_code'])) {
                    $scByCode[$user['employee_code']] = $user;
                    $scNamesByCode[$user['employee_code']] = $userName;
                }
                if (!empty($user['person_code'])) {
                    $scByCode[$user['person_code']] = $user;
                    $scNamesByCode[$user['person_code']] = $userName;
                }
                if (!empty($user['mile_id'])) {
                    $scByMileId[$user['mile_id']] = $user;
                }
            }

            $fupTypesMap = collect(OrgService::keywordValueByCode('FOLLOW_UP_TYPE'))
                ->pluck('value', 'code')
                ->toArray();

            return compact(
                'lpMap',
                'fuelMap',
                'finMap',
                'branchesMap',
                'segmentsMap',
                'scByCode',
                'scByMileId',
                'scNamesByCode',
                'fupTypesMap'
            );
        });
    }

    private function resolveMapType(string $listType): string
    {
        return match (true) {
            in_array($listType, ['assigned_long', 'unassigned_long']) => 'long',
            in_array($listType, ['assigned_quick', 'unassigned_quick']) => 'quick',
            $listType === 'otf' => 'otf',
            in_array($listType, [
                'reference',
                'virtual',
                'whatsapp',
                'hyperlocal',
                'exchange',
                'scrappage',
                'exchange_not_interested',
                'finance',
                'finance_not_interested',
            ]) => $listType,
            default => 'all',
        };
    }

    private function getBaseQuery(string $listType)
    {
        if ($listType === 'otf') {
            return \App\Models\Module\Booking\Booking::withoutGlobalScope(\Illuminate\Database\Eloquent\SoftDeletingScope::class)
                ->from('xlr8_crm_booking as crm_booking')
                ->select([
                    'crm_booking.id',
                    'crm_booking.booking_date',
                    'crm_booking.status',
                    'crm_booking.cancellation_date',
                    'crm_booking.sc_mile_id as sc_code',
                    'crm_booking.oem_code',
                    'crm_booking.segment_code',
                    'crm_booking.model_code',
                    'crm_booking.variant_code',
                    'crm_booking.color_code',
                    'crm_booking.invoice_no',
                    'crm_booking.evaluation_id',
                    'crm_booking.so_number',
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
                ->where('crm_booking.is_active', 1);
        }

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

        return $query->with(['segment', 'model', 'variant', 'color', 'campaign']);
    }

    /**
     * Latest CRE follow-up row per enquiry, batched for a page/chunk of enquiries
     */
    private function getLatestCreFups(array $enquiryIds): array
    {
        if (empty($enquiryIds)) {
            return [];
        }

        $x8Nos = array_map(fn($id) => 'XENQ-' . $id, $enquiryIds);

        $rows = DB::table('xlr8_cre_enquiry_fup')
            ->whereIn('x8_enq_no', $x8Nos)
            ->orderByDesc('id')
            ->get();

        $latest = [];
        foreach ($rows as $row) {
            if (!isset($latest[$row->x8_enq_no])) {
                $latest[$row->x8_enq_no] = $row;
            }
        }

        return $latest;
    }

    private function formatDate(?string $date, string $format): string
    {
        if (empty($date) || str_starts_with($date, '0000')) {
            return '—';
        }
        try {
            return Carbon::parse($date)->format($format);
        } catch (\Throwable $th) {
            return '—';
        }
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

        $highlightCounts = Cache::remember('enquiry_highlight_counts', now()->addMinutes(2), function () use ($filters) {
            $counts = [];
            foreach ($filters as $filter) {
                $query = Enquiry::query();
                OrgService::applyHighlightFilter($query, $filter);
                $counts[$filter] = $query->count();
            }
            return $counts;
        });

        return view('admin.enquiry.list', [
            'title' => 'Xlr8 Enquiries',
            'gridConfig' => [
                'columns' => $this->getColumns('all'),
                'defaultColumns' => $this->getDefaultColumns(),
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

        $query = $this->getBaseQuery($listType);

        if ($listType === 'otf') {
            $this->applyOtfSearch($query, $searchText);
            $this->applyOtfFilter($query, $filterModel);
            $this->applyOtfSort($query, (array) $request->input('sortModel', []));
        } else {
            $this->applyEnquirySearch($query, $searchText);
            $this->applyEnquirySort($query, (array) $request->input('sortModel', []));
            $this->applyEnquiryFilter($query, $filterModel);
            OrgService::applyHighlightFilter($query, $highlightFilter);
        }

        $total = (clone $query)->count();
        $mapType = $this->resolveMapType($listType);
        $lookups = $this->getEnquiryLookupMaps();

        $pageRows = $query->skip($startRow)->take($limit)->get();

        if ($listType !== 'otf') {
            $lookups['creFups'] = $this->getLatestCreFups($pageRows->pluck('id')->all());
        }

        $gridData = $pageRows
            ->map(fn($e, $i) => $this->mapData($e, $startRow + $i, $mapType, $lookups))
            ->all();

        return response()->json(['rows' => $gridData, 'lastRow' => $total]);
    }

    public function export(Request $request)
    {
        $searchText = trim((string) $request->input('searchText', ''));
        $highlightFilter = trim((string) $request->input('highlightFilter', ''));
        $filterModel = json_decode((string) $request->input('filterModel', '{}'), true) ?: [];
        $listType = trim((string) $request->input('list_type', 'all'));

        $query = $this->getBaseQuery($listType);

        if ($listType === 'otf') {
            $this->applyOtfSearch($query, $searchText);
            $this->applyOtfFilter($query, $filterModel);
            $this->applyOtfSort($query, (array) $request->input('sortModel', []));
            $query->orderByDesc('crm_booking.id');
        } else {
            $this->applyEnquirySearch($query, $searchText);
            $this->applyEnquirySort($query, (array) $request->input('sortModel', []));
            $this->applyEnquiryFilter($query, $filterModel);
            OrgService::applyHighlightFilter($query, $highlightFilter);
            $query->orderByDesc('created_at');
        }

        $mapType = $this->resolveMapType($listType);
        $columns = array_values(array_filter(
            $this->getColumns($mapType),
            fn($col) => ($col['field'] ?? null) !== 'action'
        ));

        $lookups = $this->getEnquiryLookupMaps();

        $fileName = $listType === 'otf'
            ? 'otf-bookings-' . now()->format('Y-m-d_His') . '.csv'
            : 'enquiries-' . now()->format('Y-m-d_His') . '.csv';

        return response()->streamDownload(function () use ($query, $columns, $mapType, $lookups) {
            $out = fopen('php://output', 'w');

            fputcsv($out, array_merge(['S.No.'], array_map(fn($c) => $c['headerName'], $columns)));

            $serial = 0;
            $query->chunk(500, function ($chunk) use ($out, &$serial, $columns, $mapType, $lookups) {
                if ($mapType !== 'otf') {
                    $lookups['creFups'] = $this->getLatestCreFups($chunk->pluck('id')->all());
                }
                foreach ($chunk as $e) {
                    $rowData = $this->mapData($e, $serial, $mapType, $lookups);
                    $serial++;
                    fputcsv($out, array_merge(
                        [$serial],
                        array_map(fn($c) => $rowData[$c['field']] ?? '', $columns)
                    ));
                }
            });

            fclose($out);
        }, $fileName, ['Content-Type' => 'text/csv']);
    }

    private function applyOtfSearch($query, string $searchText): void
    {
        if ($searchText === '') return;
        $like = "%{$searchText}%";
        $query->where(function ($q) use ($like, $searchText) {
            $q->where('crm_booking.id', (int) $searchText)
                ->orWhere('crm_booking.otf_no', 'like', $like)
                ->orWhere('crm_booking.customer_name', 'like', $like)
                ->orWhere('crm_booking.customer_code', 'like', $like)
                ->orWhere('crm_booking.customer_pan', 'like', $like)
                ->orWhere('crm_booking.customer_aadhar', 'like', $like);
        });
    }

    private const OTF_SORT_COLUMN_MAP = [
        'booking_no' => 'crm_booking.id',
        'booking_date' => 'crm_booking.booking_date',
        'booking_status' => 'crm_booking.status',
        'cancellation_date' => 'crm_booking.cancellation_date',
        'customer_name' => 'crm_booking.customer_name',
        'customer_code' => 'crm_booking.customer_code',
        'otf_number' => 'crm_booking.otf_no',
    ];

    private function applyOtfSort($query, array $sortModel): void
    {
        $applied = false;
        foreach ($sortModel as $sort) {
            $colId = $sort['colId'] ?? null;
            if ($colId && isset(self::OTF_SORT_COLUMN_MAP[$colId])) {
                $query->orderBy(self::OTF_SORT_COLUMN_MAP[$colId], strtolower($sort['sort'] ?? 'asc') === 'desc' ? 'desc' : 'asc');
                $applied = true;
            }
        }
        if (!$applied) $query->orderByDesc('crm_booking.id');
    }

    private const OTF_FILTER_FIELD_MAP = [
        'booking_no' => 'crm_booking.id',
        'booking_date' => 'crm_booking.booking_date',
        'sc_code' => 'crm_booking.sc_mile_id',
        'booking_status' => 'crm_booking.status',
        'cancellation_date' => 'crm_booking.cancellation_date',
        'customer_code' => 'crm_booking.customer_code',
        'customer_name' => 'crm_booking.customer_name',
        'customer_address' => 'crm_booking.customer_address',
        'customer_city' => 'crm_booking.customer_city',
        'customer_tehsil' => 'crm_booking.customer_tehsil',
        'customer_district' => 'crm_booking.customer_district',
        'pan_number' => 'crm_booking.customer_pan',
        'tan_number' => 'crm_booking.customer_tan',
        'aadhaar_number' => 'crm_booking.customer_aadhar',
        'otf_number' => 'crm_booking.otf_no',
    ];

    private function applyOtfFilter($query, array $filterModel): void
    {
        foreach ($filterModel as $field => $condition) {
            if (!is_array($condition) || !isset(self::OTF_FILTER_FIELD_MAP[$field])) continue;
            $this->applyFilterCondition($query, self::OTF_FILTER_FIELD_MAP[$field], $condition);
        }
    }

    public function referenceList()
    {
        return $this->renderGridPage('admin.enquiry.reference-enquiry', 'Reference Enquiries', 'reference');
    }

    public function virtualNumberList()
    {
        return $this->renderGridPage('admin.enquiry.virtual-number-enquiry', 'Virtual Number Enquiries', 'virtual');
    }

    public function hyperlocalList()
    {
        $this->crud->hasAccessOrFail('list');

        $count = (clone Enquiry::hyperlocal())->count();

        return $this->renderGridPage(
            'admin.enquiry.hyperlocal-enquiry',
            'Hyperlocal Enquiries (' . $count . ')',
            'hyperlocal'
        );
    }

    public function whatsappCampaignList()
    {
        return $this->renderGridPage('admin.enquiry.whatsapp-campaign-enquiry', 'WhatsApp Campaign Enquiries', 'whatsapp');
    }

    public function assignedLongList()
    {
        return $this->renderGridPage('admin.enquiry.enquiry-grid', 'Assigned Long Enquiries', 'assigned_long');
    }

    public function unassignedLongList()
    {
        return $this->renderGridPage('admin.enquiry.enquiry-grid', 'Unassigned Long Enquiries', 'unassigned_long');
    }

    public function assignedQuickList()
    {
        return $this->renderGridPage('admin.enquiry.enquiry-grid', 'Assigned Quick Enquiries', 'assigned_quick');
    }

    public function unassignedQuickList()
    {
        return $this->renderGridPage('admin.enquiry.enquiry-grid', 'Unassigned Quick Enquiries', 'unassigned_quick');
    }

    public function exchangeEnquiryList()
    {
        return $this->renderGridPage('admin.enquiry.exchange', 'Int in Exchange Dashboard', 'exchange');
    }

    public function scrappageEnquiryList()
    {
        return $this->renderGridPage('admin.enquiry.exchange', 'Int in Scrappage Dashboard', 'scrappage');
    }

    public function exchangeNotInterestedList()
    {
        return $this->renderGridPage('admin.enquiry.exchange', 'Not Interested in Exchange Dashboard', 'exchange_not_interested');
    }

    public function financeEnquiryList()
    {
        return $this->renderGridPage('admin.enquiry.finance-list', 'Enquiries - Int in Finance', 'finance');
    }

    public function financeNotInterestedList()
    {
        return $this->renderGridPage('admin.enquiry.finance-list', 'Enquiries - Finance Not Interested', 'finance_not_interested');
    }

    private function renderGridPage(string $view, string $title, string $listType)
    {
        $this->crud->hasAccessOrFail('list');
        $this->crud->setListView($view);

        $mapType = $this->resolveMapType($listType);
        $columnsType = in_array($mapType, ['quick', 'long']) ? 'all' : $mapType;

        return view($view, [
            'title' => $title,
            'listType' => $listType,
            'segments' => OrgService::segments(),
            'gridConfig' => [
                'list_type' => $listType,
                'columns' => $this->getColumns($columnsType),
                'defaultColumns' => $this->getDefaultColumns(),
            ],
        ]);
    }

    private function getAssignedSc($code, $mileId, $scByCode, $scByMileId): ?array
    {
        if (!empty($code) && isset($scByCode[$code])) {
            return $scByCode[$code];
        }

        if (!empty($mileId) && isset($scByMileId[$mileId])) {
            return $scByMileId[$mileId];
        }

        return null;
    }

    private function mapData($e, $i, $type, array $lookups)
    {
        $lpMap = $lookups['lpMap'] ?? [];
        $fuelMap = $lookups['fuelMap'] ?? [];
        $finMap = $lookups['finMap'] ?? [];
        $branchesMap = $lookups['branchesMap'] ?? [];
        $scByCode = $lookups['scByCode'] ?? [];
        $scByMileId = $lookups['scByMileId'] ?? [];
        $scNamesByCode = $lookups['scNamesByCode'] ?? [];
        $fupTypesMap = $lookups['fupTypesMap'] ?? [];

        $x8AssignedSc = $this->getAssignedSc($e->x8_sc_code ?? null, $e->x8_sc_mile_id ?? null, $scByCode, $scByMileId);
        $oemAssignedSc = $this->getAssignedSc($e->sc_code ?? null, $e->sc_mile_id ?? null, $scByCode, $scByMileId);

        $segmentRel = $e instanceof \Illuminate\Database\Eloquent\Model && $e->relationLoaded('segment') ? $e->getRelation('segment') : null;
        $modelRel = $e instanceof \Illuminate\Database\Eloquent\Model && $e->relationLoaded('model') ? $e->getRelation('model') : null;
        $variantRel = $e instanceof \Illuminate\Database\Eloquent\Model && $e->relationLoaded('variant') ? $e->getRelation('variant') : null;
        $colorRel = $e instanceof \Illuminate\Database\Eloquent\Model && $e->relationLoaded('color') ? $e->getRelation('color') : null;

        $editUrl = backpack_url("enquiry/{$e->id}/edit");
        $quotUrl = backpack_url("quotation-form/create?id={$e->id}");
        $bookUrl = backpack_url("booking/create?enquiry_id={$e->id}");

        if ($type === 'hyperlocal') {
            return [
                'serial_no' => $i + 1,
                'lead_id' => $e->lead_id ?? $e->id ?? '—',
                'name' => trim(($e->first_name ?? '') . ' ' . ($e->last_name ?? '')) ?: ($e->name ?? '—'),
                'email' => $e->email ?? '—',
                'phone_number' => $e->mobile ?? $e->phone_number ?? '—',
                'call_start_time' => $this->formatDate($e->call_start_time ?? $e->created_at, 'd-M-Y H:i:s'),
                'call_end_time' => $this->formatDate($e->call_end_time, 'd-M-Y H:i:s'),
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
                'enquiry_date' => $this->formatDate($e->enquiry_date ?? $e->created_at, 'd-M-Y'),
                'model' => $e->model_name ?? $e->model ?? '—',
                'dealer_code' => $e->dealer_code ?? $e->dealer_branch ?? '—',
                'dms_enquiry_stage' => $e->stage ?? '—',
                'cre_enquiry_stage' => '—',
                'cre_next_fup_date' => '—',
                'cre_next_fup_time' => '—',
                'cre_next_fup_remarks' => '—',
                'x8_quotation_no' => $e->x8_quotation_no ?? $e->quotation_no ?? '—',
                'x8_booking_no' => $e->x8_booking_no ?? $e->booking_no ?? '—',
                'x8_booking_date' => $this->formatDate($e->x8_booking_date ?? $e->booking_date, 'd-M-Y'),
                'oem_booking_no' => $e->oem_booking_no ?? '—',
                'oem_booking_date' => $this->formatDate($e->oem_booking_date, 'd-M-Y'),
                'oem_otf_no' => $e->oem_otf_no ?? '—',
                'oem_test_drive_no' => $e->oem_test_drive_no ?? $e->test_drive_no ?? '—',
                'territory' => $e->territory ?? '—',
                'fup_count' => $e->fup_count ?? '—',
                'td_date' => $this->formatDate($e->td_date, 'd-M-Y'),
                'lost_reason' => $e->lost_reason ?? '—',
                'followup_status' => $e->fup_status ?? '—',
                'action' => '<div class="d-flex justify-content-center gap-2">'
                    . '<a href="' . $editUrl . '" class="btn btn-sm btn-primary">Edit</a>'
                    . '<a href="' . $quotUrl . '" class="btn btn-sm btn-success">Quote</a>'
                    . '<a href="' . $bookUrl . '" class="btn btn-sm btn-warning" title="Convert to Booking">Process</a>'
                    . '</div>',
            ];
        }

        if ($type === 'otf') {
            $editUrl = backpack_url("booking/{$e->id}/edit");

            return [
                'serial_no'         => $i + 1,
                'booking_no'        => $e->id ?? '—',
                'booking_date'      => $this->formatDate($e->booking_date, 'd-M-Y'),
                'sc_code'           => $e->sc_code ?? '—',
                'booking_status'    => $e->status ?? '—',
                'cancellation_date' => $this->formatDate($e->cancellation_date, 'd-M-Y'),
                'model_group'       => $e->oem_code ?? '—',
                'variant'           => $e->variant_code ?? '—',
                'oem_model_code'    => $e->oem_code ?? '—',
                'customer_code'     => $e->customer_code ?? '—',
                'customer_name'     => $e->customer_name ?? '—',
                'customer_address'  => $e->customer_address ?? '—',
                'customer_city'     => $e->city ?? '—',
                'customer_tehsil'   => $e->tehsil ?? '—',
                'customer_district' => $e->district ?? '—',
                'pan_number'        => $e->pan_no ?? '—',
                'tan_number'        => $e->tan_no ?? '—',
                'aadhaar_number'    => $e->adhar_no ?? '—',
                'invoice_no'        => $e->invoice_no ?? '—',
                'evaluation_id'     => $e->evaluation_id ?? '—',
                'so_number'         => $e->so_number ?? '—',
                'otf_number'        => $e->otf_number ?? '—',
                'action'            => '<div class="d-flex justify-content-center gap-2"><a href="' . $editUrl . '" class="btn btn-sm btn-primary">Edit</a></div>',
            ];
        }

        $actionBtns = '<a href="' . $editUrl . '" class="btn btn-sm btn-primary">Edit</a>';

        if ($type === 'all') {
            $actionBtns .= '<a href="' . $quotUrl . '" class="btn btn-success btn-sm">Quote</a>';
            $actionBtns .= '<a href="' . $bookUrl . '" class="btn btn-warning btn-sm" title="Convert to Booking">Process</a>';
        } elseif (in_array($type, ['exchange', 'scrappage', 'exchange_not_interested'])) {
            $exchUrl = backpack_url("exchange/enquiry/{$e->id}/edit");
            $actionBtns = '<a href="' . $exchUrl . '" class="btn btn-sm btn-primary">Process</a>';
        } elseif (in_array($type, ['finance', 'finance_not_interested'])) {
            $finUrl = backpack_url("finance/enquiry/{$e->id}/edit");
            $actionBtns = '<a href="' . $finUrl . '" class="btn btn-sm btn-primary">Process</a>';
        }

        $row = [
            'serial_no' => $i + 1,
            'x8_enquiry_no' => 'XENQ-' . $e->id,
            'x8_enquiry_date' => $this->formatDate($e->created_at, 'd-M-Y H:i'),
            'x8_enquiry_assign_date' => $this->formatDate($e->x8_enquiry_assign_date ?? $e->enq_assign_date, 'd-M-Y'),
            'oem_enquiry_no' => $e->x8_enquiry_no ?? $e->enquiry_no ?? $e->oem_enquiry_no ?? '—',
            'oem_enquiry_date' => $this->formatDate($e->x8_enquiry_date ?? $e->enquiry_date ?? $e->oem_enquiry_date, 'd-M-Y'),
            'oem_enquiry_assign_date' => $this->formatDate($e->oem_enquiry_assign_date ?? $e->enq_assign_date, 'd-M-Y'),
            'segment_name' => $e->segment_code ? ($segmentRel?->name ?? $e->segment ?? $e->segment_code) : ($e->segment ?? '—'),
            'model_name' => $e->model_code ? ($modelRel?->name ?? $e->model ?? $e->model_code) : ($e->model ?? '—'),
            'variant_name' => $e->variant_code ? ($variantRel?->display_name ?? $variantRel?->custom_name ?? $variantRel?->oem_name ?? $e->variant ?? $e->variant_code) : ($e->variant ?? '—'),
            'color_name' => $e->color_code ? ($colorRel?->name ?? $e->color ?? $e->color_code) : ($e->color ?? '—'),
            'mobile' => $e->mobile ?? '—',
            'alternate_mobile' => $e->alternate_mobile ?? '—',
            'dms_enquiry_stage' => $e->stage ?? '—',
            'cre_enquiry_stage' => '—',
            'cre_next_fup_date' => '—',
            'cre_next_fup_time' => '—',
            'cre_next_fup_remarks' => '—',
            'x8_quotation_no' => $e->x8_quotation_no ?? $e->quotation_no ?? '—',
            'x8_booking_no' => $e->x8_booking_no ?? $e->booking_no ?? '—',
            'x8_booking_date' => $this->formatDate($e->x8_booking_date ?? $e->booking_date, 'd-M-Y'),
            'oem_booking_no' => $e->oem_booking_no ?? '—',
            'oem_booking_date' => $this->formatDate($e->oem_booking_date, 'd-M-Y'),
            'oem_otf_no' => $e->oem_otf_no ?? '—',
            'oem_test_drive_no' => $e->oem_test_drive_no ?? $e->test_drive_no ?? '—',
            'territory' => $e->territory ?? '—',
            'fup_count' => $e->fup_count ?? '—',
            'td_date' => $this->formatDate($e->td_date, 'd-M-Y'),
            'lost_reason' => $e->lost_reason ?? '—',
            'followup_status' => $e->fup_status ?? '—',
            'action' => '<div class="d-flex justify-content-center gap-2">' . $actionBtns . '</div>',
        ];

        if (in_array($type, ['long', 'quick', 'all', 'reference', 'virtual', 'whatsapp', 'exchange', 'scrappage', 'exchange_not_interested', 'finance', 'finance_not_interested'])) {
            $x8BranchCode = $x8AssignedSc['primary_branch_code'] ?? null;
            $oemBranchCode = $oemAssignedSc['primary_branch_code'] ?? null;

            $creFup = $lookups['creFups']['XENQ-' . $e->id] ?? null;

            $row += [
                'oem_long_enquiry_no' => $e->oem_long_enquiry_no ?? '—',
                'oem_long_enquiry_date' => $this->formatDate($e->oem_long_enquiry_date, 'd-M-Y'),
                'oem_long_enquiry_assign_date' => $this->formatDate($e->oem_long_enquiry_assign_date, 'd-M-Y'),
                'oem_quick_enquiry_no' => $e->oem_quick_enquiry_no ?? $e->quick_enquiry_no ?? '—',
                'oem_quick_enquiry_date' => $this->formatDate($e->oem_quick_enquiry_date ?? $e->quick_enquiry_date, 'd-M-Y'),
                'oem_quick_enquiry_status' => $e->oem_quick_enquiry_status ?? $e->quick_status ?? '—',
                'oem_quick_enquiry_assign_date' => $this->formatDate($e->oem_quick_enquiry_assign_date ?? $e->quick_enq_assign_date, 'd-M-Y'),
                'x8_enq_source' => $e->x8_enq_source ?? $e->x8_source_code ?? '—',
                'first_name' => $e->first_name ?? '—',
                'last_name' => $e->last_name ?? '—',
                'full_name' => $e->full_name ?? trim(($e->first_name ?? '') . ' ' . ($e->last_name ?? '')),
                'email' => $e->email ?? '—',
                'gender' => $e->gender ?? '—',
                'enquiry_type' => $e->enquiry_type ?? '—',
                'source_code' => $e->source?->name ?? $e->source_code ?? '—',
                'sub_source' => $e->sub_source ?? '—',
                'likely_purchase_date' => $lpMap[$e->likely_purchase_date] ?? $e->likely_purchase_date ?? '—',
                'x8_quotation_date' => $this->formatDate($e->x8_quotation_date ?? $e->quotation_date, 'd-M-Y'),
                'fuel_type' => $fuelMap[$e->fuel_type] ?? $e->fuel_type ?? '—',
                'transmission' => $e->transmission ?? '—',
                'drivetrain' => $e->drivetrain ?? '—',
                'seating' => $e->seating ?? '—',
                'pincode' => $e->pincode ?? $e->zipcode ?? '—',
                'vpo' => $e->vpo ?? '—',
                'tehsil' => $e->tehsil ?? '—',
                'district' => $e->district ?? '—',
                'city' => $e->city ?? '—',
                'address' => $e->address ?? $e->customer_address ?? '—',
                'x8_sc_code' => $e->x8_sc_code ?? '—',
                'x8_sc_mile_id' => $e->x8_sc_mile_id ?? $x8AssignedSc['mile_id'] ?? '—',
                'x8_sc_branch' => $branchesMap[$x8BranchCode] ?? $x8BranchCode ?? '—',
                'x8_sc_location' => $x8AssignedSc['primary_loc_code'] ?? '—',
                'sc_code' => $scNamesByCode[$e->sc_code] ?? $e->sc_code ?? '—',
                'sc_mile_id' => $e->sc_mile_id ?? $oemAssignedSc['mile_id'] ?? '—',
                'oem_sc_branch' => $branchesMap[$oemBranchCode] ?? $oemBranchCode ?? '—',
                'oem_sc_location' => $oemAssignedSc['primary_loc_code'] ?? '—',
                'dealer_branch' => $e->dealer_branch ?? '—',
                'dealer_location' => $e->dealer_location ?? '—',
                'occupation_type' => $e->occupation_type ?? '—',
                'customer_type' => $e->customer_type ?? '—',
                'occupation_sub_type' => $e->occupation_sub_type ?? '—',
                'company_name' => $e->company_name ?? '—',
                'dob' => $this->formatDate($e->dob, 'd-M-Y'),
                'marital_status' => $e->marital_status ?? '—',
                'marriage_date' => $this->formatDate($e->marriage_date, 'd-M-Y'),
                'age_group' => $e->age_group ?? '—',
                'usage_area' => $e->usage_area ?? '—',
                'km_travelled_daily' => $e->km_travelled_daily ?? '—',
                'application_type' => $e->application_type ?? '—',
                'application' => $e->application ?? '—',
                'has_ev' => $e->has_ev ?? '—',
                'purchase_type' => $e->purchase_type ?? '—',
                'consid_brand' => $e->consid_brand ?? $e->consider_make ?? '—',
                'consid_model' => $e->consid_model ?? $e->consider_model ?? '—',
                'consid_variant' => $e->consid_variant ?? $e->consider_variant ?? '—',
                'expected_price' => $e->expected_price ?? '—',
                'offered_price' => $e->offered_price ?? '—',
                'exchange_bonus' => $e->exchange_bonus ?? '—',
                'price_gap' => ($e->expected_price ?? 0) - ($e->offered_price ?? 0) - ($e->exchange_bonus ?? 0),
                'fin_mode' => $e->fin_mode ?? '—',
                'financier_name' => $finMap[$e->financier] ?? $e->financier ?? '—',
                'loan_status' => $e->loan_status ?? '—',

                // Follow up (SC side)
                'fup_type' => $fupTypesMap[$e->followup_type] ?? $e->followup_type ?? '—',
                'followup_type' => $fupTypesMap[$e->followup_type] ?? $e->followup_type ?? '—',
                'followup_date' => $this->formatDate($e->followup_date, 'd-M-Y'),
                'followup_time' => $e->followup_time ?? '—',
                'recent_planned_followup_date' => trim($this->formatDate($e->followup_date, 'd-M-Y') . ' ' . ($e->followup_time ?? '')) ?: '—',
                'recent_actual_followup_date' => $this->formatDate($e->recent_actual_followup_date, 'd-M-Y H:i'),
                'call_duration' => $e->actual_fup_duration ?? $e->call_duration ?? '—',
                'deviation_stage' => $e->deviation_stage ?? '—',
                'remarks' => $e->recent_fup_remarks ?? $e->remarks ?? '—',
                'followup_remarks_type' => $e->recent_fup_remarks_type ?? '—',
                'comments' => $e->recent_fup_comments ?? '—',
                'stage' => $e->stage ?? '—',
                'td_count' => $e->test_drive_count ?? '—',
                'test_drive_no' => $e->test_drive_no ?? $e->oem_test_drive_no ?? '—',
                'td_date' => $this->formatDate($e->td_date, 'd-M-Y'),
                'lost_reason' => $e->lost_reason ?? '—',
                'lost_sub_reason' => $e->lost_sub_reason ?? '—',
                'lost_detail_reason' => $e->lost_detail_reason ?? '—',
                'lost_remarks' => $e->lost_remarks ?? '—',

                // Follow up (CRE side)
                'cre_fup_count' => $creFup->cre_fup_count ?? '—',
                'cre_planned_fup_date' => $creFup ? $this->formatDate($creFup->cre_planned_fup_date, 'd-M-Y') : '—',
                'cre_actual_fup_date' => $creFup ? $this->formatDate($creFup->cre_actual_fup_date, 'd-M-Y') : '—',
                'cre_fup_call_duration' => $creFup->cre_fup_call_duration ?? '—',
                'cre_fup_deviation_stage' => $creFup->cre_fup_deviation_stage ?? '—',
                'cre_enq_stage' => $creFup->cre_enq_stage ?? '—',
                'cre_customer_stage' => $creFup->cre_customer_stage ?? '—',
                'cre_fup_remarks' => $creFup->cre_fup_remarks ?? '—',
                'cre_next_fup_date' => $creFup ? $this->formatDate($creFup->cre_next_fup_date, 'd-M-Y') : '—',
            ];
        }

        if ($type === 'reference') {
            $row['referee_name'] = $e->referee_name ?? '—';
            $row['referee_phone'] = $e->referee_phone ?? '—';
            $row['referred_by'] = $e->referred_by ?? '—';
            $row['first_name'] = trim(($e->first_name ?? '') . ' ' . ($e->last_name ?? '')) ?: '—';
        } elseif ($type === 'virtual') {
            $row['virtual_no'] = $e->virtual_no ?? '—';
            $row['call_date_and_time'] = $this->formatDate($e->virtual_call_date, 'd-M-Y H:i');
            $row['call_date'] = $this->formatDate($e->virtual_call_date, 'd-M-Y');
            $row['call_nature'] = $e->call_nature ?? '—';
            $row['call_duration'] = $e->call_duration ?? $e->virtual_call_duration ?? ($row['call_duration'] ?? '—');
            $row['remarks'] = $e->remarks ?? ($row['remarks'] ?? '—');
        } elseif ($type === 'whatsapp') {
            $row['campaign_name'] = $e->wapp_campaign_name ?? '—';
            $row['campaign_date'] = $this->formatDate($e->wapp_campaign_date, 'd-M-Y');
            $row['campaign_segment'] = $e->wapp_campaign_segment ?? '—';
            $row['campaign_model'] = $e->wapp_campaign_model ?? '—';
            $row['first_name'] = trim(($e->first_name ?? '') . ' ' . ($e->last_name ?? '')) ?: ($e->name ?? '—');
        }

        return $row;
    }

    private function actionWidth(string $type): int
    {
        return match ($type) {
            'all', 'quick', 'long' => 220,
            'hyperlocal' => 90,
            default => 110,
        };
    }

    private function getColumns($type)
    {
        $actionWidth = $this->actionWidth($type);

        $actionColumn = [
            'field'      => 'action',
            'headerName' => 'Action',
            'width'      => $actionWidth,
            'minWidth'   => $actionWidth,
            'pinned'     => 'right',
            'sortable'   => false,
            'filter'     => false,
            'cellClass'  => 'text-center p-0'
        ];

        $commonEnd = [
            ['field' => 'dms_enquiry_stage', 'headerName' => 'DMS Stage'],
            ['field' => 'cre_enquiry_stage', 'headerName' => 'CRE Stage'],
            ['field' => 'followup_status', 'headerName' => 'FOLLOW UP STATUS'],
            ['field' => 'cre_next_fup_date', 'headerName' => 'Next FUP Date'],
            ['field' => 'cre_next_fup_time', 'headerName' => 'Next FUP Time'],
            ['field' => 'cre_next_fup_remarks', 'headerName' => 'FUP Remarks'],
            ['field' => 'x8_quotation_no', 'headerName' => 'X8 Quotation No.'],
            ['field' => 'oem_otf_no', 'headerName' => 'OEM OTF No.'],
            ['field' => 'oem_test_drive_no', 'headerName' => 'OEM Test Drive No.'],
            $actionColumn
        ];

        if ($type === 'reference') {
            return [
                ['field' => 'serial_no', 'headerName' => 'S.No.'],
                ['field' => 'x8_enquiry_date', 'headerName' => 'Lead Date & Time'],
                ['field' => 'referred_by', 'headerName' => 'Referred By'],
                ['field' => 'referee_name', 'headerName' => 'Referee Name'],
                ['field' => 'referee_phone', 'headerName' => 'Referee Contact No.'],
                ['field' => 'first_name', 'headerName' => 'Customer Name'],
                ['field' => 'mobile', 'headerName' => 'Customer Contact No.'],
                ['field' => 'model_name', 'headerName' => 'Model'],
                ['field' => 'variant_name', 'headerName' => 'Variant (Optional)'],
                ['field' => 'pincode', 'headerName' => 'Pin Code'],
                ['field' => 'vpo', 'headerName' => 'VPO'],
                ['field' => 'tehsil', 'headerName' => 'Tehsil'],
                ['field' => 'district', 'headerName' => 'District'],
                ['field' => 'x8_sc_code', 'headerName' => 'X8 Assigned SC'],
                ['field' => 'x8_sc_mile_id', 'headerName' => 'X8 Assigned SC Mile ID'],
                ['field' => 'x8_sc_branch', 'headerName' => 'X8 Assigned SC Branch'],
                ['field' => 'x8_sc_location', 'headerName' => 'X8 Assigned SC Location'],
                ['field' => 'oem_enquiry_no', 'headerName' => 'OEM Enquiry No.'],
                $actionColumn
            ];
        }

        if ($type === 'virtual') {
            return [
                ['field' => 'serial_no', 'headerName' => 'S.No.'],
                ['field' => 'virtual_no', 'headerName' => 'Virtual Number'],
                ['field' => 'call_date', 'headerName' => 'Call Date'],
                ['field' => 'call_duration', 'headerName' => 'Call Duration'],
                ['field' => 'call_nature', 'headerName' => 'Call Nature'],
                ['field' => 'mobile', 'headerName' => 'Contact No.'],
                ['field' => 'alternate_mobile', 'headerName' => 'Alternate Contact No.'],
                ['field' => 'pincode', 'headerName' => 'Pin Code'],
                ['field' => 'vpo', 'headerName' => 'VPO'],
                ['field' => 'tehsil', 'headerName' => 'Tehsil'],
                ['field' => 'district', 'headerName' => 'District'],
                ['field' => 'x8_sc_code', 'headerName' => 'X8 Assigned SC'],
                ['field' => 'x8_sc_mile_id', 'headerName' => 'X8 Assigned SC Mile ID'],
                ['field' => 'x8_sc_branch', 'headerName' => 'X8 Assigned SC Branch'],
                ['field' => 'x8_sc_location', 'headerName' => 'X8 Assigned SC Location'],
                ['field' => 'oem_enquiry_no', 'headerName' => 'OEM Enquiry No.'],
                $actionColumn
            ];
        }

        if ($type === 'otf') {
            return [
                ['field' => 'serial_no', 'headerName' => 'S.No.', 'width' => 80, 'pinned' => 'left'],
                ['field' => 'booking_no', 'headerName' => 'Booking Number', 'width' => 150, 'pinned' => 'left'],
                ['field' => 'booking_date', 'headerName' => 'Booking Date', 'width' => 130],
                ['field' => 'sc_code', 'headerName' => 'SC Code', 'width' => 120],
                ['field' => 'booking_status', 'headerName' => 'Booking Status', 'width' => 130],
                ['field' => 'cancellation_date', 'headerName' => 'Booking Cancellation Date', 'width' => 160],
                ['field' => 'model_group', 'headerName' => 'Model Group', 'width' => 140],
                ['field' => 'variant', 'headerName' => 'Model Variant', 'width' => 160],
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
                ['field' => 'invoice_no', 'headerName' => 'Invoice No.', 'width' => 150],
                ['field' => 'evaluation_id', 'headerName' => 'Evaluation ID', 'width' => 150],
                ['field' => 'so_number', 'headerName' => 'SO Number', 'width' => 150],
                ['field' => 'otf_number', 'headerName' => 'OTF Number', 'width' => 160],
                [
                    'field'        => 'action',
                    'headerName'   => 'Action',
                    'width'        => $this->actionWidth('otf'),
                    'minWidth'     => $this->actionWidth('otf'),
                    'pinned'       => 'right',
                    'sortable'     => false,
                    'filter'       => false,
                    'cellRenderer' => 'htmlRenderer',
                    'cellClass'    => 'text-center',
                ],
            ];
        }

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

        if ($type === 'whatsapp') {
            return [
                ['field' => 'serial_no', 'headerName' => 'S.No.'],
                ['field' => 'x8_enquiry_date', 'headerName' => 'Lead Date & Time'],
                ['field' => 'campaign_name', 'headerName' => 'Whatsapp Campaign Name'],
                ['field' => 'campaign_date', 'headerName' => 'Whatsapp Campaign Date'],
                ['field' => 'campaign_segment', 'headerName' => 'Whatsapp Campaign Segment'],
                ['field' => 'campaign_model', 'headerName' => 'Whatsapp Campaign Model'],
                ['field' => 'first_name', 'headerName' => 'Customer Name'],
                ['field' => 'mobile', 'headerName' => 'Customer Contact No.'],
                ['field' => 'model_name', 'headerName' => 'Model'],
                ['field' => 'variant_name', 'headerName' => 'Variant (Optional)'],
                ['field' => 'pincode', 'headerName' => 'Pin Code'],
                ['field' => 'vpo', 'headerName' => 'VPO'],
                ['field' => 'tehsil', 'headerName' => 'Tehsil'],
                ['field' => 'district', 'headerName' => 'District'],
                ['field' => 'x8_sc_code', 'headerName' => 'X8 Assigned SC'],
                ['field' => 'x8_sc_mile_id', 'headerName' => 'X8 Assigned SC Mile ID'],
                ['field' => 'x8_sc_branch', 'headerName' => 'X8 Assigned SC Branch'],
                ['field' => 'x8_sc_location', 'headerName' => 'X8 Assigned SC Location'],
                ['field' => 'oem_enquiry_no', 'headerName' => 'OEM Enquiry No.'],
                $actionColumn
            ];
        }

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
            ['field' => 'x8_quotation_no', 'headerName' => 'X8 Quotation No.'],
            ['field' => 'x8_quotation_date', 'headerName' => 'X8 Quotation Date'],
            ['field' => 'oem_booking_no', 'headerName' => 'OEM Booking No.'],
            ['field' => 'oem_booking_date', 'headerName' => 'OEM Booking Date'],
            ['field' => 'oem_otf_no', 'headerName' => 'OEM OTF No.'],
            ['field' => 'x8_booking_no', 'headerName' => 'X8 Booking No.'],
            ['field' => 'x8_booking_date', 'headerName' => 'X8 Booking Date'],
        ];

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
            ['field' => 'has_ev', 'headerName' => 'Has EV?'],
            ['field' => 'first_name', 'headerName' => 'First Name'],
            ['field' => 'last_name', 'headerName' => 'Last Name'],
            ['field' => 'full_name', 'headerName' => 'Full Name'],
            ['field' => 'mobile', 'headerName' => 'Contact No.'],
            ['field' => 'alternate_mobile', 'headerName' => 'Alternate Contact No.'],
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
            ['field' => 'fup_count', 'headerName' => 'FUP Count'],
            ['field' => 'followup_type', 'headerName' => 'FUP Type'],
            ['field' => 'recent_planned_followup_date', 'headerName' => 'Planned FUP Date & Time'],
            ['field' => 'recent_actual_followup_date', 'headerName' => 'Actual FUP Date & Time'],
            ['field' => 'call_duration', 'headerName' => 'FUP Call Duration'],
            ['field' => 'deviation_stage', 'headerName' => 'FUP Deviation Stage'],
            ['field' => 'remarks', 'headerName' => 'FUP Remarks'],
            ['field' => 'followup_remarks_type', 'headerName' => 'FUP Remarks Type'],
            ['field' => 'comments', 'headerName' => 'FUP Comments'],
            ['field' => 'stage', 'headerName' => 'Enquiry Stage'],
            ['field' => 'td_count', 'headerName' => 'Test Drive Count'],
            ['field' => 'test_drive_no', 'headerName' => 'Test Drive No.'],
            ['field' => 'td_date', 'headerName' => 'Test Drive Date'],
            ['field' => 'lost_reason', 'headerName' => 'Lost Reason'],
            ['field' => 'lost_sub_reason', 'headerName' => 'Lost Sub Reason'],
            ['field' => 'lost_detail_reason', 'headerName' => 'Lost Detail Reason'],
            ['field' => 'lost_remarks', 'headerName' => 'Lost Remarks'],
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

        return array_merge($baseCols, $midCols, $commonEnd);
    }

    private function getDefaultColumns(): array
    {
        return [
            'serial_no',
            'x8_enquiry_no',
            'x8_enquiry_date',
            'oem_enquiry_no',
            'oem_quick_enquiry_no',
            'source_code',
            'sub_source',
            'x8_quotation_no',
            'oem_booking_no',
            'x8_booking_no',
            'model_name',
            'variant_name',
            'color_name',
            'first_name',
            'mobile',
            'alternate_mobile',
            'tehsil',
            'district',
            'purchase_type',
            'expected_price',
            'price_gap',
            'fin_mode',
            'x8_sc_code',
            'sc_code',
            'dob',
            'marital_status',
            'consid_brand',
            'consid_model',
            'consid_variant',
            'recent_planned_followup_date',
            'deviation_stage',
            'stage',
            'td_count',
            'td_date',
            'cre_planned_fup_date',
            'cre_enq_stage',
            'cre_customer_stage',
            'action',
        ];
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
                    ->orWhere('alternate_mobile', 'like', $like)
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
        if (!$sortApplied) {
            $query->orderByDesc('created_at');
        }
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
            'fups' => [],
            'creFups' => []
        ] + $this->getEnquiryFormData());
    }

    public function createReference()
    {
        return view('admin.enquiry.reference-create', [
            'title' => 'Add Reference Enquiry',
            'enquiry' => null,
            'fups' => [],
            'creFups' => []
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

        $creFups = DB::table('xlr8_cre_enquiry_fup')
            ->where('x8_enq_no', 'XENQ-' . $enquiry->id)
            ->orderBy('id', 'asc')
            ->get();

        $data['enquiry'] = $enquiry;
        $data['fups'] = $fups;
        $data['creFups'] = $creFups;

        return view('admin.enquiry.create', $data);
    }

    private function saveCreFup($enquiry, $request)
    {
        if ($request->filled('cre_enq_stage') || $request->filled('cre_customer_stage') || $request->filled('cre_next_fup_date') || $request->filled('cre_fup_remarks')) {
            $previousFup = DB::table('xlr8_cre_enquiry_fup')
                ->where('x8_enq_no', 'XENQ-' . $enquiry->id)
                ->orderBy('id', 'desc')
                ->first();

            $fupCount = $previousFup ? ($previousFup->cre_fup_count + 1) : 1;
            $plannedDate = $previousFup ? $previousFup->cre_next_fup_date : Carbon::now()->format('Y-m-d');
            $actualDate = Carbon::now()->format('Y-m-d');

            DB::table('xlr8_cre_enquiry_fup')->insert([
                'enquiry_no' => $enquiry->oem_enquiry_no ?? $enquiry->enquiry_no,
                'quick_enquiry_no' => $enquiry->quick_enquiry_no ?? $enquiry->oem_quick_enquiry_no,
                'x8_enq_no' => 'XENQ-' . $enquiry->id,
                'cre_fup_count' => $fupCount,
                'cre_planned_fup_date' => $plannedDate,
                'cre_actual_fup_date' => $actualDate,
                'cre_fup_call_duration' => $request->cre_fup_call_duration,
                'cre_fup_deviation_stage' => $request->cre_fup_deviation_stage,
                'cre_enq_stage' => $request->cre_enq_stage,
                'cre_customer_stage' => $request->cre_customer_stage,
                'cre_fup_remarks' => $request->cre_fup_remarks,
                'cre_next_fup_date' => $request->cre_next_fup_date,
                'created_by' => backpack_user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
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

            $creFields = ['cre_fup_call_duration', 'cre_fup_deviation_stage', 'cre_enq_stage', 'cre_customer_stage', 'cre_fup_remarks', 'cre_next_fup_date'];
            $enquiryData = collect($validated)->except($creFields)->toArray();

            $enquiry = Enquiry::create($enquiryData);

            $this->saveCreFup($enquiry, $request);

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

        $creFields = ['cre_fup_call_duration', 'cre_fup_deviation_stage', 'cre_enq_stage', 'cre_customer_stage', 'cre_fup_remarks', 'cre_next_fup_date'];
        $enquiryData = collect($validated)->except($creFields)->toArray();

        $enquiry->update($enquiryData);

        $this->saveCreFup($enquiry, $request);

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
        if (!empty($validated['variant_code'])) {
            $validated['variant'] = OrgService::variants($validated['model_code'])[$validated['variant_code']]['name'] ?? null;
        }
        if (!empty($validated['color_code'])) {
            $validated['color'] = OrgService::colors($validated['variant_code'])[$validated['color_code']] ?? null;
        }
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
            'alternate_mobile' => 'nullable|max:15',
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
            'vpo' => 'nullable|max:150',
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
            'sc_code' => 'nullable',
            'x8_sc_code' => 'nullable|string|max:200',
            'x8_sc_mile_id' => 'nullable|string|max:100',

            // Follow Up Rules
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

            // CRE Follow up validations
            'cre_fup_call_duration' => 'nullable|string|max:50',
            'cre_fup_deviation_stage' => 'nullable|string|max:50',
            'cre_enq_stage' => 'nullable|string|max:50',
            'cre_customer_stage' => 'nullable|string|max:50',
            'cre_fup_remarks' => 'nullable|string|max:255',
            'cre_next_fup_date' => 'nullable|date',

            // Financial & Exchange Rules
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
        if (!$log = DB::table('xlr8_crm_import_logs')->where('id', $id)->first()) {
            return response()->json(['error' => 'Not found'], 404);
        }
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

    public function otfBookingsList()
    {
        return $this->renderGridPage('admin.enquiry.otf-bookings', 'OTF Bookings', 'otf');
    }
}
