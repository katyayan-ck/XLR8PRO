<?php

namespace App\Http\Controllers\Admin\Sales\Lead;

use App\Http\Requests\LeadRequest;
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

/**
 * Lead is a Sales-module resource (feeds into Enquiry conversion via the
 * "Process" action), so it is namespaced under Admin\Sales, matching the
 * menu_items.blade.php module grouping — same reasoning as LeadSource.
 *
 * No "lead.*" permission existed in xlr8_iam_permissions before this change.
 * Per the user's explicit direction (20-09-2026), minted 4 new permissions
 * (lead.view/create/edit/delete) following the app's real `resource.action`
 * convention, matching all pre-existing rows (module_code/process_code left
 * NULL).
 */
class LeadCrudController extends CrudController
{
    use CreateOperation;
    use DeleteOperation;
    use ListOperation {
        search as traitSearch;
        showDetailsRow as traitShowDetailsRow;
    }
    use UpdateOperation;

    public function setup()
    {
        CRUD::setModel(Lead::class);
        CRUD::setRoute(config('backpack.base.route_prefix').'/sales/lead');
        CRUD::setEntityNameStrings('lead', 'leads');
    }

    /**
     * Overrides ListOperation's search()/showDetailsRow() to add a permission gate.
     * See known-bugs-report.md BUG-054: both trait defaults only check hasAccessOrFail('list'),
     * which is auto-allowed, so they bypassed every other check in this controller.
     */
    public function search()
    {
        if (! backpack_user()->can('SLS_LEAD_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view leads.');
        }

        return $this->traitSearch();
    }

    public function showDetailsRow($id)
    {
        if (! backpack_user()->can('SLS_LEAD_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view leads.');
        }

        return $this->traitShowDetailsRow($id);
    }

    protected function setupListOperation()
    {
        if (! backpack_user()->can('SLS_LEAD_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view leads.');
        }

        $this->crud->setListView('admin.lead.list');
    }

    public function index()
    {
        if (! backpack_user()->can('SLS_LEAD_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view leads.');
        }

        $this->crud->setListView('admin.lead.list');

        $leads = Lead::with([
            'source',
            'segment',
            'model',
            'variant',
            'color',
        ])
            ->orderByDesc('id')->get();

        $gridData = $leads->map(function ($lead, $index) {
            $mapped = $lead->toArray();

            $mapped['status'] = ucfirst(str_replace('_', ' ', $lead->status));
            $mapped['serial_no'] = $index + 1;
            $mapped['customer_name'] = trim($lead->first_name.' '.$lead->last_name);
            $mapped['source_name'] = $lead->source?->name ?? '—';
            $mapped['segment_name'] = $lead->segment?->name ?? '—';
            $mapped['model_name'] = $lead->model?->name ?? '—';
            $mapped['variant_name'] = $lead->variant?->display_name
                ?? $lead->variant?->custom_name
                ?? $lead->variant?->oem_name
                ?? '—';
            $mapped['color_name'] = $lead->color?->name ?? '—';

            $editUrl = backpack_url("sales/lead/{$lead->id}/edit");
            $processUrl = backpack_url('sales/enquiry/add-hot-enquiry?lead_no='.urlencode($lead->lead_no));

            $mapped['action'] = '
                <div class="d-flex gap-2 justify-content-center">
                    <a href="'.$editUrl.'" class="btn btn-sm btn-primary">Edit</a>
                    <a href="'.$processUrl.'" class="btn btn-sm btn-success">Process</a>
                </div>';

            return $mapped;
        })->values();

        return view('admin.lead.list', [
            'title' => 'Lead Master',
            'gridConfig' => [
                'columns' => [
                    ['field' => 'serial_no',      'headerName' => 'S.No.'],
                    ['field' => 'lead_no',        'headerName' => 'Lead No.'],
                    ['field' => 'customer_name',  'headerName' => 'Customer'],
                    ['field' => 'mobile',         'headerName' => 'Contact No.'],
                    ['field' => 'source_name',    'headerName' => 'Lead Source'],
                    ['field' => 'segment_name',   'headerName' => 'Segment'],
                    ['field' => 'model_name',     'headerName' => 'Model'],
                    ['field' => 'variant_name',   'headerName' => 'Variant'],
                    ['field' => 'color_name',     'headerName' => 'Color'],
                    ['field' => 'priority',       'headerName' => 'Priority'],
                    ['field' => 'status',         'headerName' => 'Status'],
                    ['field' => 'action',         'headerName' => 'Actions'],
                ],
                'data' => $gridData,
            ],
        ]);
    }

    public function create()
    {
        if (! backpack_user()->can('SLS_LEAD_CREATE')) {
            abort(403, 'Unauthorized. You do not have permission to create leads.');
        }

        return view('admin.lead.create', [
            'title' => 'Add New Lead',
            'sources' => LeadSource::where('is_active', 1)->orderBy('name')->pluck('name', 'code'),
            'segments' => OrgService::segments(),
            'models' => [],
            'colors' => [],
        ]);
    }

    public function store(LeadRequest $request)
    {
        if (! backpack_user()->can('SLS_LEAD_CREATE')) {
            abort(403, 'Unauthorized. You do not have permission to create leads.');
        }

        $validated = $request->validated();

        if (! empty($validated['expected_delivery_date'])) {
            $validated['expected_delivery_date'] = Carbon::createFromFormat('d-m-Y', $validated['expected_delivery_date'])->format('Y-m-d');
        }

        $lastLead = Lead::latest('id')->first();
        $nextNo = $lastLead ? $lastLead->id + 1 : 1;

        $validated['lead_no'] = 'LD'.str_pad($nextNo, 6, '0', STR_PAD_LEFT);
        $validated['capture_date'] = now()->toDateString();
        $validated['created_by'] = backpack_user()->id;

        Lead::create($validated);

        \Alert::success('Lead created successfully!')->flash();

        return redirect(backpack_url('sales/lead'));
    }

    public function edit($id)
    {
        if (! backpack_user()->can('SLS_LEAD_EDIT')) {
            abort(403, 'Unauthorized. You do not have permission to edit leads.');
        }

        $lead = Lead::findOrFail($id);

        return view('admin.lead.edit', [
            'title' => 'Edit Lead',
            'lead' => $lead,
            'sources' => LeadSource::where('is_active', 1)->orderBy('name')->pluck('name', 'code'),
            'segments' => OrgService::segments(),
            'models' => OrgService::models($lead->segment_code),
            'variants' => OrgService::variants($lead->model_code),
            'colors' => OrgService::colors($lead->variant_code),
        ]);
    }

    public function update(LeadRequest $request, $id)
    {
        if (! backpack_user()->can('SLS_LEAD_EDIT')) {
            abort(403, 'Unauthorized. You do not have permission to edit leads.');
        }

        $lead = Lead::findOrFail($id);

        $validated = $request->validated();

        if (! empty($validated['expected_delivery_date'])) {
            $validated['expected_delivery_date'] = Carbon::createFromFormat('d-m-Y', $validated['expected_delivery_date'])->format('Y-m-d');
        }

        $validated['updated_by'] = backpack_user()->id;

        $lead->update($validated);

        \Alert::success('Lead updated successfully!')->flash();

        return redirect(backpack_url('sales/lead'));
    }

    public function destroy($id)
    {
        if (! backpack_user()->can('SLS_LEAD_DELETE')) {
            abort(403, 'Unauthorized. You do not have permission to delete leads.');
        }

        return $this->crud->delete($id);
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
}
