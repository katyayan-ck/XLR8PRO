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
        CRUD::setRoute(config('backpack.base.route_prefix') . '/enquiry');
        CRUD::setEntityNameStrings('enquiry', 'enquiries');
    }

    protected function setupListOperation()
    {
        $this->crud->setListView('admin.enquiry.list');
    }

    public function index()
    {
        $this->crud->setListView('admin.enquiry.list');

        // $enquiries = Enquiry::with('source')
        $enquiries = Enquiry::with([
            'source',
            'segment',
            'model',
            'variant',
            'color',
        ])
            ->select([
                'id',
                'enquiry_no',
                'enquiry_date',
                'source_code',
                'lead_no',
                'person_code',
                'referral_details',
                'first_name',
                'last_name',
                'mobile',
                'email',
                'occupation',
                'segment_code',
                'model_code',
                'variant_code',
                'color_code',
                'place_of_registration',
                'registration_by',
                'insurance_by',
                'has_rsa',
                'has_extended_warranty',
                'expected_delivery_date',
                'dms_enquiry_no',
                'sales_consultant_id',
                'status',
                'lost_reason',
                'priority',
                'notes',
                'conversion_notes',
            ])
            ->orderBy('enquiry_date', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $gridData = $enquiries->map(function ($enquiry, $index) {

            $mapped = $enquiry->toArray();

            $mapped['serial_no'] = $index + 1;

            $mapped['enquiry_date'] = $enquiry->enquiry_date
                ? \Carbon\Carbon::parse($enquiry->enquiry_date)->format('d-m-Y')
                : '-';

            $mapped['expected_delivery_date'] = $enquiry->expected_delivery_date
                ? \Carbon\Carbon::parse($enquiry->expected_delivery_date)->format('d-m-Y')
                : '-';

            $mapped['first_name'] = $enquiry->first_name;

            $mapped['last_name'] = $enquiry->last_name;

            $mapped['full_name'] = $enquiry->full_name;

            $mapped['source_name'] = $enquiry->source?->name ?? '—';

            $mapped['segment_name'] = $enquiry->segment?->name ?? '—';

            $mapped['model_name'] = $enquiry->model?->name ?? '—';

            $mapped['variant_name'] =
                $enquiry->variant?->display_name
                ?? $enquiry->variant?->custom_name
                ?? $enquiry->variant?->oem_name
                ?? '—';

            $mapped['color_name'] =
                $enquiry->color?->name ?? '—';

            $mapped['status'] = $enquiry->status_label ?? ucfirst(str_replace('_', ' ', $enquiry->status));

            $mapped['has_rsa'] = $enquiry->has_rsa ? 'Yes' : 'No';

            $mapped['has_extended_warranty'] = $enquiry->has_extended_warranty ? 'Yes' : 'No';

            $editUrl = backpack_url("enquiry/{$enquiry->id}/edit");

            $quotationUrl = backpack_url(
                'quotation-form/create?enquiry_id=' . $enquiry->id
            );

            $mapped['action'] = '
            <div class="d-flex justify-content-center gap-2">
                <a href="' . $editUrl . '" class="btn btn-sm btn-primary">
                    Edit
                </a>
                <a href="' . $quotationUrl . '" class="btn btn-success btn-sm">
                    Form
                </a>
            </div>
        ';

            return $mapped;
        })->values();

        return view('admin.enquiry.list', [

            'title' => 'All Enquiries',

            'gridConfig' => [

                'columns' => [

                    ['field' => 'serial_no', 'headerName' => 'S.No'],

                    ['field' => 'enquiry_no', 'headerName' => 'Enquiry No'],

                    ['field' => 'enquiry_date', 'headerName' => 'Enquiry Date'],

                    ['field' => 'lead_no', 'headerName' => 'Lead No'],

                    ['field' => 'person_code', 'headerName' => 'Person Code'],

                    ['field' => 'source_code', 'headerName' => 'Lead Source'],

                    ['field' => 'referral_details', 'headerName' => 'Referral Details'],

                    ['field' => 'first_name', 'headerName' => 'First Name'],

                    ['field' => 'last_name', 'headerName' => 'Last Name'],

                    ['field' => 'full_name', 'headerName' => 'Full Name'],

                    ['field' => 'mobile', 'headerName' => 'Mobile'],

                    ['field' => 'email', 'headerName' => 'Email'],

                    ['field' => 'occupation', 'headerName' => 'Occupation'],

                    [
                        'field' => 'source_name',
                        'headerName' => 'Lead Source',
                    ],

                    [
                        'field' => 'segment_name',
                        'headerName' => 'Segment',
                    ],

                    [
                        'field' => 'model_name',
                        'headerName' => 'Model',
                    ],

                    [
                        'field' => 'variant_name',
                        'headerName' => 'Variant',
                    ],

                    [
                        'field' => 'color_name',
                        'headerName' => 'Color',
                    ],

                    ['field' => 'place_of_registration', 'headerName' => 'Place Of Registration'],

                    ['field' => 'registration_by', 'headerName' => 'Registration By'],

                    ['field' => 'insurance_by', 'headerName' => 'Insurance By'],

                    ['field' => 'has_rsa', 'headerName' => 'Has RSA'],

                    ['field' => 'has_extended_warranty', 'headerName' => 'Has Extended Warranty'],

                    ['field' => 'expected_delivery_date', 'headerName' => 'Expected Delivery Date'],

                    ['field' => 'dms_enquiry_no', 'headerName' => 'DMS Enquiry No'],

                    ['field' => 'sales_consultant_id', 'headerName' => 'Sales Consultant'],

                    ['field' => 'status', 'headerName' => 'Status'],

                    ['field' => 'lost_reason', 'headerName' => 'Lost Reason'],

                    ['field' => 'priority', 'headerName' => 'Priority'],

                    ['field' => 'notes', 'headerName' => 'Notes'],

                    ['field' => 'conversion_notes', 'headerName' => 'Conversion Notes'],

                    ['field' => 'action', 'headerName' => 'Action'],

                ],

                'data' => $gridData,

            ],

        ]);
    }

    public function create()
    {
        $this->crud->setCreateView('admin.enquiry.create');

        return view('admin.enquiry.create', [
            'title' => 'Add New Enquiry',
            'leads' => Lead::orderBy('lead_no')
                ->pluck(
                    'lead_no',
                    'lead_no'
                ),
            'sources' => LeadSource::where(
                'is_active',
                1
            )
                ->orderBy('name')
                ->pluck(
                    'name',
                    'code'
                ),

            'segments' => OrgService::segments(),

            'models' => [],

            'variants' => [],

            'colors' => [],
            'saleconsultants' => OrgService::getUsers(desigCode: 'CNS'),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'enquiry_no' => 'required|string|max:50|unique:xlr8_crm_enquiries,enquiry_no',
            'enquiry_date' => 'required|date',
            'source_code' => 'required|exists:xlr8_crm_lead_sources,code',
            'lead_no' => 'nullable|string|max:50',
            'person_code' => 'required|string|max:100',
            'referral_details' => 'nullable|string|max:255',
            'first_name' => 'required|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'mobile' => 'required|string|max:15',
            'email' => 'nullable|email|max:150',
            'occupation' => 'nullable|string|max:150',
            'segment_code' => 'required|exists:xlr8_vehicle_segment,code',
            'model_code' => 'required|string|max:50',
            'variant_code' => 'required|string|max:50',
            'color_code' => 'required|string|max:50',
            'place_of_registration' => 'nullable|string|max:100',
            'registration_by' => 'nullable|string|max:20',
            'insurance_by' => 'nullable|string|max:20',
            'has_rsa' => 'boolean',
            'has_extended_warranty' => 'boolean',
            'expected_delivery_date' => 'nullable|date',
            'dms_enquiry_no' => 'nullable|string|max:50',
            'sales_consultant_id' => 'nullable|string|max:100',  // assuming users table
            'status' => 'required|string|in:new,in_followup,quotation_sent,quotation_approved,booking_done,lost,cancelled',
            'lost_reason' => 'nullable|string|max:255',
            'priority' => 'required|string|in:high,medium,low',
            'notes' => 'nullable|string',
            'conversion_notes' => 'nullable|string',
            // Add more validation rules as needed
        ]);

        $enquiry = Enquiry::create($validated);

        \Alert::success('Enquiry created successfully!')->flash();

        return redirect(backpack_url('enquiry'));
    }

    public function edit($id)
    {
        $enquiry = Enquiry::findOrFail($id);

        return view('admin.enquiry.edit', [

            'title' => 'Edit Enquiry',

            'enquiry' => $enquiry,

            'leads' => Lead::orderBy('lead_no')
                ->pluck('lead_no', 'lead_no'),

            'sources' => LeadSource::where('is_active', 1)
                ->orderBy('name')
                ->pluck('name', 'code'),

            'segments' => OrgService::segments(),

            'models' => OrgService::models(
                $enquiry->segment_code
            ),

            'variants' => OrgService::variants(
                $enquiry->model_code
            ),

            'colors' => OrgService::colors(
                $enquiry->variant_code
            ),

            'saleconsultants' => OrgService::getUsers(desigCode: 'CNS'),

        ]);
    }

    public function update(Request $request, $id)
    {
        $enquiry = Enquiry::findOrFail($id);

        $validated = $request->validate([
            'enquiry_no' => 'required|string|max:50|unique:xlr8_crm_enquiries,enquiry_no,' . $id,
            'enquiry_date' => 'required|date',
            'source_code' => 'required|exists:xlr8_crm_lead_sources,code',
            'lead_no' => 'nullable|string|max:50',
            'person_code' => 'required|string|max:100',
            'referral_details' => 'nullable|string|max:255',
            'first_name' => 'required|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'mobile' => 'required|string|max:15',
            'email' => 'nullable|email|max:150',
            'occupation' => 'nullable|string|max:150',
            'segment_code' => 'required|exists:xlr8_vehicle_segment,code',
            'model_code' => 'required|string|max:50',
            'variant_code' => 'required|string|max:50',
            'color_code' => 'required|string|max:50',
            'place_of_registration' => 'nullable|string|max:100',
            'registration_by' => 'nullable|string|max:20',
            'insurance_by' => 'nullable|string|max:20',
            'has_rsa' => 'boolean',
            'has_extended_warranty' => 'boolean',
            'expected_delivery_date' => 'nullable|date',
            'dms_enquiry_no' => 'nullable|string|max:50',
            'sales_consultant_id' => 'nullable|string|max:100',
            'status' => 'required|string|in:new,in_followup,quotation_sent,quotation_approved,booking_done,lost,cancelled',
            'lost_reason' => 'nullable|string|max:255',
            'priority' => 'required|string|in:high,medium,low',
            'notes' => 'nullable|string',
            'conversion_notes' => 'nullable|string',
        ]);

        $enquiry->update($validated);

        \Alert::success('Enquiry updated successfully!')->flash();

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
}
