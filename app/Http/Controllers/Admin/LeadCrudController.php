<?php

namespace App\Http\Controllers\Admin;

use App\Models\CRM\Lead;
use App\Models\CRM\LeadSource;
use App\Services\OrgService;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Carbon\Carbon;
use Illuminate\Http\Request;

class LeadCrudController extends CrudController
{
    use CreateOperation;
    use DeleteOperation;
    use ListOperation;
    use UpdateOperation;

    public function setup()
    {
        CRUD::setModel(Lead::class);

        CRUD::setRoute(
            config('backpack.base.route_prefix').'/lead'
        );

        CRUD::setEntityNameStrings(
            'lead',
            'leads'
        );
    }

    protected function setupListOperation()
    {
        $this->crud->setListView(
            'admin.lead.list'
        );
    }

    public function index()
    {
        $this->crud->setListView(
            'admin.lead.list'
        );

        $leads = Lead::with([
            'source',
            'segment',
            'model',
            'variant',
            'color',
        ])
            ->orderByDesc('id')
            ->get();

        $gridData = $leads->map(function ($lead, $index) {

            $mapped = $lead->toArray();

            $mapped['status'] =
                $lead->status_label;
            $mapped['status'] = ucfirst(str_replace('_', ' ', $lead->status));

            $mapped['serial_no'] =
                $index + 1;

            $mapped['customer_name'] =
                trim(
                    $lead->first_name.' '.
                    $lead->last_name
                );

            $mapped['source_name'] =
                $lead->source?->name ?? '—';

            $mapped['segment_name'] =
                $lead->segment?->name ?? '—';

            $mapped['model_name'] =
                $lead->model?->name ?? '—';

            $mapped['variant_name'] =
                $lead->variant?->display_name
                ??
                $lead->variant?->custom_name
                ??
                $lead->variant?->oem_name
                ??
                '—';

            $mapped['color_name'] =
                $lead->color?->name ?? '—';

            $editUrl = backpack_url("lead/{$lead->id}/edit");

            $processUrl = backpack_url(
                'enquiries/add-hot-enquiry?lead_no='.urlencode($lead->lead_no)
            );

            $mapped['action'] = '
                <div class="d-flex gap-2 justify-content-center">

                    <a href="'.$editUrl.'"
                    class="btn btn-sm btn-primary">
                        Edit
                    </a>

                    <a href="'.$processUrl.'"
                    class="btn btn-sm btn-success">
                        Process
                    </a>

                </div>';

            return $mapped;

        })->values();

        return view(

            'admin.lead.list',

            [

                'title' => 'Lead Master',

                'gridConfig' => [

                    'columns' => [

                        [
                            'field' => 'serial_no',
                            'headerName' => 'S.No.',
                        ],

                        [
                            'field' => 'lead_no',
                            'headerName' => 'Lead No.',
                        ],

                        [
                            'field' => 'customer_name',
                            'headerName' => 'Customer',
                        ],

                        [
                            'field' => 'mobile',
                            'headerName' => 'Contact No.',
                        ],

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

                        [
                            'field' => 'priority',
                            'headerName' => 'Priority',
                        ],

                        [
                            'field' => 'status',
                            'headerName' => 'Status',
                        ],

                        [
                            'field' => 'action',
                            'headerName' => 'Actions',
                        ],

                    ],

                    'data' => $gridData,

                ],

            ]

        );
    }

    public function create()
    {
        return view('admin.lead.create', [

            'title' => 'Add New Lead',

            'sources' => LeadSource::where('is_active', 1)
                ->orderBy('name')
                ->pluck('name', 'code'),

            'segments' => OrgService::segments(),

            'models' => [],

            'colors' => [],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([

            'source_code' => 'required|exists:xlr8_crm_lead_sources,code',

            'first_name' => 'required|string|max:100',

            'mobile' => 'required|digits:10',

            'segment_code' => 'required|exists:xlr8_vehicle_segment,code',

            'model_code' => 'required|exists:xlr8_vehicle_model,code',

            'variant_code' => 'required|exists:xlr8_vehicle_variant,code',

            'color_code' => 'required|exists:xlr8_vehicle_color,code',

            'expected_delivery_date' => 'nullable|date',

            'priority' => 'required|string',


            'notes' => 'nullable|string',

            'referral_details' => 'nullable|string|max:255',

            'last_name' => 'nullable|string|max:100',

            'email' => 'nullable|email|max:150',

            'occupation' => 'nullable|string|max:150',

        ]);
        if (! empty($validated['expected_delivery_date'])) {

            $validated['expected_delivery_date'] =
                Carbon::createFromFormat(
                    'd-m-Y',
                    $validated['expected_delivery_date']
                )->format('Y-m-d');

        }

        

        $lastLead = Lead::latest('id')->first();

        if ($lastLead) {

            $nextNo = $lastLead->id + 1;

        } else {

            $nextNo = 1;

        }

        $validated['lead_no'] =
            'LD'.str_pad($nextNo, 6, '0', STR_PAD_LEFT);

        $validated['capture_date'] = now()->toDateString();


        $validated['created_by'] = backpack_user()->id;

        Lead::create($validated);

        \Alert::success(
            'Lead created successfully!'
        )->flash();

        return redirect(
            backpack_url('lead')
        );
    }

    public function edit($id)
    {
        $lead = Lead::findOrFail($id);

        return view(
            'admin.lead.edit',
            [

                'title' => 'Edit Lead',

                'lead' => $lead,

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

                'models' => OrgService::models($lead->segment_code),

                'variants' => OrgService::variants($lead->model_code),

                'colors' => OrgService::colors($lead->variant_code),

            ]
        );
    }

    public function update(
        Request $request,
        $id
    ) {
        $lead = Lead::findOrFail($id);

        $validated = $request->validate([

            'source_code' => 'required|exists:xlr8_crm_lead_sources,code',

            'first_name' => 'required|string|max:100',

            'last_name' => 'nullable|string|max:100',

            'mobile' => 'required|digits:10',

            'email' => 'nullable|email|max:150',

            'occupation' => 'nullable|string|max:150',

            'segment_code' => 'required|exists:xlr8_vehicle_segment,code',

            'model_code' => 'required|exists:xlr8_vehicle_model,code',

            'variant_code' => 'required|exists:xlr8_vehicle_variant,code',

            'color_code' => 'required|exists:xlr8_vehicle_color,code',

            'expected_delivery_date' => 'nullable|date',

            'priority' => 'required|string',

            'status' => 'required|string',

            'notes' => 'nullable|string',

            'referral_details' => 'nullable|string|max:255',

        ]);

        if (! empty($validated['expected_delivery_date'])) {

            $validated['expected_delivery_date'] = Carbon::createFromFormat(
                'd-m-Y',
                $validated['expected_delivery_date']
            )->format('Y-m-d');

        }

        $validated['updated_by'] =
            backpack_user()->id;

        $lead->update(
            $validated
        );

        \Alert::success(
            'Lead updated successfully!'
        )->flash();

        return redirect(
            backpack_url('lead')
        );
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
