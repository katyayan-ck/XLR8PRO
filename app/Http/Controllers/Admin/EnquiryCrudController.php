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
use Throwable;


class EnquiryCrudController extends CrudController
{
    use CreateOperation;
    use DeleteOperation;
    use ListOperation;
    use UpdateOperation;

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

        return view('admin.enquiry.list', [

            'title' => 'Xlr8 Enquiries',


            'gridConfig' => [

                'columns' => [
                    ['field' => 'serial_no', 'headerName' => 'S.No'],
                    ['field' => 'enquiry_no', 'headerName' => 'Enquiry No'],
                    ['field' => 'enquiry_type', 'headerName' => 'Enquiry Type'],
                    ['field' => 'source_name', 'headerName' => 'Source'],
                    ['field' => 'sub_source', 'headerName' => 'Sub Source'],
                    ['field' => 'person_code', 'headerName' => 'Person Code'],
                    ['field' => 'reference_details', 'headerName' => 'Reference Details'],
                    ['field' => 'referred_by', 'headerName' => 'Referred By'],
                    ['field' => 'referee_phone', 'headerName' => 'Referee Phone'],
                    ['field' => 'referee_name', 'headerName' => 'Referee Name'],
                    ['field' => 'planned_campaign_name', 'headerName' => 'Planned Campaign'],
                    ['field' => 'likely_purchase_date', 'headerName' => 'Likely Purchase Date'],
                    ['field' => 'activity_type', 'headerName' => 'Activity Type'],
                    ['field' => 'activity_segment', 'headerName' => 'Activity Segment'],
                    ['field' => 'activity_model', 'headerName' => 'Activity Model'],
                    ['field' => 'activity_start_date', 'headerName' => 'Activity Start'],
                    ['field' => 'activity_end_date', 'headerName' => 'Activity End'],
                    ['field' => 'activity_branch', 'headerName' => 'Activity Branch'],
                    ['field' => 'activity_location', 'headerName' => 'Activity Location'],
                    ['field' => 'first_name', 'headerName' => 'First Name'],
                    ['field' => 'last_name', 'headerName' => 'Last Name'],
                    ['field' => 'full_name', 'headerName' => 'Full Name'],
                    ['field' => 'mobile', 'headerName' => 'Mobile'],
                    ['field' => 'email', 'headerName' => 'Email'],
                    ['field' => 'occupation_type', 'headerName' => 'Occupation Type'],
                    ['field' => 'occupation_sub_type', 'headerName' => 'Occupation Sub Type'],
                    ['field' => 'customer_type', 'headerName' => 'Customer Type'],
                    ['field' => 'company_name', 'headerName' => 'Company Name'],
                    ['field' => 'gender', 'headerName' => 'Gender'],
                    ['field' => 'dob', 'headerName' => 'DOB'],
                    ['field' => 'marital_status', 'headerName' => 'Marital Status'],
                    ['field' => 'marriage_date', 'headerName' => 'Marriage Date'],
                    ['field' => 'age_group', 'headerName' => 'Age Group'],
                    ['field' => 'zipcode', 'headerName' => 'Zipcode'],
                    ['field' => 'tehsil', 'headerName' => 'Tehsil'],
                    ['field' => 'district', 'headerName' => 'District'],
                    ['field' => 'city', 'headerName' => 'City'],
                    ['field' => 'has_ev', 'headerName' => 'Has EV'],
                    ['field' => 'purchase_type', 'headerName' => 'Purchase Type'],
                    ['field' => 'exchange_make', 'headerName' => 'Exchange Make'],
                    ['field' => 'exchange_model', 'headerName' => 'Exchange Model'],
                    ['field' => 'vehicle_no', 'headerName' => 'Vehicle No'],
                    ['field' => 'remarks', 'headerName' => 'Remarks'],
                    ['field' => 'segment_name', 'headerName' => 'Segment'],
                    ['field' => 'model_name', 'headerName' => 'Model'],
                    ['field' => 'variant_name', 'headerName' => 'Variant'],
                    ['field' => 'color_name', 'headerName' => 'Color'],
                    ['field' => 'fuel_type', 'headerName' => 'Fuel Type'],
                    ['field' => 'transmission', 'headerName' => 'Transmission'],
                    ['field' => 'drivetrain', 'headerName' => 'Drivetrain'],
                    ['field' => 'seating', 'headerName' => 'Seating'],
                    ['field' => 'usage_area', 'headerName' => 'Usage Area'],
                    ['field' => 'km_travelled_daily', 'headerName' => 'KM/Day'],
                    ['field' => 'application_type', 'headerName' => 'Application Type'],
                    ['field' => 'application', 'headerName' => 'Application'],
                    ['field' => 'place_of_registration', 'headerName' => 'Place Of Registration'],
                    ['field' => 'dealer_branch', 'headerName' => 'Dealer Branch'],
                    ['field' => 'dealer_location', 'headerName' => 'Dealer Location'],
                    ['field' => 'sc_code', 'headerName' => 'Sales Consultant'],
                    ['field' => 'followup_type', 'headerName' => 'Followup Type'],
                    ['field' => 'followup_date', 'headerName' => 'Followup Date'],
                    ['field' => 'followup_time', 'headerName' => 'Followup Time'],
                    ['field' => 'action', 'headerName' => 'Action']
                ],
                'data' => []
            ]
        ]);
    }

    public function data(Request $request)
    {
        $startRow = max(0, (int) $request->input('startRow', 0));
        $endRow = max($startRow + 1, (int) $request->input('endRow', $startRow + 100));
        $limit = $endRow - $startRow;

        $searchText = trim((string) $request->input('searchText', ''));
        $sortModel = (array) $request->input('sortModel', []);

        $query = Enquiry::formComplete()->with(['segment', 'model', 'variant', 'color', 'campaign']);

        $this->applyEnquirySearch($query, $searchText);
        $this->applyEnquirySort($query, $sortModel);

        // Total matching rows, so AG-Grid knows when it has reached the end.
        $total = (clone $query)->count();

        $enquiries = $query->skip($startRow)->take($limit)->get();

        $gridData = $enquiries->values()
            ->map(fn($enquiry, $i) => $this->mapEnquiryToGridRow($enquiry, $startRow + $i + 1))
            ->all();

        return response()->json([
            'rows' => $gridData,
            'lastRow' => $total,
        ]);
    }


    public function export(Request $request)
    {
        $searchText = trim((string) $request->input('searchText', ''));

        $query = Enquiry::formComplete()->with(['segment', 'model', 'variant', 'color', 'campaign']);
        $this->applyEnquirySearch($query, $searchText);
        $query->orderByDesc('created_at');

        $filename = 'enquiries-' . now()->format('Y-m-d_His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');

            fputcsv($out, [
                'S.No',
                'Enquiry No',
                'Enquiry Type',
                'Source',
                'Sub Source',
                'Full Name',
                'Mobile',
                'Email',
                'Segment',
                'Model',
                'Variant',
                'Color',
                'City',
                'Dealer Branch',
                'Dealer Location',
                'Followup Date',
            ]);

            $serial = 0;

            $query->chunk(500, function ($chunk) use ($out, &$serial) {
                foreach ($chunk as $enquiry) {
                    $serial++;

                    fputcsv($out, [
                        $serial,
                        $enquiry->enquiry_no,
                        $enquiry->enquiry_type,
                        $enquiry->source_code,
                        $enquiry->sub_source,
                        $enquiry->full_name,
                        $enquiry->mobile,
                        $enquiry->email,
                        $enquiry->segment?->name,
                        $enquiry->model?->name,
                        $enquiry->variant?->display_name
                        ?? $enquiry->variant?->custom_name
                        ?? $enquiry->variant?->oem_name,
                        $enquiry->color?->name,
                        $enquiry->city,
                        $enquiry->dealer_branch,
                        $enquiry->dealer_location,
                        $enquiry->followup_date
                        ? Carbon::parse($enquiry->followup_date)->format('d-m-Y')
                        : '',
                    ]);
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }


    private function mapEnquiryToGridRow(Enquiry $enquiry, int $serialNo): array
    {
        $mapped = $enquiry->toArray();

        $mapped['serial_no'] = $serialNo;

        $mapped['full_name'] = $enquiry->full_name;

        $mapped['source_name'] = $enquiry->source_code ?? '—';

        $mapped['segment_name'] = $enquiry->segment?->name ?? '—';

        $mapped['model_name'] = $enquiry->model?->name ?? '—';

        $mapped['variant_name'] = $enquiry->variant?->display_name
            ?? $enquiry->variant?->custom_name
            ?? $enquiry->variant?->oem_name
            ?? '—';

        $mapped['color_name'] = $enquiry->color?->name ?? '—';

        $mapped['planned_campaign_name'] = $enquiry->campaign?->name
            ?? $enquiry->planned_campaign
            ?? '—';

        $mapped['likely_purchase_date'] = $enquiry->likely_purchase_date;

        $mapped['activity_start_date'] = $enquiry->activity_start_date
            ? Carbon::parse($enquiry->activity_start_date)->format('d-m-Y')
            : '—';

        $mapped['activity_end_date'] = $enquiry->activity_end_date
            ? Carbon::parse($enquiry->activity_end_date)->format('d-m-Y')
            : '—';

        $mapped['dob'] = $enquiry->dob
            ? Carbon::parse($enquiry->dob)->format('d-m-Y')
            : '—';

        $mapped['marriage_date'] = $enquiry->marriage_date
            ? Carbon::parse($enquiry->marriage_date)->format('d-m-Y')
            : '—';

        $mapped['followup_date'] = $enquiry->followup_date
            ? Carbon::parse($enquiry->followup_date)->format('d-m-Y')
            : '—';

        $editUrl = backpack_url("enquiry/{$enquiry->id}/edit");

        $quotationUrl = backpack_url(
            "quotation-form/create?enquiry_id={$enquiry->id}"
        );

        $mapped['action'] = '
        <div class="d-flex justify-content-center gap-2">
            <a href="' . $editUrl . '" class="btn btn-sm btn-primary">
                Edit
            </a>
            <a href="' . $quotationUrl . '" class="btn btn-success btn-sm">
                Form
            </a>
        </div>';

        return $mapped;
    }


    private function applyEnquirySearch($query, string $searchText): void
    {
        if ($searchText === '') {
            return;
        }

        $like = "%{$searchText}%";

        $query->where(function ($q) use ($like) {
            $q->where('enquiry_no', 'like', $like)
                ->orWhere('first_name', 'like', $like)
                ->orWhere('last_name', 'like', $like)
                ->orWhere('mobile', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('source_code', 'like', $like)
                ->orWhere('sub_source', 'like', $like)
                ->orWhere('company_name', 'like', $like)
                ->orWhere('vehicle_no', 'like', $like)
                ->orWhere('city', 'like', $like)
                ->orWhere('zipcode', 'like', $like)
                ->orWhereHas('model', fn($q2) => $q2->where('name', 'like', $like))
                ->orWhereHas('segment', fn($q2) => $q2->where('name', 'like', $like))
                ->orWhereHas('color', fn($q2) => $q2->where('name', 'like', $like))
                ->orWhereHas('variant', function ($q2) use ($like) {
                    $q2->where('display_name', 'like', $like)
                        ->orWhere('custom_name', 'like', $like)
                        ->orWhere('oem_name', 'like', $like);
                });
        });
    }

    private function applyEnquirySort($query, array $sortModel): void
    {
        $sortableColumns = [
            'enquiry_no',
            'enquiry_type',
            'sub_source',
            'person_code',
            'first_name',
            'last_name',
            'mobile',
            'email',
            'occupation_type',
            'customer_type',
            'company_name',
            'gender',
            'dob',
            'marital_status',
            'city',
            'district',
            'purchase_type',
            'created_at',
        ];

        $sortApplied = false;

        foreach ($sortModel as $sort) {
            $colId = $sort['colId'] ?? null;
            $direction = strtolower($sort['sort'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

            if ($colId && in_array($colId, $sortableColumns, true)) {
                $query->orderBy($colId, $direction);
                $sortApplied = true;
            }
        }

        if (!$sortApplied) {
            $query->orderByDesc('created_at');
        }
    }


    private function getEnquiryFormData()
    {
        $data = [];

        $data['segments'] = OrgService::segments();
        $data['models'] = [];
        $data['variants'] = [];
        $data['colors'] = [];

        $data['saleconsultants'] = OrgService::getUsers(desigCode: 'CNS');

        $data['enquiry_types'] = OrgService::keywordValueByCode('ENQUIRY_TYPE');
        $data['activity_types'] = OrgService::keywordValueByCode('ACTIVITY_TYPE');
        $data['likely_purchase_dates'] = OrgService::keywordValueByCode('LIKELY_PURCHASE_DATE');
        $data['follow_up_types'] = OrgService::keywordValueByCode('FOLLOW_UP_TYPE');

        $data['occupation_types'] = OrgService::keywordValueByCode('OCCUPATION_TYPE');
        $data['occupation_sub_types'] = OrgService::keywordValueByCode('OCCUPATION_SUB_TYPE');

        $data['customer_types'] = OrgService::keywordValueByCode('CUSTOMER_TYPE');
        $data['genders'] = OrgService::keywordValueByCode('GENDER');
        $data['marital_statuses'] = OrgService::keywordValueByCode('MARITAL_STATUS');

        $data['age_groups'] = OrgService::keywordValueByCode('AGE_GROUP');

        $data['usage_areas'] = OrgService::keywordValueByCode('USAGE_AREA');
        $data['km_travelled_daily'] = OrgService::keywordValueByCode('KM_TRAVELLED_DAILY');

        $data['application_types'] = OrgService::keywordValueByCode('APPLICATION_TYPE');
        $data['applications'] = OrgService::keywordValueByCode('APPLICATION');

        $data['call_nature_virtual'] = OrgService::keywordValueByCode('CALL_NATURE_VIRTUAL');

        $data['sc_fup_remarks'] = OrgService::keywordValueByCode('SC_FUP_REMARKS');
        $data['sc_fup_remarks_types'] = OrgService::keywordValueByCode('SC_FUP_REMARKS_TYPE');

        // Existing Keyword
        $data['enquiry_sources'] = OrgService::keywordValueByCode('ENQ_SOURCE');

        $data['enquiry_sub_sources'] = OrgService::keywordValueByCode('ENQUIRY_SUB_SOURCE');

        $data['branches'] = OrgService::branches();

        $data['locations'] = [];

        $data['existing_car_oems'] = OrgService::keywordValueByCode('EXISTING_CAR_OEM');
        $data['existing_car_models'] = OrgService::keywordValueByCode('EXISTING_CAR_MODEL');
        $data['existing_car_variants'] = OrgService::keywordValueByCode('EXISTING_CAR_VARIANT');
        $data['fuel_types'] = OrgService::keywordValueByCode('FUEL_TYPE');
        $data['transmission_types'] = OrgService::keywordValueByCode('TRANSMISSION_TYPE');
        $data['finance_types'] = OrgService::keywordValueByCode('FINANCE_TYPE');
        $data['purchase_reasons'] = OrgService::keywordValueByCode('PURCHASE_REASON');
        $data['campaigns'] = Campaign::orderBy('name')
            ->pluck('name')
            ->toArray();

        return $data;
    }

    public function create()
    {
        $data = $this->getEnquiryFormData();

        $data['title'] = 'Add New Enquiry';

        return view('admin.enquiry.create', $data);
    }

    public function edit($id)
    {
        $data = $this->getEnquiryFormData();

        $data['title'] = 'Edit Enquiry';

        $data['enquiry'] = Enquiry::with([
            'campaign',
            'segment',
            'model',
            'variant',
            'color',
        ])->findOrFail($id);

        // dd([
        //     'dealer_branch'   => $data['enquiry']->dealer_branch,
        //     'dealer_location' => $data['enquiry']->dealer_location,
        // ]);

        return view('admin.enquiry.create', $data);
    }

    // public function store(Request $request)
    // {
    //     $validated = $request->validate([
    //         'enquiry_no' => 'required|unique:xlr8_crm_enquiries,enquiry_no',
    //         'enquiry_type' => 'required',
    //         'source_code' => 'required',
    //         'sub_source' => 'nullable',
    //         'person_code' => 'nullable',
    //         'reference_details' => 'nullable|max:255',
    //         'referred_by' => 'nullable|max:100',
    //         'referee_phone' => 'nullable|max:15',
    //         'referee_name' => 'nullable|max:100',
    //         'planned_campaign' => 'nullable|max:150',
    //         'likely_purchase_date' => 'nullable|max:150',
    //         'activity_type' => 'nullable',
    //         'activity_segment' => 'nullable',
    //         'activity_model' => 'nullable',
    //         'activity_start_date' => 'nullable|date',
    //         'activity_end_date' => 'nullable|date',
    //         'activity_branch' => 'nullable',
    //         'activity_location' => 'nullable',
    //         'first_name' => 'required|max:100',
    //         'last_name' => 'nullable|max:100',
    //         'mobile' => 'required|max:15',
    //         'email' => 'nullable|email|max:150',
    //         'occupation_type' => 'nullable',
    //         'customer_type' => 'nullable',
    //         'occupation_sub_type' => 'nullable',
    //         'company_name' => 'nullable|max:150',
    //         'gender' => 'nullable',
    //         'dob' => 'nullable|date',
    //         'marital_status' => 'nullable',
    //         'marriage_date' => 'nullable|date',
    //         'age_group' => 'nullable',
    //         'zipcode' => 'nullable|max:10',
    //         'tehsil' => 'nullable|max:100',
    //         'district' => 'nullable|max:100',
    //         'city' => 'nullable|max:100',
    //         'has_ev' => 'nullable',
    //         'purchase_type' => 'nullable',
    //         'exchange_make' => 'nullable|max:100',
    //         'exchange_model' => 'nullable|max:100',
    //         'vehicle_no' => 'nullable|max:30',
    //         'remarks' => 'nullable',
    //         'segment_code' => 'required',
    //         'model_code' => 'required',
    //         'variant_code' => 'required',
    //         'color_code' => 'required',
    //         'fuel_type' => 'nullable',
    //         'transmission' => 'nullable',
    //         'drivetrain' => 'nullable',
    //         'seating' => 'nullable',
    //         'usage_area' => 'nullable',
    //         'km_travelled_daily' => 'nullable',
    //         'application_type' => 'nullable',
    //         'application' => 'nullable',
    //         'place_of_registration' => 'nullable|max:100',
    //         'dealer_branch' => 'required',
    //         'dealer_location' => 'required',
    //         'sc_code' => 'required',
    //         'followup_type' => 'nullable',
    //         'followup_date' => 'nullable|date',
    //         'followup_time' => 'nullable'
    //     ]);

    //     // Save Segment Name
    //     $segments = OrgService::segments();
    //     $validated['segment'] = $segments[$validated['segment_code']] ?? null;

    //     // Save Model Name
    //     $models = OrgService::models($validated['segment_code']);
    //     $validated['model'] = $models[$validated['model_code']] ?? null;

    //     // Save Variant Name
    //     $variants = OrgService::variants($validated['model_code']);
    //     $validated['variant'] = $variants[$validated['variant_code']]['name'] ?? null;

    //     // Save Color Name
    //     $colors = OrgService::colors($validated['variant_code']);
    //     $validated['color'] = $colors[$validated['color_code']] ?? null;

    //     $validated['created_by'] = backpack_user()->id;

    //     Enquiry::create($validated);

    //     \Alert::success('Enquiry created successfully.')->flash();

    //     return redirect(backpack_url('enquiry'));
    // }

    public function store(Request $request)
    {
        Log::info('==================== ENQUIRY STORE START ====================');

        Log::info('Incoming Request', $request->all());

        try {

            $validated = $request->validate([
                'enquiry_no' => 'required|unique:xlr8_crm_enquiries,enquiry_no',
                'enquiry_type' => 'required',
                'source_code' => 'required',
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
                'first_name' => 'required|max:100',
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
                'zipcode' => 'nullable|max:10',
                'tehsil' => 'nullable|max:100',
                'district' => 'nullable|max:100',
                'city' => 'nullable|max:100',
                'has_ev' => 'nullable',
                'purchase_type' => 'nullable',
                'exchange_make' => 'nullable|max:100',
                'exchange_model' => 'nullable|max:100',
                'vehicle_no' => 'nullable|max:30',
                'remarks' => 'nullable',
                'segment_code' => 'required',
                'model_code' => 'required',
                'variant_code' => 'required',
                'color_code' => 'required',
                'fuel_type' => 'nullable',
                'transmission' => 'nullable',
                'drivetrain' => 'nullable',
                'seating' => 'nullable',
                'usage_area' => 'nullable',
                'km_travelled_daily' => 'nullable',
                'application_type' => 'nullable',
                'application' => 'nullable',
                'place_of_registration' => 'nullable|max:100',
                'dealer_branch' => 'required',
                'dealer_location' => 'required',
                'sc_code' => 'required',
                'followup_type' => 'nullable',
                'followup_date' => 'nullable|date',
                'followup_time' => 'nullable'
            ]);

            Log::info('Validation Passed', $validated);

            /*
            |--------------------------------------------------------------------------
            | Segment
            |--------------------------------------------------------------------------
            */

            $segments = OrgService::segments();

            Log::info('Segments Master Loaded', [
                'count' => count($segments),
                'selected_code' => $validated['segment_code'],
            ]);

            $validated['segment'] = $segments[$validated['segment_code']] ?? null;

            Log::info('Segment Selected', [
                'code' => $validated['segment_code'],
                'name' => $validated['segment'],
            ]);

            /*
            |--------------------------------------------------------------------------
            | Model
            |--------------------------------------------------------------------------
            */

            $models = OrgService::models($validated['segment_code']);

            Log::info('Models Master Loaded', [
                'count' => count($models),
                'selected_code' => $validated['model_code'],
            ]);

            $validated['model'] = $models[$validated['model_code']] ?? null;

            Log::info('Model Selected', [
                'code' => $validated['model_code'],
                'name' => $validated['model'],
            ]);

            /*
            |--------------------------------------------------------------------------
            | Variant
            |--------------------------------------------------------------------------
            */

            $variants = OrgService::variants($validated['model_code']);

            Log::info('Variants Master Loaded', [
                'count' => count($variants),
                'selected_code' => $validated['variant_code'],
            ]);

            Log::info('Selected Variant Data', [
                'variant' => $variants[$validated['variant_code']] ?? null
            ]);

            $validated['variant'] = $variants[$validated['variant_code']]['name'] ?? null;

            Log::info('Variant Selected', [
                'code' => $validated['variant_code'],
                'name' => $validated['variant'],
            ]);

            /*
            |--------------------------------------------------------------------------
            | Color
            |--------------------------------------------------------------------------
            */

            $colors = OrgService::colors($validated['variant_code']);

            Log::info('Colors Master Loaded', [
                'count' => count($colors),
                'selected_code' => $validated['color_code'],
            ]);

            $validated['color'] = $colors[$validated['color_code']] ?? null;

            Log::info('Color Selected', [
                'code' => $validated['color_code'],
                'name' => $validated['color'],
            ]);

            /*
            |--------------------------------------------------------------------------
            | Final Payload
            |--------------------------------------------------------------------------
            */

            $validated['created_by'] = backpack_user()->id;

            $validated['origin'] = 'QUICK';
            $validated['current_origin'] = 'QUICK';
            $validated['cne'] = 1;

            Log::info('Final Payload Before Create', $validated);

            /*
            |--------------------------------------------------------------------------
            | Save
            |--------------------------------------------------------------------------
            */

            $enquiry = Enquiry::create($validated);

            Log::info('Enquiry Saved Successfully', [
                'id' => $enquiry->id,
                'enquiry_no' => $enquiry->enquiry_no,
                'segment' => $enquiry->segment,
                'segment_code' => $enquiry->segment_code,
                'model' => $enquiry->model,
                'model_code' => $enquiry->model_code,
                'variant' => $enquiry->variant,
                'variant_code' => $enquiry->variant_code,
                'color' => $enquiry->color,
                'color_code' => $enquiry->color_code,
            ]);

            Log::info('==================== ENQUIRY STORE END ====================');

            \Alert::success('Enquiry created successfully.')->flash();

            return redirect(backpack_url('enquiry'));

        } catch (Throwable $e) {

            Log::error('==================== ENQUIRY STORE FAILED ====================');

            Log::error('Message', [
                'message' => $e->getMessage(),
            ]);

            Log::error('File', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            Log::error('Trace', [
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    public function update(Request $request, $id)
    {
        $enquiry = Enquiry::findOrFail($id);

        $validated = $request->validate([
            'enquiry_no' => 'required|unique:xlr8_crm_enquiries,enquiry_no,' . $id,
            'enquiry_type' => 'required',
            'source_code' => 'required',
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
            'first_name' => 'required|max:100',
            'last_name' => 'nullable|max:100',
            'mobile' => 'required|max:15',
            'email' => 'nullable|email|max:150',
            'occupation_type' => 'nullable',
            'occupation_sub_type' => 'nullable',
            'customer_type' => 'nullable',
            'company_name' => 'nullable|max:150',
            'gender' => 'nullable',
            'dob' => 'nullable|date',
            'marital_status' => 'nullable',
            'marriage_date' => 'nullable|date',
            'age_group' => 'nullable',
            'zipcode' => 'nullable|max:10',
            'tehsil' => 'nullable|max:100',
            'district' => 'nullable|max:100',
            'city' => 'nullable|max:100',
            'has_ev' => 'nullable',
            'purchase_type' => 'nullable',
            'exchange_make' => 'nullable|max:100',
            'exchange_model' => 'nullable|max:100',
            'vehicle_no' => 'nullable|max:30',
            'remarks' => 'nullable',
            'segment_code' => 'required',
            'model_code' => 'required',
            'variant_code' => 'required',
            'color_code' => 'required',
            'fuel_type' => 'nullable',
            'transmission' => 'nullable',
            'drivetrain' => 'nullable',
            'seating' => 'nullable',
            'usage_area' => 'nullable',
            'km_travelled_daily' => 'nullable',
            'application_type' => 'nullable',
            'application' => 'nullable',
            'place_of_registration' => 'nullable|max:100',
            'dealer_branch' => 'required',
            'dealer_location' => 'required',
            'sc_code' => 'required',
            'followup_type' => 'nullable',
            'followup_date' => 'nullable|date',
            'followup_time' => 'nullable'
        ]);

        // Save Segment Name
        $segments = OrgService::segments();
        $validated['segment'] = $segments[$validated['segment_code']] ?? null;

        // Save Model Name
        $models = OrgService::models($validated['segment_code']);
        $validated['model'] = $models[$validated['model_code']] ?? null;

        // Save Variant Name
        $variants = OrgService::variants($validated['model_code']);
        $validated['variant'] = $variants[$validated['variant_code']]['name'] ?? null;

        // Save Color Name
        $colors = OrgService::colors($validated['variant_code']);
        $validated['color'] = $colors[$validated['color_code']] ?? null;

        $validated['updated_by'] = backpack_user()->id;

        $enquiry->update($validated);

        \Alert::success('Enquiry updated successfully.')->flash();

        return redirect(backpack_url('enquiry'));
    }

    public function getSources()
    {
        $sources = LeadSource::active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name', 'code')
            ->toArray();

        return response()->json($sources);
    }

    public function getLead($leadNo)
    {
        $lead = Lead::where(
            'lead_no',
            $leadNo
        )->firstOrFail();

        return response()->json([

            'source_code' => $lead->source_code,

            'referral_details' => $lead->referral_details,

            'first_name' => $lead->first_name,

            'last_name' => $lead->last_name,

            'mobile' => $lead->mobile,

            'email' => $lead->email,

            'occupation' => $lead->occupation,

            'segment_code' => $lead->segment_code,

            'model_code' => $lead->model_code,

            'variant_code' => $lead->variant_code,

            'color_code' => $lead->color_code,

        ]);
    }

    public function getVariants($modelCode)
    {
        return response()->json(
            OrgService::variants($modelCode)
        );
    }



    public function getColors($variantCode)
    {
        return response()->json(
            OrgService::colors($variantCode)
        );
    }

    public function getModels(string $segmentCode)
    {
        return response()->json(
            OrgService::models($segmentCode)
        );
    }

    public function getLocations($branchCode)
    {
        return response()->json(
            OrgService::locations($branchCode)
        );
    }

    public function getKeywordValues($keyword, $parent)
    {
        return response()->json(
            OrgService::keywordValueByParentCode(
                $keyword,
                $parent
            )
        );
    }

    public function getReferenceUsers(Request $request)
    {
        return response()->json(
            OrgService::getReferenceUsers(
                $request->type,
                $request->mobile
            )
        );
    }

    public function referenceList()
    {
        $this->crud->setListView('admin.enquiry.reference-enquiry');

        // ->reference() = currentOrigin('REFERENCE') + active() (status = 1),
        // both already defined on the model.
        // ->formIncomplete() keeps it out of here once it graduates to the plain Enquiry List.
        $enquiries = Enquiry::reference()
            ->formIncomplete()
            ->with(['model', 'variant'])
            ->orderByDesc('created_at')
            ->get();

        $gridData = $enquiries->map(function ($enquiry, $index) {

            $editUrl = backpack_url("enquiry/{$enquiry->id}/edit");

            return [
                'serial_no' => $index + 1,
                'enq_date_and_time' => $enquiry->enquiry_date
                    ? Carbon::parse($enquiry->enquiry_date)->format('d-m-Y H:i')
                    : '—',
                'referred_by' => $enquiry->referred_by ?? '—',
                'referee_phone' => $enquiry->referee_phone ?? '—',
                'customer_name' => $enquiry->full_name,
                'customer_phone' => $enquiry->mobile ?? '—',
                'model_code' => $enquiry->model?->name ?? $enquiry->model_code ?? '—',
                'variant_code' => $enquiry->variant?->display_name ?? $enquiry->variant_code ?? '—',
                'action' => '
                    <div class="d-flex justify-content-center gap-2">
                        <a href="' . $editUrl . '" class="btn btn-sm btn-primary">Edit</a>
                    </div>',
            ];
        })->values();

        return view('admin.enquiry.reference-enquiry', [
            'title' => 'Reference Enquiries',
            'gridConfig' => [
                'columns' => [
                    ['field' => 'serial_no', 'headerName' => 'S.No'],
                    ['field' => 'enq_date_and_time', 'headerName' => 'Enquiry Date & Time'],
                    ['field' => 'referred_by', 'headerName' => 'Referred By'],
                    ['field' => 'referee_phone', 'headerName' => 'Referee Mobile'],
                    ['field' => 'customer_name', 'headerName' => 'Customer Name'],
                    ['field' => 'customer_phone', 'headerName' => 'Customer Mobile'],
                    ['field' => 'model_code', 'headerName' => 'Model'],
                    ['field' => 'variant_code', 'headerName' => 'Variant'],
                    ['field' => 'action', 'headerName' => 'Action']
                ],
                'data' => $gridData
            ]
        ]);
    }

    public function virtualNumberList()
    {
        $this->crud->setListView('admin.enquiry.virtual-number-enquiry');

        $enquiries = Enquiry::virtual()
            ->formIncomplete()
            ->with(['model', 'variant'])
            ->orderByDesc('created_at')
            ->get();

        $gridData = $enquiries->map(function ($enquiry, $index) {

            $editUrl = backpack_url("enquiry/{$enquiry->id}/edit");

            return [
                'serial_no' => $index + 1,
                'virtual_no' => $enquiry->virtual_no ?? '—',
                'call_date_and_time' => $enquiry->virtual_call_date
                    ? Carbon::parse($enquiry->virtual_call_date)->format('d-m-Y H:i')
                    : '—',
                'call_duration' => $enquiry->call_duration ?? '—',
                'call_status' => $enquiry->call_status ?? '—',
                'customer_phone' => $enquiry->mobile ?? '—',
                'action' => '
                    <div class="d-flex justify-content-center gap-2">
                        <a href="' . $editUrl . '" class="btn btn-sm btn-primary">Edit</a>
                    </div>',
            ];
        })->values();

        return view('admin.enquiry.virtual-number-enquiry', [
            'title' => 'Virtual Number Enquiries',
            'gridConfig' => [
                'columns' => [
                    ['field' => 'serial_no', 'headerName' => 'S.No'],
                    ['field' => 'virtual_no', 'headerName' => 'Virtual No'],
                    ['field' => 'call_date_and_time', 'headerName' => 'Call Date & Time'],
                    ['field' => 'call_duration', 'headerName' => 'Call Duration'],
                    ['field' => 'call_status', 'headerName' => 'Status'],
                    ['field' => 'customer_phone', 'headerName' => 'Customer Mobile'],
                    ['field' => 'action', 'headerName' => 'Action']
                ],
                'data' => $gridData
            ]
        ]);
    }
    public function whatsappCampaignList()
    {
        $this->crud->setListView('admin.enquiry.whatsapp-campaign-enquiry');

        $enquiries = Enquiry::whatsapp()
            ->formIncomplete()
            ->with(['model', 'variant'])
            ->orderByDesc('created_at')
            ->get();

        $gridData = $enquiries->map(function ($enquiry, $index) {

            $editUrl = backpack_url("enquiry/{$enquiry->id}/edit");

            return [
                'serial_no' => $index + 1,
                'enq_date_and_time' => $enquiry->enquiry_date
                    ? Carbon::parse($enquiry->enquiry_date)->format('d-m-Y H:i')
                    : '—',
                'campaign_name' => $enquiry->wapp_campaign_name ?? '—',
                'campaign_date' => $enquiry->wapp_campaign_date
                    ? Carbon::parse($enquiry->wapp_campaign_date)->format('d-m-Y')
                    : '—',
                'campaign_segment' => $enquiry->wapp_campaign_segment ?? '—',
                'campaign_model' => $enquiry->wapp_campaign_model ?? '—',
                'customer_name' => $enquiry->full_name,
                'customer_phone' => $enquiry->mobile ?? '—',
                'tehsil' => $enquiry->tehsil ?? '—',
                'model_code' => $enquiry->model?->name ?? $enquiry->model_code ?? '—',
                'variant_code' => $enquiry->variant?->display_name ?? $enquiry->variant_code ?? '—',
                'action' => '
                    <div class="d-flex justify-content-center gap-2">
                        <a href="' . $editUrl . '" class="btn btn-sm btn-primary">Edit</a>
                    </div>',
            ];
        })->values();

        return view('admin.enquiry.whatsapp-campaign-enquiry', [
            'title' => 'WhatsApp Campaign Enquiries',
            'gridConfig' => [
                'columns' => [
                    ['field' => 'serial_no', 'headerName' => 'S.No'],
                    ['field' => 'enq_date_and_time', 'headerName' => 'Enquiry Date & Time'],
                    ['field' => 'campaign_name', 'headerName' => 'Campaign Name'],
                    ['field' => 'campaign_date', 'headerName' => 'Campaign Date'],
                    ['field' => 'campaign_segment', 'headerName' => 'Campaign Segment'],
                    ['field' => 'campaign_model', 'headerName' => 'Campaign Model'],
                    ['field' => 'customer_name', 'headerName' => 'Customer Name'],
                    ['field' => 'customer_phone', 'headerName' => 'Customer Mobile'],
                    ['field' => 'tehsil', 'headerName' => 'Tehsil'],
                    ['field' => 'model_code', 'headerName' => 'Model'],
                    ['field' => 'variant_code', 'headerName' => 'Variant'],
                    ['field' => 'action', 'headerName' => 'Action']
                ],
                'data' => $gridData
            ]
        ]);
    }

    public function assignedLongList()
    {
        $this->crud->setListView('admin.enquiry.assigned-long-enquiry');

        // ->assignedLong() = long() [currentOrigin('LONG') + active()] + assigned()
        $enquiries = Enquiry::assignedLong()
            ->formIncomplete()
            ->with(['model', 'variant'])
            ->orderByDesc('created_at')
            ->get();

        $gridData = $enquiries->map(function ($enquiry, $index) {

            $editUrl = backpack_url("enquiry/{$enquiry->id}/edit");

            return [
                'serial_no' => $index + 1,
                'long_enq_no' => $enquiry->enquiry_no ?? '—',
                'long_enq_date_and_time' => $enquiry->enquiry_date
                    ? Carbon::parse($enquiry->enquiry_date)->format('d-m-Y H:i')
                    : '—',
                'long_assign_date_and_time' => $enquiry->enq_assign_date
                    ? Carbon::parse($enquiry->enq_assign_date)->format('d-m-Y H:i')
                    : '—',
                'customer_first_name' => $enquiry->full_name,
                'customer_phone' => $enquiry->mobile ?? '—',
                'enq_type' => $enquiry->enquiry_type ?? '—',
                'enq_source' => $enquiry->source?->name ?? $enquiry->source_code ?? '—',
                'enq_sub_source' => $enquiry->sub_source ?? '—',
                'likely_purchase_date' => $enquiry->likely_purchase_date ?? '—',
                'model_code' => $enquiry->model?->name ?? $enquiry->model_code ?? '—',
                'variant_code' => $enquiry->variant?->display_name ?? $enquiry->variant_code ?? '—',
                'color_code' => $enquiry->color?->name ?? $enquiry->color_code ?? '—',
                'sc_name' => $enquiry->salesConsultant?->name ?? '—',
                'sc_mile_id' => $enquiry->sc_mile_id ?? '—',
                'customer_type' => $enquiry->customer_type ?? '—',
                'zip_code' => $enquiry->zipcode ?? '—',
                'action' => '
                    <div class="d-flex justify-content-center gap-2">
                        <a href="' . $editUrl . '" class="btn btn-sm btn-primary">Edit</a>
                    </div>',
            ];
        })->values();

        return view('admin.enquiry.assigned-long-enquiry', [
            'title' => 'Assigned Long Enquiries',
            'gridConfig' => [
                'columns' => [
                    ['field' => 'serial_no', 'headerName' => 'S.No'],
                    ['field' => 'long_enq_no', 'headerName' => 'Long Enquiry No'],
                    ['field' => 'long_enq_date_and_time', 'headerName' => 'Long Enquiry Date & Time'],
                    ['field' => 'long_assign_date_and_time', 'headerName' => 'Long Assigned Date & Time'],
                    ['field' => 'customer_first_name', 'headerName' => 'Customer Name'],
                    ['field' => 'customer_phone', 'headerName' => 'Customer Mobile'],
                    ['field' => 'enq_type', 'headerName' => 'Enquiry Type'],
                    ['field' => 'enq_source', 'headerName' => 'Enquiry Source'],
                    ['field' => 'enq_sub_source', 'headerName' => 'Enquiry Sub Source'],
                    ['field' => 'likely_purchase_date', 'headerName' => 'Likely Purchase Date'],
                    ['field' => 'model_code', 'headerName' => 'Model'],
                    ['field' => 'variant_code', 'headerName' => 'Variant'],
                    ['field' => 'color_code', 'headerName' => 'Color'],
                    ['field' => 'sc_name', 'headerName' => 'SC Name'],
                    ['field' => 'sc_mile_id', 'headerName' => 'SC Mile Id'],
                    ['field' => 'customer_type', 'headerName' => 'Customer Type'],
                    ['field' => 'zip_code', 'headerName' => 'Zip Code'],
                    ['field' => 'action', 'headerName' => 'Action']
                ],
                'data' => $gridData
            ]
        ]);
    }

    public function unassignedLongList()
    {
        $this->crud->setListView('admin.enquiry.unassigned-long-enquiry');

        $enquiries = Enquiry::unassignedLong()
            ->formIncomplete()
            ->with(['model', 'variant'])
            ->orderByDesc('created_at')
            ->get();
        $gridData = $enquiries->map(function ($enquiry, $index) {

            $editUrl = backpack_url("enquiry/{$enquiry->id}/edit");

            return [
                'serial_no' => $index + 1,
                'long_enq_no' => $enquiry->enquiry_no ?? '—',
                'long_enq_date_and_time' => $enquiry->enquiry_date
                    ? Carbon::parse($enquiry->enquiry_date)->format('d-m-Y H:i')
                    : '—',
                'long-assign_date_and_time' => $enquiry->enq_assign_date
                    ? Carbon::parse($enquiry->enq_assign_date)->format('d-m-Y H:i')
                    : '—',
                'customer_first_name' => $enquiry->full_name,
                'customer_phone' => $enquiry->mobile ?? '—',
                'enq_type' => $enquiry->enquiry_type ?? '—',
                'enq_source' => $enquiry->source?->name ?? $enquiry->source_code ?? '—',
                'enq_sub_source' => $enquiry->sub_source ?? '—',
                'likely_purchase_date' => $enquiry->likely_purchase_date ?? '—',
                'model_code' => $enquiry->model?->name ?? $enquiry->model_code ?? '—',
                'variant_code' => $enquiry->variant?->display_name ?? $enquiry->variant_code ?? '—',
                'color_code' => $enquiry->color?->name ?? $enquiry->color_code ?? '—',
                'customer_type' => $enquiry->customer_type ?? '—',
                'zip_code' => $enquiry->zipcode ?? '—',
                'action' => '
                    <div class="d-flex justify-content-center gap-2">
                        <a href="' . $editUrl . '" class="btn btn-sm btn-primary">Edit</a>
                    </div>',
            ];
        })->values();

        return view('admin.enquiry.unassigned-long-enquiry', [
            'title' => 'Unassigned Long Enquiries',
            'gridConfig' => [
                'columns' => [
                    ['field' => 'serial_no', 'headerName' => 'S.No'],
                    ['field' => 'long_enq_no', 'headerName' => 'Long Enquiry No'],
                    ['field' => 'long_enq_date_and_time', 'headerName' => 'Long Enquiry Date & Time'],
                    ['field' => 'customer_first_name', 'headerName' => 'Customer Name'],
                    ['field' => 'customer_phone', 'headerName' => 'Customer Mobile'],
                    ['field' => 'enq_type', 'headerName' => 'Enquiry Type'],
                    ['field' => 'enq_source', 'headerName' => 'Enquiry Source'],
                    ['field' => 'enq_sub_source', 'headerName' => 'Enquiry Sub Source'],
                    ['field' => 'likely_purchase_date', 'headerName' => 'Likely Purchase Date'],
                    ['field' => 'model_code', 'headerName' => 'Model'],
                    ['field' => 'variant_code', 'headerName' => 'Variant'],
                    ['field' => 'color_code', 'headerName' => 'Color'],
                    ['field' => 'customer_type', 'headerName' => 'Customer Type'],
                    ['field' => 'zip_code', 'headerName' => 'Zip Code'],
                    ['field' => 'action', 'headerName' => 'Action']
                ],
                'data' => $gridData
            ]
        ]);
    }

    public function assignedQuickList()
    {
        $this->crud->setListView('admin.enquiry.assigned-quick-enquiry');

        $enquiries = Enquiry::assignedQuick()
            ->formIncomplete()
            ->with(['model', 'variant'])
            ->orderByDesc('created_at')
            ->get();

        $gridData = $enquiries->map(function ($enquiry, $index) {

            $editUrl = backpack_url("enquiry/{$enquiry->id}/edit");

            return [
                'serial_no' => $index + 1,
                'quick_enq_no' => $enquiry->enquiry_no ?? '—',
                'quick_enq_date_and_time' => $enquiry->enquiry_date
                    ? Carbon::parse($enquiry->enquiry_date)->format('d-m-Y H:i')
                    : '—',
                'quick-assign_date_and_time' => $enquiry->enq_assign_date
                    ? Carbon::parse($enquiry->enq_assign_date)->format('d-m-Y H:i')
                    : '—',
                'customer_first_name' => $enquiry->full_name,
                'customer_phone' => $enquiry->mobile ?? '—',
                'enq_type' => $enquiry->enquiry_type ?? '—',
                'enq_source' => $enquiry->source?->name ?? $enquiry->source_code ?? '—',
                'enq_sub_source' => $enquiry->sub_source ?? '—',
                'likely_purchase_date' => $enquiry->likely_purchase_date ?? '—',
                'model_code' => $enquiry->model?->name ?? $enquiry->model_code ?? '—',
                'variant_code' => $enquiry->variant?->display_name ?? $enquiry->variant_code ?? '—',
                'color_code' => $enquiry->color?->name ?? $enquiry->color_code ?? '—',
                'sc_name' => $enquiry->salesConsultant?->name ?? '—',
                'sc_mile_id' => $enquiry->sc_mile_id ?? '—',
                'enq_stage' => $enquiry->stage ?? '—',
                'action' => '
                    <div class="d-flex justify-content-center gap-2">
                        <a href="' . $editUrl . '" class="btn btn-sm btn-primary">Edit</a>
                    </div>',
            ];
        })->values();

        return view('admin.enquiry.assigned-quick-enquiry', [
            'title' => 'Assigned Quick Enquiries',
            'gridConfig' => [
                'columns' => [
                    ['field' => 'serial_no', 'headerName' => 'S.No'],
                    ['field' => 'quick_enq_no', 'headerName' => 'Quick Enquiry No'],
                    ['field' => 'quick_enq_date_and_time', 'headerName' => 'Quick Enquiry Date & Time'],
                    ['field' => 'quick_assign_date_and_time', 'headerName' => 'Quick Assigned Date & Time'],
                    ['field' => 'customer_first_name', 'headerName' => 'Customer Name'],
                    ['field' => 'customer_phone', 'headerName' => 'Customer Mobile'],
                    ['field' => 'enq_type', 'headerName' => 'Enquiry Type'],
                    ['field' => 'enq_source', 'headerName' => 'Enquiry Source'],
                    ['field' => 'enq_sub_source', 'headerName' => 'Enquiry Sub Source'],
                    ['field' => 'likely_purchase_date', 'headerName' => 'Likely Purchase Date'],
                    ['field' => 'model_code', 'headerName' => 'Model'],
                    ['field' => 'variant_code', 'headerName' => 'Variant'],
                    ['field' => 'color_code', 'headerName' => 'Color'],
                    ['field' => 'sc_name', 'headerName' => 'SC Name'],
                    ['field' => 'sc_mile_id', 'headerName' => 'SC Mile Id'],
                    ['field' => 'enq_stage', 'headerName' => 'Enquiry Stage'],
                    ['field' => 'action', 'headerName' => 'Action']
                ],
                'data' => $gridData
            ]
        ]);
    }

    public function unassignedQuickList()
    {
        $this->crud->setListView('admin.enquiry.unassigned-quick-enquiry');

        $enquiries = Enquiry::unassignedQuick()
            ->formIncomplete()
            ->with(['model', 'variant'])
            ->orderByDesc('created_at')
            ->get();

        $gridData = $enquiries->map(function ($enquiry, $index) {

            $editUrl = backpack_url("enquiry/{$enquiry->id}/edit");

            return [
                'serial_no' => $index + 1,
                'quick_enq_no' => $enquiry->enquiry_no ?? '—',
                'quick_enq_date_and_time' => $enquiry->enquiry_date
                    ? Carbon::parse($enquiry->enquiry_date)->format('d-m-Y H:i')
                    : '—',
                'customer_first_name' => $enquiry->full_name,
                'customer_phone' => $enquiry->mobile ?? '—',
                'enq_type' => $enquiry->enquiry_type ?? '—',
                'enq_source' => $enquiry->source?->name ?? $enquiry->source_code ?? '—',
                'enq_sub_source' => $enquiry->sub_source ?? '—',
                'likely_purchase_date' => $enquiry->likely_purchase_date ?? '—',
                'model_code' => $enquiry->model?->name ?? $enquiry->model_code ?? '—',
                'variant_code' => $enquiry->variant?->display_name ?? $enquiry->variant_code ?? '—',
                'color_code' => $enquiry->color?->name ?? $enquiry->color_code ?? '—',
                'enq_stage' => $enquiry->stage ?? '—',
                'action' => '
                    <div class="d-flex justify-content-center gap-2">
                        <a href="' . $editUrl . '" class="btn btn-sm btn-primary">Edit</a>
                    </div>',
            ];
        })->values();

        return view('admin.enquiry.unassigned-quick-enquiry', [
            'title' => 'Unassigned Quick Enquiries',
            'gridConfig' => [
                'columns' => [
                    ['field' => 'serial_no', 'headerName' => 'S.No'],
                    ['field' => 'quick_enq_no', 'headerName' => 'Quick Enquiry No'],
                    ['field' => 'quick_enq_date_and_time', 'headerName' => 'Quick Enquiry Date & Time'],
                    ['field' => 'customer_first_name', 'headerName' => 'Customer Name'],
                    ['field' => 'customer_phone', 'headerName' => 'Customer Mobile'],
                    ['field' => 'enq_type', 'headerName' => 'Enquiry Type'],
                    ['field' => 'enq_source', 'headerName' => 'Enquiry Source'],
                    ['field' => 'enq_sub_source', 'headerName' => 'Enquiry Sub Source'],
                    ['field' => 'likely_purchase_date', 'headerName' => 'Likely Purchase Date'],
                    ['field' => 'model_code', 'headerName' => 'Model'],
                    ['field' => 'variant_code', 'headerName' => 'Variant'],
                    ['field' => 'color_code', 'headerName' => 'Color'],
                    ['field' => 'enq_stage', 'headerName' => 'Enquiry Stage'],
                    ['field' => 'action', 'headerName' => 'Action']
                ],
                'data' => $gridData
            ]
        ]);
    }

    public function checkDuplicateEnquiry(Request $request)
    {
        $enquiry = Enquiry::where('mobile', $request->mobile)
            ->where('segment_code', $request->segment_code)
            ->first();

        return response()->json([
            'exists' => $enquiry ? true : false,
            'enquiry_no' => $enquiry?->enquiry_no
        ]);
    }

    public function locationByPincode(Request $request)
    {
        return response()->json(
            OrgService::getLocationByPincode($request->pincode)
        );
    }
    public function importEnquiries(Request $request)
    {

        if (!$request->hasFile('excel_file')) {

            Alert::error('No file uploaded!')->flash();

            return redirect()->back();
        }



        $file = $request->file('excel_file');

        if (!in_array($file->getClientOriginalExtension(), ['xlsx', 'xls'])) {

            Alert::error('Only Excel files (.xlsx, .xls) allowed')->flash();

            return redirect()->back();
        }



        // Move the upload out of the request's tmp path into permanent storage —

        // the queue worker runs in a totally separate process/request lifecycle

        // and the original tmp upload file will be gone by the time it picks

        // this job up.

        $storedPath = $file->store('imports', 'local'); // storage/app/imports/xxxx.xlsx

        $absolutePath = Storage::disk('local')->path($storedPath);



        $importLogId = DB::table('xlr8_crm_import_logs')->insertGetId([

            'file_name' => $file->getClientOriginalName(),

            'stored_path' => $absolutePath,

            'status' => 'queued',

            'created_by' => backpack_user()->id ?? null,

            'created_at' => now(),

            'updated_at' => now(),

        ]);

        ImportEnquiriesJob::dispatch($importLogId, $absolutePath);

        Alert::success(

            "File uploaded and queued for processing (Import #{$importLogId}). " .

            "Large files can take a few minutes — the status panel below will update automatically."

        )->flash();



        return redirect()->back();
    }



    /**

     * Polled by the blade page every few seconds to show live progress.

     * Route: GET enquiry/import/status/{id}

     */

    public function importStatus($id)
    {

        $log = DB::table('xlr8_crm_import_logs')->where('id', $id)->first();



        if (!$log) {

            return response()->json(['error' => 'Not found'], 404);
        }



        return response()->json([

            'id' => $log->id,

            'status' => $log->status,

            'total_rows' => $log->total_rows,

            'processed_rows' => $log->processed_rows,

            'percent' => $log->total_rows > 0

                ? round(($log->processed_rows / $log->total_rows) * 100, 1)

                : 0,

            'stats' => $log->stats ? json_decode($log->stats, true) : null,

            'error_message' => $log->error_message,

        ]);
    }

    public function importHistory()
    {

        $logs = DB::table('xlr8_crm_import_logs')

            ->orderByDesc('id')

            ->limit(5)

            ->get();



        return response()->json($logs);
    }
}