<?php

namespace App\Http\Controllers\Admin;

use App\Models\CRM\Enquiry;
use App\Models\CRM\Lead;
use App\Models\CRM\LeadSource;
use App\Services\OrgService;
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

        $enquiries = Enquiry::with([
            // 'source',
            'segment',
            'model',
            'variant',
            'color',
            'campaign',
        ])
            ->orderByDesc('created_at')
            ->get();

        $gridData = $enquiries->map(function ($enquiry, $index) {

            $mapped = $enquiry->toArray();

            $mapped['serial_no'] = $index + 1;

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

        })->values();

        return view('admin.enquiry.list', [

            'title' => 'All Enquiries',

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
                    ['field' => 'sales_consultant_id', 'headerName' => 'Sales Consultant'],
                    ['field' => 'followup_type', 'headerName' => 'Followup Type'],
                    ['field' => 'followup_date', 'headerName' => 'Followup Date'],
                    ['field' => 'followup_time', 'headerName' => 'Followup Time'],
                    ['field' => 'action', 'headerName' => 'Action']
                ],
                'data' => $gridData
            ]
        ]);
    }


    public function create()
    {
        $this->crud->setCreateView('admin.enquiry.create');

        $data = [];

        /*
        |--------------------------------------------------------------------------
        | Masters
        |--------------------------------------------------------------------------
        */

        $data['title'] = 'Add New Enquiry';

        $data['segments'] = OrgService::segments();
        $data['models'] = [];
        $data['variants'] = [];
        $data['colors'] = [];

        $data['saleconsultants'] = OrgService::getUsers(desigCode: 'CNS');

        /*
        |--------------------------------------------------------------------------
        | Keyword Masters
        |--------------------------------------------------------------------------
        */

        $data['enquiry_types'] = OrgService::keywordValueByCode('ENQUIRY_TYPE');
        $data['activity_types'] = OrgService::keywordValueByCode('ACTIVITY_TYPE');
        $data['likely_purchase_dates'] = OrgService::keywordValueByCode('LIKELY_PURCHASE_DATE');
        $data['follow_up_types'] = OrgService::keywordValueByCode('FOLLOW_UP_TYPE');

        $data['occupation_types'] = OrgService::keywordValueByCode('OCCUPATION_TYPE');
        $data['occupation_sub_types'] = OrgService::keywordValueByCode('OCCUPATION_SUB_TYPE');

        // Existing Keywords
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

        // dd($data);
        return view('admin.enquiry.create', $data);
    }

    // public function store(Request $request)
    // {
    //     dd($request->all());
    // }

    public function store(Request $request)
    {
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
            'sales_consultant_id' => 'required',
            'followup_type' => 'nullable',
            'followup_date' => 'nullable|date',
            'followup_time' => 'nullable'
        ]);

        $validated['created_by'] = backpack_user()->id;

        Enquiry::create($validated);

        \Alert::success('Enquiry created successfully.')->flash();

        return redirect(backpack_url('enquiry'));
    }

    public function edit($id)
    {
        $enquiry = Enquiry::findOrFail($id);

        return view('admin.enquiry.create', [
            'title' => 'Edit Enquiry',
            'enquiry' => $enquiry,
            'sources' => OrgService::leadSources(),
            'subSources' => OrgService::leadSubSources($enquiry->source_code),
            'campaigns' => Campaign::orderBy('name')->pluck('name', 'name'),
            'segments' => OrgService::segments(),
            'models' => OrgService::models($enquiry->segment_code),
            'variants' => OrgService::variants($enquiry->model_code),
            'colors' => OrgService::colors($enquiry->variant_code),
            'branches' => OrgService::branches(),
            'locations' => OrgService::locations($enquiry->dealer_branch),
            'activity_types' => OrgService::keywordValueByCode('ACTIVITY_TYPE'),
            'activity_models' => OrgService::models($enquiry->activity_segment),
            'activity_locations' => OrgService::locations($enquiry->activity_branch),
            'saleconsultants' => OrgService::getUsers(desigCode: 'CNS'),
        ]);
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
            'likely_purchase_date' => 'nullable|date',
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
            'sales_consultant_id' => 'required',
            'followup_type' => 'nullable',
            'followup_date' => 'nullable|date',
            'followup_time' => 'nullable'
        ]);

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

       $enquiries = Enquiry::where('current_origin', 'REFERENCE')
            ->where('real_status', 1)
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
                    ['field' => 'serial_no',     'headerName' => 'S.No'],
                    ['field' => 'enq_date_and_time',    'headerName' => 'Enquiry Date & Time'],
                    ['field' => 'referred_by',  'headerName' => 'Referred By'],
                    ['field' => 'referee_phone',     'headerName' => 'Referee Mobile'],
                    ['field' => 'customer_name',    'headerName' => 'Customer Name'],
                    ['field' => 'customer_phone',        'headerName' => 'Customer Mobile'],
                    ['field' => 'model_code',    'headerName' => 'Model'],
                    ['field' => 'variant_code',  'headerName' => 'Variant'],
                    ['field' => 'action',        'headerName' => 'Action']
                ],
                'data' => $gridData
            ]
        ]);
    }

    public function virtualNumberList()
    {
        $this->crud->setListView('admin.enquiry.virtual-number-enquiry');

        $enquiries = Enquiry::where('current_origin', 'VIRTUAL')
            ->where('real_status', 1)
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
                    ['field' => 'serial_no',     'headerName' => 'S.No'],
                    ['field' => 'virtual_no',    'headerName' => 'Virtual No'],
                    ['field' => 'call_date_and_time',  'headerName' => 'Call Date & Time'],
                    ['field' => 'call_duration',     'headerName' => 'Call Duration'],
                    ['field' => 'call_status',     'headerName' => 'Status'],
                    ['field' => 'customer_phone',        'headerName' => 'Customer Mobile'],
                    ['field' => 'action',        'headerName' => 'Action']
                ],
                'data' => $gridData
            ]
        ]);
    }
    public function whatsappCampaignList()
    {
        $this->crud->setListView('admin.enquiry.whatsapp-campaign-enquiry');

        $enquiries = Enquiry::where('current_origin', 'WHATSAPP')
            ->where('real_status', 1)
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
                    ['field' => 'serial_no',     'headerName' => 'S.No'],
                    ['field' => 'enq_date_and_time',     'headerName' => 'Enquiry Date & Time'],
                    ['field' => 'campaign_name',    'headerName' => 'Campaign Name'],
                    ['field' => 'campaign_date',  'headerName' => 'Campaign Date'],
                    ['field' => 'campaign_segment',     'headerName' => 'Campaign Segment'],
                    ['field' => 'campaign_model',        'headerName' => 'Campaign Model'],
                    ['field' => 'customer_name',        'headerName' => 'Customer Name'],
                    ['field' => 'customer_phone',        'headerName' => 'Customer Mobile'],
                    ['field' => 'tehsil',        'headerName' => 'Tehsil'],
                    ['field' => 'model_code',        'headerName' => 'Model'],
                    ['field' => 'variant_code',        'headerName' => 'Variant'],
                    ['field' => 'action',        'headerName' => 'Action']
                ],
                'data' => $gridData
            ]
        ]);
    }

    public function assignedLongList()
    {
        $this->crud->setListView('admin.enquiry.assigned-long-enquiry');

       $enquiries = Enquiry::where('current_origin', 'LONG')
            ->where('real_status', 1)
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
                    ['field' => 'serial_no',     'headerName' => 'S.No'],
                    ['field' => 'long_enq_no',    'headerName' => 'Long Enquiry No'],
                    ['field' => 'long_enq_date_and_time',  'headerName' => 'Long Enquiry Date & Time'],
                    ['field' => 'long_assign_date_and_time',  'headerName' => 'Long Assigned Date & Time'],
                    ['field' => 'customer_first_name',     'headerName' => 'Customer Name'],
                    ['field' => 'customer_phone',        'headerName' => 'Customer Mobile'],
                    ['field' => 'enq_type',        'headerName' => 'Enquiry Type'],
                    ['field' => 'enq_source',        'headerName' => 'Enquiry Source'],
                    ['field' => 'enq_sub_source',        'headerName' => 'Enquiry Sub Source'],
                    ['field' => 'likely_purchase_date',        'headerName' => 'Likely Purchase Date'],
                    ['field' => 'model_code',    'headerName' => 'Model'],
                    ['field' => 'variant_code',  'headerName' => 'Variant'],
                    ['field' => 'color_code',    'headerName' => 'Color'],
                    ['field' => 'sc_name',    'headerName' => 'SC Name'],
                    ['field' => 'sc_mile_id',    'headerName' => 'SC Mile Id'],
                    ['field' => 'customer_type',        'headerName' => 'Customer Type'],
                    ['field' => 'zip_code',        'headerName' => 'Zip Code'],
                    ['field' => 'action',        'headerName' => 'Action']
                ],
                'data' => $gridData
            ]
        ]);
    }

    public function unassignedLongList()
    {
        $this->crud->setListView('admin.enquiry.unassigned-long-enquiry');

        $enquiries = Enquiry::where('current_origin', 'LONG')
            ->where('real_status', 1)
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
                    ['field' => 'serial_no',     'headerName' => 'S.No'],
                    ['field' => 'long_enq_no',    'headerName' => 'Long Enquiry No'],
                    ['field' => 'long_enq_date_and_time',  'headerName' => 'Long Enquiry Date & Time'],
                    ['field' => 'customer_first_name',     'headerName' => 'Customer Name'],
                    ['field' => 'customer_phone',        'headerName' => 'Customer Mobile'],
                    ['field' => 'enq_type',        'headerName' => 'Enquiry Type'],
                    ['field' => 'enq_source',        'headerName' => 'Enquiry Source'],
                    ['field' => 'enq_sub_source',        'headerName' => 'Enquiry Sub Source'],
                    ['field' => 'likely_purchase_date',        'headerName' => 'Likely Purchase Date'],
                    ['field' => 'model_code',    'headerName' => 'Model'],
                    ['field' => 'variant_code',  'headerName' => 'Variant'],
                    ['field' => 'color_code',    'headerName' => 'Color'],
                    ['field' => 'customer_type',        'headerName' => 'Customer Type'],
                    ['field' => 'zip_code',        'headerName' => 'Zip Code'],
                    ['field' => 'action',        'headerName' => 'Action']
                ],
                'data' => $gridData
            ]
        ]);
    }

    public function assignedQuickList()
    {
        $this->crud->setListView('admin.enquiry.assigned-quick-enquiry');

        $enquiries = Enquiry::where('current_origin', 'QUICK')
            ->where('real_status', 1)
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
                    ['field' => 'serial_no',     'headerName' => 'S.No'],
                    ['field' => 'quick_enq_no',    'headerName' => 'Quick Enquiry No'],
                    ['field' => 'quick_enq_date_and_time',  'headerName' => 'Quick Enquiry Date & Time'],
                    ['field' => 'quick_assign_date_and_time',  'headerName' => 'Quick Assigned Date & Time'],
                    ['field' => 'customer_first_name',     'headerName' => 'Customer Name'],
                    ['field' => 'customer_phone',        'headerName' => 'Customer Mobile'],
                    ['field' => 'enq_type',        'headerName' => 'Enquiry Type'],
                    ['field' => 'enq_source',        'headerName' => 'Enquiry Source'],
                    ['field' => 'enq_sub_source',        'headerName' => 'Enquiry Sub Source'],
                    ['field' => 'likely_purchase_date',        'headerName' => 'Likely Purchase Date'],
                    ['field' => 'model_code',    'headerName' => 'Model'],
                    ['field' => 'variant_code',  'headerName' => 'Variant'],
                    ['field' => 'color_code',    'headerName' => 'Color'],
                    ['field' => 'sc_name',    'headerName' => 'SC Name'],
                    ['field' => 'sc_mile_id',    'headerName' => 'SC Mile Id'],
                    ['field' => 'enq_stage',        'headerName' => 'Enquiry Stage'],
                    ['field' => 'action',        'headerName' => 'Action']
                ],
                'data' => $gridData
            ]
        ]);
    }

    public function unassignedQuickList()
    {
        $this->crud->setListView('admin.enquiry.unassigned-quick-enquiry');

        $enquiries = Enquiry::where('current_origin', 'QUICK')
            ->where('real_status', 1)
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
                    ['field' => 'serial_no',     'headerName' => 'S.No'],
                    ['field' => 'quick_enq_no',    'headerName' => 'Quick Enquiry No'],
                    ['field' => 'quick_enq_date_and_time',  'headerName' => 'Quick Enquiry Date & Time'],
                    ['field' => 'customer_first_name',     'headerName' => 'Customer Name'],
                    ['field' => 'customer_phone',        'headerName' => 'Customer Mobile'],
                    ['field' => 'enq_type',        'headerName' => 'Enquiry Type'],
                    ['field' => 'enq_source',        'headerName' => 'Enquiry Source'],
                    ['field' => 'enq_sub_source',        'headerName' => 'Enquiry Sub Source'],
                    ['field' => 'likely_purchase_date',        'headerName' => 'Likely Purchase Date'],
                    ['field' => 'model_code',    'headerName' => 'Model'],
                    ['field' => 'variant_code',  'headerName' => 'Variant'],
                    ['field' => 'color_code',    'headerName' => 'Color'],
                    ['field' => 'enq_stage',        'headerName' => 'Enquiry Stage'],
                    ['field' => 'action',        'headerName' => 'Action']
                ],
                'data' => $gridData
            ]
        ]);
    }


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
}
