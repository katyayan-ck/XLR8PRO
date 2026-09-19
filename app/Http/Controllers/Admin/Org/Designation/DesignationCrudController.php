<?php

namespace App\Http\Controllers\Admin\Org\Designation;

use App\Http\Requests\DesignationRequest;
use App\Models\Admin\Designation;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

class DesignationCrudController extends CrudController
{
    use CreateOperation;
    use DeleteOperation;
    use ListOperation;
    use UpdateOperation;

    public function setup()
    {
        CRUD::setModel(Designation::class);
        CRUD::setRoute(config('backpack.base.route_prefix').'/designation');
        CRUD::setEntityNameStrings('designation', 'designations');
    }

    protected function setupListOperation()
    {
        if (! backpack_user()->can('designation.view')) {
            abort(403, 'Unauthorized. You do not have permission to view designations.');
        }

        $this->crud->setListView('admin.designation.list');
    }

    public function index()
    {
        if (! backpack_user()->can('designation.view')) {
            abort(403, 'Unauthorized. You do not have permission to view designations.');
        }

        $this->crud->setListView('admin.designation.list');

        $designations = Designation::with('parentDesignation')
            ->select([
                'id',
                'code',
                'name',
                'description',
                'rank',
                'is_top_mgmt',
                'parent_desig_code',
                'is_active',
            ])
            ->orderBy('id', 'desc')
            ->get();

        $gridData = $designations->map(function ($desig, $index) {

            $mapped = $desig->toArray();

            $mapped['serial_no'] = $index + 1;

            $mapped['is_active'] =
                $desig->is_active ? 'Active' : 'Inactive';

            $mapped['is_top_mgmt'] =
                $desig->is_top_mgmt ? 'Yes' : 'No';

            $rankMap = [
                1 => 'A',
                2 => 'B',
                3 => 'C',
                4 => 'D',
                5 => 'E',
            ];

            $mapped['rank'] =
                $rankMap[$desig->rank] ?? '-';

            $mapped['reports_to'] =
                $desig->parentDesignation?->name ?? '-';

            $editUrl =
                backpack_url("designation/{$desig->id}/edit");

            $mapped['action'] = '
            <div class="d-flex gap-2 justify-content-center">
                <a href="'.$editUrl.'"
                    class="btn btn-sm btn-primary py-1 px-2">
                    Edit
                </a>
            </div>
        ';

            return $mapped;
        })->values();

        return view('admin.designation.list', [

            'title' => 'All Designations',

            'gridConfig' => [

                'columns' => [

                    ['field' => 'serial_no', 'headerName' => 'S.No.'],

                    ['field' => 'code', 'headerName' => 'Code'],

                    ['field' => 'name', 'headerName' => 'Designation Name'],

                    ['field' => 'description', 'headerName' => 'Description'],

                    ['field' => 'rank', 'headerName' => 'Rank'],

                    ['field' => 'parent_desig_code', 'headerName' => 'Reports To'],

                    ['field' => 'is_top_mgmt', 'headerName' => 'Top Management'],

                    ['field' => 'is_active', 'headerName' => 'Is Active'],

                    ['field' => 'action', 'headerName' => 'Actions'],
                ],

                'data' => $gridData,
            ],
        ]);
    }

    public function create()
    {
        if (! backpack_user()->can('designation.create')) {
            abort(403, 'Unauthorized. You do not have permission to create designations.');
        }

        return view('admin.designation.create', [

            'title' => 'Add New Designation',

            'designations' =>
            Designation::orderBy('name')->get(),

        ]);
    }

    public function store(DesignationRequest $request)
    {
        if (! backpack_user()->can('designation.create')) {
            abort(403, 'Unauthorized. You do not have permission to create designations.');
        }

        $validated = $request->validated();

        $validated['rank'] = $validated['rank'] ?? 0;
        $validated['guard_name'] = 'web';

        $designation = Designation::create($validated);

        if ($request->hasFile('designation_image')) {
            $designation
                ->addMediaFromRequest('designation_image')
                ->toMediaCollection('designation_image');
        }

        \Alert::success('Designation created successfully!')->flash();

        return redirect(backpack_url('designation'));
    }

    public function edit($id)
    {
        if (! backpack_user()->can('designation.edit')) {
            abort(403, 'Unauthorized. You do not have permission to edit designations.');
        }

        $this->crud->setEditView('admin.designation.edit');

        $designation = Designation::findOrFail($id);

        return view('admin.designation.edit', [
            'title' => 'Edit Designation - '.$designation->name,
            'designation' => $designation,
            'designations' => Designation::orderBy('name')->get(),
        ]);
    }

    public function update(DesignationRequest $request, $id)
    {
        if (! backpack_user()->can('designation.edit')) {
            abort(403, 'Unauthorized. You do not have permission to edit designations.');
        }

        $designation = Designation::findOrFail($id);

        $validated = $request->validated();

        $validated['rank'] = $validated['rank'] ?? 0;
        $validated['guard_name'] = 'web';

        $designation->update($validated);

        if ($request->hasFile('designation_image')) {
            $designation
                ->addMediaFromRequest('designation_image')
                ->toMediaCollection('designation_image');
        }

        \Alert::success('Designation updated successfully!')->flash();

        return redirect(backpack_url('designation'));
    }

    public function destroy($id)
    {
        if (! backpack_user()->can('designation.delete')) {
            abort(403, 'Unauthorized. You do not have permission to delete designations.');
        }

        return $this->crud->delete($id);
    }
}
