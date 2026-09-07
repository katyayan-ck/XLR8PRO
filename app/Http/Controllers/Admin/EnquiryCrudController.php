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

            $fupTypesMap = collect(OrgService::keywordValueByCode('FOLLOW_UP_TYPE'))->pluck('value', 'code')->toArray();
            $enqStageMap = collect(OrgService::keywordValueByCode('ENQUIRY_STAGE'))->pluck('value', 'code')->toArray();
            $custStageMap = collect(OrgService::keywordValueByCode('CUSTOMER_STAGE'))->pluck('value', 'code')->toArray();
            $purcTypeMap = collect(OrgService::keywordValueByCode('PURCHASE_TYPE'))->pluck('value', 'code')->toArray();
            $lostReasonMap = collect(OrgService::keywordValueByCode('LOST_REASON'))->pluck('value', 'code')->toArray();

            // 1. Source & Sub-Source Maps
            $sourcesMap   = collect(OrgService::keywordValueByCode('ENQ_SOURCE'))->pluck('value', 'code')->toArray();
            $subSourceMap = collect(OrgService::keywordValueByCode('ENQUIRY_SUB_SOURCE'))->pluck('value', 'code')->toArray();

            // 2. OEM Enquiry Type Map
            $enquiryTypeMap = collect(OrgService::keywordValueByCode('ENQUIRY_TYPE'))->pluck('value', 'code')->toArray();

            // 3. Demographics: Marital Status & Age Group
            $maritalStatusMap = collect(OrgService::keywordValueByCode('MARITAL_STATUS'))->pluck('value', 'code')->toArray();
            $ageGroupMap      = collect(OrgService::keywordValueByCode('AGE_GROUP'))->pluck('value', 'code')->toArray();

            // 4. Lost Sub Reason Map (checks both LOST_SUBREASON and LOST_SUB_REASON variations)
            $sub1 = OrgService::keywordValueByCode('LOST_SUBREASON');
            $sub2 = OrgService::keywordValueByCode('LOST_SUB_REASON');
            $lostSubReasonMap = collect(array_merge($sub1, $sub2))->pluck('value', 'code')->toArray();

            // 5. CRE Deviation Stage Map
            $deviationStageMap = collect(OrgService::keywordValueByCode('DEVIATION_STAGE'))->pluck('value', 'code')->toArray();

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

            return compact(
                'lpMap',
                'fuelMap',
                'finMap',
                'branchesMap',
                'segmentsMap',
                'scByCode',
                'scByMileId',
                'scNamesByCode',
                'fupTypesMap',
                'enqStageMap',
                'custStageMap',
                'purcTypeMap',
                'lostReasonMap',
                'sourcesMap',
                'subSourceMap',
                'enquiryTypeMap',
                'maritalStatusMap',
                'ageGroupMap',
                'lostSubReasonMap',
                'deviationStageMap'
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
            return DB::table('xlr8_crm_booking as crm_booking')
                ->select([
                    'crm_booking.id',
                    'crm_booking.booking_date',
                    'crm_booking.status',
                    'crm_booking.cancellation_date',
                    'crm_booking.sc_mile_id as sc_code',
                    'crm_booking.oem_code',
                    'crm_booking.invoice_no',
                    'crm_booking.evaluation_no',
                    'crm_booking.so_no',
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
    private static array $vehicleFromOemCodeCache = [];



    private function resolveVehicleFromOemCode(?string $oemCode): array
    {
        $empty = ['segment' => '—', 'model' => '—', 'variant' => '—', 'color' => '—'];

        $oemCode = trim((string) $oemCode);
        if (strlen($oemCode) < 3) {
            return $empty;
        }

        if (isset(self::$vehicleFromOemCodeCache[$oemCode])) {
            return self::$vehicleFromOemCodeCache[$oemCode];
        }

        // 1. Base code (variant code) aur last 2 chars (color code) alag karein
        $variantCode = substr($oemCode, 0, -2);
        $colorCode   = strtoupper(substr($oemCode, -2));

        // 2. Direct xlr8_vehicle_variant table se rows fetch karein
        $rows = DB::table('xlr8_vehicle_variant')
            ->where('code', $variantCode)
            ->get(['segment_code', 'model_code', 'custom_name', 'color', 'color_code']);

        if ($rows->isEmpty()) {
            return self::$vehicleFromOemCodeCache[$oemCode] = $empty;
        }

        // 3. Variant details ke liye any row
        $baseRow = $rows->first();

        // 4. Color column data jahan color_code matching ho
        $colorRow = $rows->first(fn($r) => strtoupper((string) $r->color_code) === $colorCode);

        $result = [
            'segment' => $baseRow->segment_code ?? '—',
            'model'   => $baseRow->model_code ?? '—',
            'variant' => $baseRow->custom_name ?? '—',
            'color'   => ($colorRow->color ?? $baseRow->color) ?? '—',
        ];

        return self::$vehicleFromOemCodeCache[$oemCode] = $result;
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
            'title' => 'Xceler8 Enquiries',
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

    private function actionWidth(string $type): int
    {
        return match ($type) {
            'all', 'quick', 'long', 'exchange', 'scrappage', 'exchange_not_interested', 'finance', 'finance_not_interested' => 240,
            'hyperlocal' => 90,
            default => 200,
        };
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
        $enqStageMap = $lookups['enqStageMap'] ?? [];
        $custStageMap = $lookups['custStageMap'] ?? [];
        $purcTypeMap = $lookups['purcTypeMap'] ?? [];
        $lostReasonMap = $lookups['lostReasonMap'] ?? [];
        $sourcesMap        = $lookups['sourcesMap'] ?? [];
        $subSourceMap      = $lookups['subSourceMap'] ?? [];
        $enquiryTypeMap    = $lookups['enquiryTypeMap'] ?? [];
        $maritalStatusMap  = $lookups['maritalStatusMap'] ?? [];
        $ageGroupMap       = $lookups['ageGroupMap'] ?? [];
        $lostSubReasonMap  = $lookups['lostSubReasonMap'] ?? [];
        $deviationStageMap = $lookups['deviationStageMap'] ?? [];

        $x8AssignedSc = $this->getAssignedSc($e->x8_sc_code ?? null, $e->x8_sc_mile_id ?? null, $scByCode, $scByMileId);
        $oemAssignedSc = $this->getAssignedSc($e->sc_code ?? null, $e->sc_mile_id ?? null, $scByCode, $scByMileId);

        $segmentRel = $e instanceof \Illuminate\Database\Eloquent\Model && $e->relationLoaded('segment') ? $e->getRelation('segment') : null;
        $modelRel = $e instanceof \Illuminate\Database\Eloquent\Model && $e->relationLoaded('model') ? $e->getRelation('model') : null;
        $variantRel = $e instanceof \Illuminate\Database\Eloquent\Model && $e->relationLoaded('variant') ? $e->getRelation('variant') : null;
        $colorRel = $e instanceof \Illuminate\Database\Eloquent\Model && $e->relationLoaded('color') ? $e->getRelation('color') : null;

        $cleanVal = function ($val) {
            $trimmed = trim((string) $val);
            if ($trimmed === '' || in_array(strtoupper($trimmed), ['NA', 'N/A', 'NULL', '—'])) {
                return '—';
            }
            return $trimmed;
        };

        $resolvedSegment = $resolvedModel = $resolvedVariant = $resolvedColor = null;

        if ($type !== 'otf') {
            $segmentRel = $e instanceof \Illuminate\Database\Eloquent\Model && $e->relationLoaded('segment') ? $e->getRelation('segment') : null;
            $modelRel = $e instanceof \Illuminate\Database\Eloquent\Model && $e->relationLoaded('model') ? $e->getRelation('model') : null;
            $variantRel = $e instanceof \Illuminate\Database\Eloquent\Model && $e->relationLoaded('variant') ? $e->getRelation('variant') : null;
            $colorRel = $e instanceof \Illuminate\Database\Eloquent\Model && $e->relationLoaded('color') ? $e->getRelation('color') : null;

            $resolvedSegment = $cleanVal($e->segment_code ? ($segmentRel?->name ?? $e->segment ?? $e->segment_code) : $e->segment);
            $resolvedModel   = $cleanVal($e->model_code ? ($modelRel?->name ?? $e->model ?? $e->model_code) : $e->model);
            $resolvedVariant = $cleanVal($e->variant_code ? ($variantRel?->display_name ?? $variantRel?->custom_name ?? $variantRel?->oem_name ?? $e->variant ?? $e->variant_code) : $e->variant);
            $resolvedColor   = $cleanVal($e->color_code ? ($colorRel?->name ?? $e->color ?? $e->color_code) : $e->color);
        }

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
                'dms_enquiry_stage' => $enqStageMap[$e->stage ?? ''] ?? $e->stage ?? '—',
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
                'action' => '—',
            ];
        }

        if ($type === 'otf') {
            $editUrl = backpack_url("booking/{$e->id}/edit");
            $vehicle = $this->resolveVehicleFromOemCode($e->oem_code ?? null);
            return [
                'serial_no'         => $i + 1,
                'booking_no'        => $e->id ?? '—',
                'booking_date'      => $this->formatDate($e->booking_date, 'd-M-Y'),
                'sc_code'           => $e->sc_code ?? '—',
                'booking_status'    => $e->status ?? '—',
                'cancellation_date' => $this->formatDate($e->cancellation_date, 'd-M-Y'),
                'segment'           => $vehicle['segment'],
                'model'             => $vehicle['model'],
                'variant'           => $vehicle['variant'],
                'color'             => $vehicle['color'],
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

        // Added globally to ensure Quote shows up in all standard listings
        $actionBtns .= '<a href="' . $quotUrl . '" class="btn btn-success btn-sm">Quote</a>';

        if ($type === 'all') {
            $actionBtns .= '<a href="' . $bookUrl . '" class="btn btn-warning btn-sm" title="Convert to Booking">Book</a>';
        } elseif (in_array($type, ['exchange', 'scrappage', 'exchange_not_interested'])) {
            $exchUrl = backpack_url("exchange/enquiry/{$e->id}/edit");
            // Appended process instead of overwriting, keeping Edit and Quote accessible
            $actionBtns .= '<a href="' . $exchUrl . '" class="btn btn-sm btn-info">Process</a>';
        } elseif (in_array($type, ['finance', 'finance_not_interested'])) {
            $finUrl = backpack_url("finance/enquiry/{$e->id}/edit");
            // Appended process instead of overwriting, keeping Edit and Quote accessible
            $actionBtns .= '<a href="' . $finUrl . '" class="btn btn-sm btn-info">Process</a>';
        }

        $row = [
            'serial_no' => $i + 1,
            'x8_enquiry_no' => 'XENQ-' . $e->id,
            'x8_enquiry_date' => $this->formatDate($e->created_at, 'd-M-Y H:i'),
            'x8_enquiry_assign_date' => $this->formatDate($e->x8_enquiry_assign_date ?? $e->enq_assign_date, 'd-M-Y'),
            'oem_enquiry_no' => $e->x8_enquiry_no ?? $e->enquiry_no ?? $e->oem_enquiry_no ?? '—',
            'oem_enquiry_date' => $this->formatDate($e->x8_enquiry_date ?? $e->enquiry_date ?? $e->oem_enquiry_date, 'd-M-Y'),
            'oem_enquiry_assign_date' => $this->formatDate($e->oem_enquiry_assign_date ?? $e->enq_assign_date, 'd-M-Y'),
            'segment_name'            => $resolvedSegment,
            'model_name'              => $resolvedModel,
            'variant_name'            => $resolvedVariant,
            'color_name'              => $resolvedColor,
            'mobile' => $e->mobile ?? '—',
            'alternate_mobile' => $e->alternate_mobile ?? '—',
            'dms_enquiry_stage' => $enqStageMap[$e->stage ?? ''] ?? $e->stage ?? '—',
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
            'lost_reason'     => $lostReasonMap[$e->lost_reason ?? ''] ?? $e->lost_reason ?? '—',
            'followup_status' => $e->fup_status ?? '—',
            'action' => '<div class="d-flex justify-content-center gap-2">' . $actionBtns . '</div>',
        ];

        if (in_array($type, ['long', 'quick', 'all', 'reference', 'virtual', 'whatsapp', 'exchange', 'scrappage', 'exchange_not_interested', 'finance', 'finance_not_interested'])) {
            $x8BranchCode = $x8AssignedSc['primary_branch_code'] ?? null;
            $oemBranchCode = $oemAssignedSc['primary_branch_code'] ?? null;

            $creFup = $lookups['creFups']['XENQ-' . $e->id] ?? null;

            $row += [
                'oem_long_enquiry_no'          => $e->oem_long_enquiry_no ?? '—',
                'oem_long_enquiry_date'        => $this->formatDate($e->oem_long_enquiry_date, 'd-M-Y'),
                'oem_long_enquiry_assign_date' => $this->formatDate($e->oem_long_enquiry_assign_date, 'd-M-Y'),
                'oem_quick_enquiry_no'         => $e->oem_quick_enquiry_no ?? $e->quick_enquiry_no ?? '—',
                'oem_quick_enquiry_date'       => $this->formatDate($e->oem_quick_enquiry_date ?? $e->quick_enquiry_date, 'd-M-Y'),
                'oem_quick_enquiry_status'     => $e->oem_quick_enquiry_status ?? $e->quick_status ?? '—',
                'oem_quick_enquiry_assign_date' => $this->formatDate($e->oem_quick_enquiry_assign_date ?? $e->quick_enq_assign_date, 'd-M-Y'),
                'x8_enq_source'                => $e->x8_enq_source ?? $e->x8_source_code ?? '—',
                'first_name'                   => $e->first_name ?? '—',
                'last_name'                    => $e->last_name ?? '—',
                'full_name'                    => $e->full_name ?? trim(($e->first_name ?? '') . ' ' . ($e->last_name ?? '')),
                'email'                        => $e->email ?? '—',
                'gender'                       => $e->gender ?? '—',
                'enquiry_type'                 => $enquiryTypeMap[$e->enquiry_type ?? ''] ?? $e->enquiry_type ?? '—',
                'source_code'                  => $sourcesMap[$e->source_code ?? ''] ?? $e->source?->name ?? $e->source_code ?? '—',
                'sub_source'                   => $subSourceMap[$e->sub_source ?? ''] ?? $e->sub_source ?? '—',
                'likely_purchase_date'         => $lpMap[$e->likely_purchase_date] ?? $e->likely_purchase_date ?? '—',
                'x8_quotation_date'            => $this->formatDate($e->x8_quotation_date ?? $e->quotation_date, 'd-M-Y'),
                'fuel_type'            => $cleanVal($fuelMap[$e->fuel_type] ?? $e->fuel_type),
                'transmission'         => $cleanVal($e->transmission),
                'drivetrain'           => $cleanVal($e->drivetrain),
                'seating'              => $cleanVal($e->seating),
                'pincode'                      => $e->pincode ?? $e->zipcode ?? '—',
                'vpo'                          => $e->vpo ?? '—',
                'tehsil'                       => $e->tehsil ?? '—',
                'district'                     => $e->district ?? '—',
                'city'                         => $e->city ?? '—',
                'address'                      => $e->address ?? $e->customer_address ?? '—',
                'x8_sc_code'                   => $x8AssignedSc['display_name'] ?? $scNamesByCode[$e->x8_sc_code] ?? $e->x8_sc_code ?? '—',
                'x8_sc_mile_id'                => $e->x8_sc_mile_id ?? $x8AssignedSc['mile_id'] ?? '—',
                'x8_sc_branch'                 => $branchesMap[$x8BranchCode] ?? $x8BranchCode ?? '—',
                'x8_sc_location'               => $x8AssignedSc['primary_loc_code'] ?? '—',
                'sc_code'                      => $scNamesByCode[$e->sc_code] ?? $e->sc_code ?? '—',
                'sc_mile_id'                   => $e->sc_mile_id ?? $oemAssignedSc['mile_id'] ?? '—',
                'oem_sc_branch'                => $branchesMap[$oemBranchCode] ?? $oemBranchCode ?? '—',
                'oem_sc_location'              => $oemAssignedSc['primary_loc_code'] ?? '—',
                'dealer_branch'                => $e->dealer_branch ?? '—',
                'dealer_location'              => $e->dealer_location ?? '—',
                'occupation_type'              => $e->occupation_type ?? '—',
                'customer_type'                => $e->customer_type ?? '—',
                'occupation_sub_type'          => $e->occupation_sub_type ?? '—',
                'company_name'                 => $e->company_name ?? '—',
                'dob'                          => $this->formatDate($e->dob, 'd-M-Y'),
                'marital_status'               => $maritalStatusMap[$e->marital_status ?? ''] ?? $e->marital_status ?? '—',
                'marriage_date'                => $this->formatDate($e->marriage_date, 'd-M-Y'),
                'age_group'                    => $ageGroupMap[$e->age_group ?? ''] ?? $e->age_group ?? '—',
                'usage_area'                   => $e->usage_area ?? '—',
                'km_travelled_daily'           => $e->km_travelled_daily ?? '—',
                'application_type'             => $e->application_type ?? '—',
                'application'                  => $e->application ?? '—',
                'has_ev'                       => $e->has_ev ?? '—',
                'purchase_type'                => $purcTypeMap[$e->purchase_type ?? ''] ?? $e->purchase_type ?? '—',
                'consid_brand'                 => $e->consid_brand ?? $e->consider_make ?? '—',
                'consid_model'                 => $e->consid_model ?? $e->consider_model ?? '—',
                'consid_variant'               => $e->consid_variant ?? $e->consider_variant ?? '—',
                'expected_price'               => $e->expected_price ?? '—',
                'offered_price'                => $e->offered_price ?? '—',
                'exchange_bonus'               => $e->exchange_bonus ?? '—',
                'price_gap'                    => ($e->expected_price ?? 0) - ($e->offered_price ?? 0) - ($e->exchange_bonus ?? 0),
                'fin_mode'                     => $e->fin_mode ?? '—',
                'financier_name'               => $finMap[$e->financier] ?? $e->financier ?? '—',
                'loan_status'                  => $e->loan_status ?? '—',

                // Follow up (SC side)
                'fup_type'                     => $fupTypesMap[$e->followup_type] ?? $e->followup_type ?? '—',
                'followup_type'                => $fupTypesMap[$e->followup_type] ?? $e->followup_type ?? '—',
                'followup_date'                => $this->formatDate($e->followup_date, 'd-M-Y'),
                'followup_time'                => $e->followup_time ?? '—',
                'recent_planned_followup_date' => trim($this->formatDate($e->followup_date, 'd-M-Y') . ' ' . ($e->followup_time ?? '')) ?: '—',
                'recent_actual_followup_date'  => $this->formatDate($e->recent_actual_followup_date, 'd-M-Y H:i'),
                'call_duration'                => $e->actual_fup_duration ?? $e->call_duration ?? '—',
                'deviation_stage'              => $deviationStageMap[$e->deviation_stage ?? ''] ?? $e->deviation_stage ?? '—',
                'remarks'                      => $e->recent_fup_remarks ?? $e->remarks ?? '—',
                'followup_remarks_type'        => $e->recent_fup_remarks_type ?? '—',
                'comments'                     => $e->recent_fup_comments ?? '—',
                'stage'                        => $enqStageMap[$e->stage ?? ''] ?? $e->stage ?? '—',
                'td_count'                     => $e->test_drive_count ?? '—',
                'test_drive_no'                => $e->test_drive_no ?? $e->oem_test_drive_no ?? '—',
                'td_date'                      => $this->formatDate($e->td_date, 'd-M-Y'),
                'lost_reason'                  => $lostReasonMap[$e->lost_reason ?? ''] ?? $e->lost_reason ?? '—',
                'lost_sub_reason'              => $lostSubReasonMap[$e->lost_sub_reason ?? ''] ?? $e->lost_sub_reason ?? '—',
                'lost_detail_reason'           => $e->lost_detail_reason ?? '—',
                'lost_remarks'                 => $e->lost_remarks ?? '—',

                // Follow up (CRE side)
                'cre_fup_count'                => $creFup->cre_fup_count ?? '—',
                'cre_planned_fup_date'         => $creFup ? $this->formatDate($creFup->cre_planned_fup_date, 'd-M-Y') : '—',
                'cre_actual_fup_date'          => $creFup ? $this->formatDate($creFup->cre_actual_fup_date, 'd-M-Y H:i') : '—',
                'cre_fup_call_duration'        => $creFup->cre_fup_call_duration ?? '—',
                'cre_fup_deviation_stage'      => $deviationStageMap[$creFup->cre_fup_deviation_stage ?? ''] ?? $creFup->cre_fup_deviation_stage ?? '—',
                'cre_enq_stage'                => $enqStageMap[$creFup->cre_enq_stage ?? ''] ?? $creFup->cre_enq_stage ?? '—',
                'cre_customer_stage'           => $custStageMap[$creFup->cre_customer_stage ?? ''] ?? $creFup->cre_customer_stage ?? '—',
                'cre_fup_remarks'              => $creFup->cre_fup_remarks ?? '—',
                // 'cre_next_fup_date'            => $creFup ? $this->formatDate($creFup->cre_next_fup_date, 'd-M-Y') : '—',
                'cre_next_fup_date'            => $creFup ? $this->formatDate($creFup->cre_next_fup_date, 'd-M-Y H:i') : '—',
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
            $actionColumn
        ];

        if ($type === 'reference') {
            return [
                ['field' => 'serial_no', 'headerName' => 'S.No.'],
                ['field' => 'x8_enquiry_date', 'headerName' => 'Lead Date & Time'],
                ['field' => 'referred_by', 'headerName' => 'Referee Type'],
                ['field' => 'referee_name', 'headerName' => 'Referee Name'],
                ['field' => 'referee_phone', 'headerName' => 'Referee Contact No.'],
                ['field' => 'first_name', 'headerName' => 'Customer Name'],
                ['field' => 'mobile', 'headerName' => 'Customer Contact No.'],
                ['field' => 'model_name', 'headerName' => 'Model'],
                ['field' => 'variant_name', 'headerName' => 'Variant'],
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
                ['field' => 'segment', 'headerName' => 'Segment', 'width' => 120],
                ['field' => 'model', 'headerName' => 'Model', 'width' => 140],
                ['field' => 'variant', 'headerName' => 'Variant', 'width' => 160],
                ['field' => 'color', 'headerName' => 'Color', 'width' => 130],
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
                ['field' => 'variant_name', 'headerName' => 'Variant'],
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
        if ($request->filled('cre_enq_stage') || $request->filled('cre_customer_stage') || $request->filled('cre_fup_remarks')) {

            $x8EnqNo = 'XENQ-' . $enquiry->id;

            // 1. Check if there's a pending OPEN_FOLLOW_UP row
            $openFup = DB::table('xlr8_cre_enquiry_fup')
                ->where('x8_enq_no', $x8EnqNo)
                ->where('cre_fup_deviation_stage', 'OPEN_FOLLOW_UP')
                ->orderBy('id', 'desc')
                ->first();

            $fupCount = 1;
            // Force IST Timezone
            $plannedDate = Carbon::now('Asia/Kolkata')->format('Y-m-d H:i:s');

            if ($openFup) {
                $fupCount = $openFup->cre_fup_count;
                $plannedDate = $openFup->cre_planned_fup_date;
            } else {
                // Determine Fup Count if no Open Fup exists
                $lastFup = DB::table('xlr8_cre_enquiry_fup')
                    ->where('x8_enq_no', $x8EnqNo)
                    ->orderBy('id', 'desc')
                    ->first();
                if ($lastFup) {
                    $fupCount = $lastFup->cre_fup_count + 1;
                }
            }

            // Force IST Timezone
            $actualDate = Carbon::now('Asia/Kolkata')->format('Y-m-d H:i:s');

            // --- DEVIATION STAGE CALCULATION ---
            $planned = Carbon::parse($plannedDate, 'Asia/Kolkata')->startOfDay();
            $actual = Carbon::parse($actualDate, 'Asia/Kolkata')->startOfDay();
            $diffDays = $planned->diffInDays($actual, false);

            $deviationCode = 'SAME_DAY';
            if ($diffDays < 0) {
                $deviationCode = 'PREPONED_FOLLOW_UPS';
            } elseif ($diffDays == 1 || $diffDays == 2) {
                $deviationCode = '1_AND_2_DAYS';
            } elseif ($diffDays >= 3 && $diffDays <= 10) {
                $deviationCode = '3_TO_10_DAYS';
            } elseif ($diffDays > 10) {
                $deviationCode = 'GREATER_THAN_10_DAYS';
            }

            // Force IST Timezone for user input
            $nextFupDate = $request->cre_next_fup_date ? Carbon::parse($request->cre_next_fup_date, 'Asia/Kolkata')->format('Y-m-d H:i:s') : null;

            // Data for the ACTUAL completed follow up
            $actualData = [
                'enquiry_no' => $enquiry->oem_enquiry_no ?? $enquiry->enquiry_no,
                'quick_enquiry_no' => $enquiry->quick_enquiry_no ?? $enquiry->oem_quick_enquiry_no,
                'x8_enq_no' => $x8EnqNo,
                'cre_fup_count' => $fupCount,
                'cre_planned_fup_date' => $plannedDate,
                'cre_actual_fup_date' => $actualDate,
                'cre_fup_deviation_stage' => $deviationCode,
                'cre_enq_stage' => $request->cre_enq_stage,
                'cre_customer_stage' => $request->cre_customer_stage,
                'cre_fup_remarks' => $request->cre_fup_remarks,
                'cre_next_fup_date' => $nextFupDate,
                'updated_at' => now('Asia/Kolkata'), // Force IST
            ];

            // Complete the pending row OR insert a new one
            if ($openFup) {
                $actualData['updated_by'] = backpack_user()->id;
                DB::table('xlr8_cre_enquiry_fup')->where('id', $openFup->id)->update($actualData);
            } else {
                $actualData['created_by'] = backpack_user()->id;
                $actualData['created_at'] = now('Asia/Kolkata'); // Force IST
                DB::table('xlr8_cre_enquiry_fup')->insert($actualData);
            }

            // 2. Create the NEXT pending row (Only if not LOST/DROPPED and date is provided)
            if ($nextFupDate && !in_array($request->cre_enq_stage, ['LOST', 'DROPPED'])) {
                DB::table('xlr8_cre_enquiry_fup')->insert([
                    'enquiry_no' => $enquiry->oem_enquiry_no ?? $enquiry->enquiry_no,
                    'quick_enquiry_no' => $enquiry->quick_enquiry_no ?? $enquiry->oem_quick_enquiry_no,
                    'x8_enq_no' => $x8EnqNo,
                    'cre_fup_count' => $fupCount + 1,
                    'cre_planned_fup_date' => $nextFupDate,
                    'cre_actual_fup_date' => null,
                    'cre_fup_deviation_stage' => 'OPEN_FOLLOW_UP',
                    'cre_enq_stage' => null,
                    'cre_customer_stage' => null,
                    'cre_fup_remarks' => null,
                    'cre_next_fup_date' => null,
                    'created_by' => backpack_user()->id,
                    'created_at' => now('Asia/Kolkata'), // Force IST
                    'updated_at' => now('Asia/Kolkata'), // Force IST
                ]);
            }
        }
    }

    // public function store(Request $request)
    // {
    //     try {
    //         $validated = $request->validate($this->getValidationRules());
    //         $this->processEntityRelations($validated);
    //         $validated['created_by'] = backpack_user()->id;

    //         // Reference leads get REFERENCE immediately, otherwise standard new enquiries get LONG
    //         $isRef = isset($validated['source_code']) && strtoupper($validated['source_code']) === 'REFERENCE';
    //         $validated['origin'] = $isRef ? 'REFERENCE' : 'LONG';
    //         $validated['current_origin'] = $isRef ? 'REFERENCE' : 'LONG';

    //         $validated['cne'] = 1;

    //         $creFields = ['cre_fup_call_duration', 'cre_fup_deviation_stage', 'cre_enq_stage', 'cre_customer_stage', 'cre_fup_remarks', 'cre_next_fup_date'];
    //         $enquiryData = collect($validated)->except($creFields)->toArray();

    //         $enquiry = Enquiry::create($enquiryData);

    //         $this->saveCreFup($enquiry, $request);

    //         Alert::success('Enquiry created successfully.')->flash();
    //         return redirect(backpack_url('enquiry'));
    //     } catch (\Throwable $e) {
    //         Log::error($e->getMessage());
    //         throw $e;
    //     }
    // }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate($this->getValidationRules());
            $this->processEntityRelations($validated);

            // Format all possible date fields for MySQL
            $dateFields = ['virtual_call_date', 'wapp_campaign_date', 'dob', 'marriage_date', 'activity_start_date', 'activity_end_date', 'cre_likely_purchase_date'];
            foreach ($dateFields as $field) {
                if (!empty($validated[$field])) {
                    $validated[$field] = Carbon::parse($validated[$field])->format('Y-m-d H:i:s');
                }
            }

            $validated['created_by'] = backpack_user()->id;

            // Reference leads get REFERENCE immediately, otherwise standard new enquiries get LONG
            $isRef = isset($validated['source_code']) && strtoupper($validated['source_code']) === 'REFERENCE';
            $validated['origin'] = $isRef ? 'REFERENCE' : 'LONG';
            $validated['current_origin'] = $isRef ? 'REFERENCE' : 'LONG';

            $validated['cne'] = 1;
            $validated['x8_enq_assign_date'] = now();

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

    // public function update(Request $request, $id)
    // {
    //     $enquiry = Enquiry::findOrFail($id);
    //     $validated = $request->validate($this->getValidationRules($id));
    //     $this->processEntityRelations($validated);

    //     // Format all possible date fields for MySQL
    //     $dateFields = ['virtual_call_date', 'wapp_campaign_date', 'dob', 'marriage_date', 'activity_start_date', 'activity_end_date', 'cre_likely_purchase_date'];
    //     foreach ($dateFields as $field) {
    //         if (!empty($validated[$field])) {
    //             $validated[$field] = Carbon::parse($validated[$field])->format('Y-m-d H:i:s');
    //         }
    //     }

    //     $validated['updated_by'] = backpack_user()->id;

    //     // When editing a Reference, force current_origin to LONG and cne to true (1)
    //     if (isset($validated['source_code']) && strtoupper($validated['source_code']) === 'REFERENCE') {
    //         $validated['current_origin'] = 'LONG';
    //         $validated['cne'] = 1;
    //     }

    //     if (isset($validated['x8_sc_code']) && $enquiry->x8_sc_code !== $validated['x8_sc_code']) {
    //         $validated['x8_enq_assign_date'] = now();
    //     }

    //     $creFields = ['cre_fup_call_duration', 'cre_fup_deviation_stage', 'cre_enq_stage', 'cre_customer_stage', 'cre_fup_remarks', 'cre_next_fup_date'];
    //     $enquiryData = collect($validated)->except($creFields)->toArray();

    //     // --- MISMATCH TRACKING LOGIC ---
    //     // Only run if the comparison table was actually rendered and submitted
    //     if ($request->has('comparison_rendered')) {
    //         // Now checking IF the box IS checked (meaning the user flagged it as unmatched)
    //         if ($request->has('mismatch_enq_stage')) {
    //             $enquiryData['enq_stage_mismatch'] = $enquiry->enq_stage_mismatch + 1;
    //         }
    //         if ($request->has('mismatch_next_fup')) {
    //             $enquiryData['next_fup_mismatch'] = $enquiry->next_fup_mismatch + 1;
    //         }
    //         if ($request->has('mismatch_fup_remarks')) {
    //             $enquiryData['latest_fup_remarks_mismatch'] = $enquiry->latest_fup_remarks_mismatch + 1;
    //         }
    //         if (!empty($enquiry->test_drive_no) && $request->has('mismatch_test_drive')) {
    //             $enquiryData['test_drive_mismatch'] = $enquiry->test_drive_mismatch + 1;
    //         }
    //     }

    //     $enquiry->update($enquiryData);

    //     $this->saveCreFup($enquiry, $request);

    //     Alert::success('Enquiry updated successfully.')->flash();
    //     return redirect(backpack_url('enquiry'));
    // }

    public function update(Request $request, $id)
    {
        $enquiry = Enquiry::findOrFail($id);
        $validated = $request->validate($this->getValidationRules($id));
        $this->processEntityRelations($validated);

        // Format all possible date fields for MySQL
        $dateFields = ['virtual_call_date', 'wapp_campaign_date', 'dob', 'marriage_date', 'activity_start_date', 'activity_end_date', 'cre_likely_purchase_date'];
        foreach ($dateFields as $field) {
            if (!empty($validated[$field])) {
                $validated[$field] = Carbon::parse($validated[$field])->format('Y-m-d H:i:s');
            }
        }

        $validated['updated_by'] = backpack_user()->id;

        // When editing a Reference, force current_origin to LONG and cne to true (1)
        if (isset($validated['source_code']) && strtoupper($validated['source_code']) === 'REFERENCE') {
            $validated['current_origin'] = 'LONG';
            $validated['cne'] = 1;
        }

        if (isset($validated['x8_sc_code']) && $enquiry->x8_sc_code !== $validated['x8_sc_code']) {
            $validated['x8_enq_assign_date'] = now();
        }

        $creFields = ['cre_fup_call_duration', 'cre_fup_deviation_stage', 'cre_enq_stage', 'cre_customer_stage', 'cre_fup_remarks', 'cre_next_fup_date'];
        $enquiryData = collect($validated)->except($creFields)->toArray();

        // --- MISMATCH TRACKING LOGIC ---
        // Only run if the comparison table was actually rendered and submitted
        if ($request->has('comparison_rendered')) {
            // Now checking IF the box IS checked (meaning the user flagged it as unmatched)
            if ($request->has('mismatch_enq_stage')) {
                $enquiryData['enq_stage_mismatch'] = $enquiry->enq_stage_mismatch + 1;
            }
            if ($request->has('mismatch_next_fup')) {
                $enquiryData['next_fup_mismatch'] = $enquiry->next_fup_mismatch + 1;
            }
            if ($request->has('mismatch_fup_remarks')) {
                $enquiryData['latest_fup_remarks_mismatch'] = $enquiry->latest_fup_remarks_mismatch + 1;
            }
            if (!empty($enquiry->test_drive_no) && $request->has('mismatch_test_drive')) {
                $enquiryData['test_drive_mismatch'] = $enquiry->test_drive_mismatch + 1;
            }
        }

        $enquiry->update($enquiryData);

        $this->saveCreFup($enquiry, $request);

        Alert::success('Enquiry updated successfully.')->flash();

        // --- DYNAMIC REDIRECT LOGIC ---
        // 1. Try to return to the exact previous list using http_referrer
        if ($request->filled('http_referrer') && !str_contains($request->http_referrer, '/edit')) {
            return redirect($request->http_referrer);
        }

        // 2. Fallback routing based on enquiry traits if referrer is missing or invalid
        $source = strtoupper($enquiry->source_code ?? '');
        $origin = strtoupper($enquiry->current_origin ?? '');

        if ($source === 'REFERENCE') {
            return redirect(backpack_url('enquiries/reference'));
        } elseif ($source === 'WHATSAPP') {
            return redirect(backpack_url('enquiries/whatsapp'));
        } elseif ($source === 'HYPERLOCAL') {
            return redirect(backpack_url('enquiries/hyperlocal'));
        } elseif ($origin === 'VIRTUAL') {
            return redirect(backpack_url('enquiries/virtual-number'));
        }

        // 3. Default fallback
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
                // Strictly Mandatory Fields
                'referee_name' => 'required|max:100',
                'referee_phone' => 'required|numeric|digits:10',
                'first_name' => 'required|max:100',
                'mobile' => 'required|numeric|digits:10',
                'segment_code' => 'required',
                'model_code' => 'required',

                // Everything else is optional during Creation
                'referred_by' => 'nullable|max:100',
                'last_name' => 'nullable|max:100',
                'alternate_mobile' => 'nullable|max:15',
                'email' => 'nullable|email|max:150',
                'gender' => 'nullable',
                'zipcode' => 'nullable|max:10',
                'vpo' => 'nullable|max:150',
                'tehsil' => 'nullable|max:100',
                'district' => 'nullable|max:100',
                'city' => 'nullable|max:100',
                'territory' => 'nullable|string|max:100',
                'variant_code' => 'nullable',
                'color_code' => 'nullable',
                'fuel_type' => 'nullable',
                'usage_area' => 'nullable',
                'km_travelled_daily' => 'nullable',
                'application_type' => 'nullable',
                'application' => 'nullable',
                'x8_sc_code' => 'nullable|string|max:200',
                'x8_sc_mile_id' => 'nullable|string|max:100',
            ]);

            $this->processEntityRelations($validated);

            // Required Forced Values
            $validated['enquiry_type'] = 'TELEPHONE';
            $validated['source_code']  = 'REFERENCE';

            // Standard metadata
            $validated['created_by']     = backpack_user()->id;
            $validated['origin']         = 'REFERENCE';
            $validated['current_origin'] = 'REFERENCE';
            $validated['cne']            = 1;

            Enquiry::create($validated);

            Alert::success('Reference Enquiry created successfully.')->flash();
            return redirect(backpack_url('enquiries/reference'));
        } catch (\Throwable $e) {
            Log::error($e->getMessage());
            throw $e;
        }
    }

    // private function processEntityRelations(array &$validated)
    // {
    //     $validated['segment'] = OrgService::segments()[$validated['segment_code']] ?? null;
    //     $validated['model'] = OrgService::models($validated['segment_code'])[$validated['model_code']] ?? null;
    //     if (!empty($validated['variant_code'])) {
    //         $validated['variant'] = OrgService::variants($validated['model_code'])[$validated['variant_code']]['name'] ?? null;
    //     }
    //     if (!empty($validated['color_code'])) {
    //         $validated['color'] = OrgService::colors($validated['variant_code'])[$validated['color_code']] ?? null;
    //     }
    // }

    private function processEntityRelations(array &$validated)
    {
        if (!empty($validated['segment_code'])) {
            $validated['segment'] = OrgService::segments()[$validated['segment_code']] ?? null;
        }
        if (!empty($validated['segment_code']) && !empty($validated['model_code'])) {
            $validated['model'] = OrgService::models($validated['segment_code'])[$validated['model_code']] ?? null;
        }
        if (!empty($validated['model_code']) && !empty($validated['variant_code'])) {
            $validated['variant'] = OrgService::variants($validated['model_code'])[$validated['variant_code']]['name'] ?? null;
        }
        if (!empty($validated['variant_code']) && !empty($validated['color_code'])) {
            $validated['color'] = OrgService::colors($validated['variant_code'])[$validated['color_code']] ?? null;
        }
    }

    private function getValidationRules($id = null)
    {
        $fullFormActive = request()->has('segment_code') || !request()->has('call_nature');

        // 1. FAST-PATH BYPASS: For Non-Sales Virtual Enquiries
        if (!$fullFormActive) {
            return [
                'call_nature' => 'required|string',
                'mobile' => 'required|max:15',
                'remarks' => 'nullable|string',
                'virtual_no' => 'nullable|string',
                'virtual_call_date' => 'nullable',
                'call_duration' => 'nullable|string',
                'dealer_branch' => 'nullable',
                'dealer_location' => 'nullable',
            ];
        }

        // 2. STANDARD VALIDATION RULES
        $req = 'required';

        $sourceCode = strtoupper(request()->input('source_code', ''));
        $isRef = $sourceCode === 'REFERENCE';

        // Dynamic field requirements based on source
        $refReq = $isRef ? 'required' : 'nullable';

        // If the frontend disabled source_code (e.g., for Walk-In), it won't be sent in the request.
        $sourceReq = request()->has('source_code') ? 'required' : 'nullable';

        // CRE fields are only mandatory on EDIT
        $isEdit = $id !== null;
        $creReq = $isEdit ? 'required' : 'nullable';

        return [
            'enquiry_type' => $req,
            'source_code' => $sourceReq,
            'sub_source' => 'nullable',
            'person_code' => 'nullable',
            'reference_details' => 'nullable|max:255',

            // Reference Details (Mandatory if Source is Reference)
            'referred_by' => $refReq . '|max:100',
            'referee_phone' => $refReq . '|max:15',
            'referee_name' => $refReq . '|max:100',

            'planned_campaign' => 'nullable|max:150',
            'likely_purchase_days' => 'nullable|max:150',
            'cre_likely_purchase_date' => 'nullable|date',
            'cre_likely_purchase_days' => 'nullable|max:150',
            'activity_type' => 'nullable',
            'activity_segment' => 'nullable',
            'activity_model' => 'nullable',
            'activity_start_date' => 'nullable|date',
            'activity_end_date' => 'nullable|date',
            'activity_branch' => 'nullable',
            'activity_location' => 'nullable',

            // 1. Customer Primary Details
            'first_name' => $req . '|max:100',
            'last_name' => 'nullable',
            'mobile' => 'required|max:15',
            'alternate_mobile' => 'nullable|max:15',
            'email' => 'nullable|email|max:150',
            'gender' => $req,
            'zipcode' => $req . '|max:10',
            'vpo' => $req . '|max:150',
            'tehsil' => $req . '|max:100',
            'district' => $req . '|max:100',
            'city' => $req . '|max:100',
            'territory' => $req . '|string|max:100',

            // 2. Vehicle Info (Color is now optional everywhere)
            'segment_code' => $req,
            'model_code' => $req,
            'variant_code' => $req,
            'color_code' => 'nullable',
            'fuel_type' => 'nullable', // Fetched automatically
            'usage_area' => 'nullable', // Checked dynamically by HTML5 based on segment
            'km_travelled_daily' => 'nullable',
            'application_type' => 'nullable',
            'application' => 'nullable',

            // 3. X8 SC Details (Optional on Create, Mandatory on Edit)
            'x8_sc_code' => ($isEdit ? 'required' : 'nullable') . '|string|max:200',
            'x8_sc_mile_id' => 'nullable|string|max:100',

            // 4. CRM Purchase Type
            'purchase_type_crm' => $req . '|string|max:100',

            // 5. CRE Enquiry Stage (No longer mandatory)
            'cre_enq_stage' => 'nullable|string|max:50',
            'cre_customer_stage' => 'nullable|string|max:50',
            'cre_next_fup_date' => 'nullable|date',
            'cre_fup_remarks' => 'nullable|string|max:255',
            'cre_fup_deviation_stage' => 'nullable|string|max:50',

            // WhatsApp Campaign Fields
            'wapp_campaign_name' => 'nullable|string|max:150',
            'wapp_campaign_date' => 'nullable|date',
            'wapp_campaign_segment' => 'nullable|string|max:50',
            'wapp_campaign_model' => 'nullable|string|max:150',

            // --- OTHER EXISTING FIELDS ---
            'occupation_type' => 'nullable',
            'customer_type' => 'nullable',
            'occupation_sub_type' => 'nullable',
            'company_name' => 'nullable|max:150',
            'dob' => 'nullable|date',
            'marital_status' => 'nullable',
            'marriage_date' => 'nullable|date',
            'age_group' => 'nullable',
            'has_ev' => 'nullable',
            'purchase_type' => 'nullable',
            'consider_make' => 'nullable|max:100',
            'consider_model' => 'nullable|max:100',
            'consider_variant' => 'nullable|max:100',
            'consid_brand2' => 'nullable|max:100',
            'consid_model2' => 'nullable|max:100',
            'consid_variant2' => 'nullable|max:100',
            'vehicle_no' => 'nullable|max:30',
            'remarks' => 'nullable',
            'transmission' => 'nullable',
            'drivetrain' => 'nullable',
            'seating' => 'nullable',
            'place_of_registration' => 'nullable|max:100',
            // 'dealer_branch' => $req,
            // 'dealer_location' => $req,
            'sc_code' => 'nullable',
            'booking_no' => 'nullable|string|max:50',
            'otf_no' => 'nullable|string|max:50',
            'dms_enq_no' => 'nullable|string|max:50',
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
            // 'campaigns' => Campaign::orderBy('name')->pluck('name')->toArray(),
            'campaigns' => Campaign::where('forever', 1)
                ->orWhereDate('end_date', '>=', Carbon::today())
                ->orderBy('name')
                ->pluck('name')
                ->toArray(),
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
            'deviation_stages' => $kw('DEVIATION_STAGE'),
            'enquiry_stages' => $kw('ENQUIRY_STAGE'),
            'customer_stages' => $kw('CUSTOMER_STAGE'),
            'purchase_types' => $kw('PURCHASE_TYPE'),
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

    public function getKeywordValues($keyword, $parent, Request $request)
    {
        return response()->json(
            OrgService::keywordValueByParentCode($keyword, $parent, $request->query('parent_keyword'))
        );
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
        return response()->json([
            'exists' => (bool) $enquiry,
            'enquiry_no' => $enquiry?->enquiry_no,
            'id' => $enquiry?->id
        ]);
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
