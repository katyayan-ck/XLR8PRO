<?php

namespace App\Http\Controllers\Admin\Sales\LeadSource;

use App\Http\Requests\LeadSourceRequest;
use App\Models\CRM\LeadSource;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Http\Request;

/**
 * Lead Source is a Sales-module configuration entity (feeds the "Source"
 * dropdown on Enquiry/Campaign), so it is namespaced under Admin\Sales,
 * matching the menu_items.blade.php module grouping — not Admin\Crm.
 *
 * No "lead_source.*" permission existed in xlr8_iam_permissions before this
 * change — this domain had zero permission coverage at all. Per the user's
 * explicit direction (20-09-2026), minted 4 new permissions
 * (lead_source.view/create/edit/delete) following the app's real
 * `resource.action` convention, matching the existing 76 rows exactly
 * (module_code/process_code left NULL, same as every other real permission —
 * confirmed those columns are nullable and unpopulated on 100% of existing rows).
 */
class LeadSourceCrudController extends CrudController
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
        CRUD::setModel(LeadSource::class);
        CRUD::setRoute(config('backpack.base.route_prefix').'/sales/lead-source');
        CRUD::setEntityNameStrings('lead source', 'lead sources');
    }

    /**
     * Overrides ListOperation's search()/showDetailsRow() to add a permission gate.
     * See known-bugs-report.md BUG-054: both trait defaults only check hasAccessOrFail('list'),
     * which is auto-allowed, so they bypassed every other check in this controller.
     */
    public function search()
    {
        if (! backpack_user()->can('SLS_LDSR_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view lead sources.');
        }

        return $this->traitSearch();
    }

    public function showDetailsRow($id)
    {
        if (! backpack_user()->can('SLS_LDSR_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view lead sources.');
        }

        return $this->traitShowDetailsRow($id);
    }

    protected function setupListOperation()
    {
        if (! backpack_user()->can('SLS_LDSR_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view lead sources.');
        }

        $this->crud->setListView('admin.sales.lead-source.list');
    }

    public function index()
    {
        if (! backpack_user()->can('SLS_LDSR_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view lead sources.');
        }

        $this->crud->setListView('admin.sales.lead-source.list');

        $leadSources = LeadSource::select([
            'id',
            'code',
            'name',
            'description',
            'is_active',
        ])
            ->orderBy('created_at', 'desc')->get();

        $gridData = $leadSources->map(function ($source, $index) {
            $mapped = $source->toArray();
            $mapped['serial_no'] = $index + 1;
            $mapped['is_active'] = $source->is_active ? 'Active' : 'Inactive';

            $editUrl = backpack_url("sales/lead-source/{$source->id}/edit");

            $mapped['action'] = '
                <div class="d-flex gap-2 justify-content-center">
                    <a href="'.$editUrl.'"
                       class="btn btn-sm btn-primary py-1 px-2"
                       title="Edit">
                         Edit
                    </a>
                </div>
            ';

            return $mapped;
        })->values();

        return view('admin.sales.lead-source.list', [
            'title' => 'All Lead Sources',
            'gridConfig' => [
                'columns' => [
                    ['field' => 'serial_no',    'headerName' => 'S.No.'],
                    ['field' => 'code',         'headerName' => 'Code'],
                    ['field' => 'name',         'headerName' => 'Source Name'],
                    ['field' => 'description',  'headerName' => 'Description'],
                    ['field' => 'is_active',    'headerName' => 'Is Active'],
                    ['field' => 'action',       'headerName' => 'Actions'],
                ],
                'data' => $gridData,
            ],
        ]);
    }

    public function create()
    {
        if (! backpack_user()->can('SLS_LDSR_CREATE')) {
            abort(403, 'Unauthorized. You do not have permission to create lead sources.');
        }

        $this->crud->setCreateView('admin.sales.lead-source.create');

        return view('admin.sales.lead-source.create', [
            'title' => 'Add New Lead Source',
        ]);
    }

    public function checkCode(Request $request)
    {
        $exists = LeadSource::where('code', strtoupper(trim($request->code)))
            ->exists();

        return response()->json([
            'exists' => $exists,
        ]);
    }

    public function store(LeadSourceRequest $request)
    {
        if (! backpack_user()->can('SLS_LDSR_CREATE')) {
            abort(403, 'Unauthorized. You do not have permission to create lead sources.');
        }

        $validated = $request->validated();

        $leadSource = LeadSource::create($validated);

        \Alert::success('Lead Source created successfully!')->flash();

        LeadSource::clearCache();

        return redirect(backpack_url('sales/lead-source'));
    }

    public function edit($id)
    {
        if (! backpack_user()->can('SLS_LDSR_EDIT')) {
            abort(403, 'Unauthorized. You do not have permission to edit lead sources.');
        }

        $this->crud->setEditView('admin.sales.lead-source.edit');

        $leadSource = LeadSource::findOrFail($id);

        return view('admin.sales.lead-source.edit', [
            'title' => 'Edit Lead Source - '.$leadSource->name,
            'leadSource' => $leadSource,
        ]);
    }

    public function update(LeadSourceRequest $request, $id)
    {
        if (! backpack_user()->can('SLS_LDSR_EDIT')) {
            abort(403, 'Unauthorized. You do not have permission to edit lead sources.');
        }

        $leadSource = LeadSource::findOrFail($id);

        $validated = $request->validated();

        $leadSource->update($validated);

        \Alert::success('Lead Source updated successfully!')->flash();

        LeadSource::clearCache();

        return redirect(backpack_url('sales/lead-source'));
    }

    public function destroy($id)
    {
        if (! backpack_user()->can('SLS_LDSR_DELETE')) {
            abort(403, 'Unauthorized. You do not have permission to delete lead sources.');
        }

        return $this->crud->delete($id);
    }
}
