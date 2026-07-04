<?php

namespace App\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Http\Request;
use App\Models\CRM\LeadSource;

class LeadSourceCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;

    public function setup()
    {
        CRUD::setModel(LeadSource::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/lead-source');
        CRUD::setEntityNameStrings('lead source', 'lead sources');
    }

    protected function setupListOperation()
    {
        $this->crud->setListView('admin.lead-source.list');
    }

    public function index()
    {
        $this->crud->setListView('admin.lead-source.list');

        $leadSources = LeadSource::select([
            'id',
            'code',
            'name',
            'description',
            'is_active',
           // 'sort_order'
        ])
        ->orderBy('name' , 'asc', )->get();
        
        $gridData = $leadSources->map(function ($source, $index) {
            $mapped = $source->toArray();
            $mapped['serial_no'] = $index + 1;
            $mapped['is_active'] = $source->is_active ? 'Active' : 'Inactive';

            $editUrl = backpack_url("lead-source/{$source->id}/edit");

            $mapped['action'] = '
                <div class="d-flex gap-2 justify-content-center">
                    <a href="' . $editUrl . '"
                       class="btn btn-sm btn-primary py-1 px-2"
                       title="Edit">
                         Edit
                    </a>
                </div>
            ';
            return $mapped;
        })->values();

        return view('admin.lead-source.list', [
            'title' => 'All Lead Sources',
            'gridConfig' => [
                'columns' => [
                    ['field' => 'serial_no',    'headerName' => 'S.No'],
                    ['field' => 'code',         'headerName' => 'Code'],
                    ['field' => 'name',         'headerName' => 'Source Name'],
                    ['field' => 'description',  'headerName' => 'Description'],
                   // ['field' => 'sort_order',   'headerName' => 'Sort Order'],
                    ['field' => 'is_active',    'headerName' => 'Is Active'],
                    ['field' => 'action',       'headerName' => 'Actions']
                ],
                'data' => $gridData
            ]
        ]);
    }

    public function create()
    {
        $this->crud->setCreateView('admin.lead-source.create');

        return view('admin.lead-source.create', [
            'title' => 'Add New Lead Source',
        ]);
    }

    public function store(Request $request)
    {
        //dd($request -> all());
        $validated = $request->validate([
            'code'        => 'required|string|min:3|max:10|unique:xlr8_crm_lead_sources,code',
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
          //  'sort_order'  => 'nullable|integer|min:0',
            'is_active'   => 'boolean',
        ]);

        $leadSource = LeadSource::create($validated);

        \Alert::success('Lead Source created successfully!')->flash();

        // Clear cache so new source appears in dropdowns immediately
        LeadSource::clearCache();

        return redirect(backpack_url('lead-source'));
    }

    public function edit($id)
    {
        $this->crud->setEditView('admin.lead-source.edit');

        $leadSource = LeadSource::findOrFail($id);

        return view('admin.lead-source.edit', [
            'title'      => 'Edit Lead Source - ' . $leadSource->name,
            'leadSource' => $leadSource,
        ]);
    }

    public function update(Request $request, $id)
    {
        $leadSource = LeadSource::findOrFail($id);

        $validated = $request->validate([
            'code'        => 'required|string|min:3|max:10|unique:xlr8_crm_lead_sources,code,' . $id,
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            //'sort_order'  => 'nullable|integer|min:0',
            'is_active'   => 'boolean',
        ]);

        $leadSource->update($validated);

        \Alert::success('Lead Source updated successfully!')->flash();

        // Clear cache after update
        LeadSource::clearCache();

        return redirect(backpack_url('lead-source'));
    }
}