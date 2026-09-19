<?php

namespace App\Http\Controllers\Admin\Org\Location;

use App\Http\Requests\LocationRequest;
use App\Models\Admin\Branch;
use App\Models\Admin\Location;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

class LocationCrudController extends CrudController
{
    use CreateOperation;
    use DeleteOperation;
    use ListOperation;
    use UpdateOperation;

    public function setup()
    {
        CRUD::setModel(Location::class);
        CRUD::setRoute(config('backpack.base.route_prefix').'/location');
        CRUD::setEntityNameStrings('location', 'locations');
    }

    protected function setupListOperation()
    {
        if (! backpack_user()->can('location.view')) {
            abort(403, 'Unauthorized. You do not have permission to view locations.');
        }

        $this->crud->setListView('admin.location.list');
    }

    public function index()
    {
        if (! backpack_user()->can('location.view')) {
            abort(403, 'Unauthorized. You do not have permission to view locations.');
        }

        $this->crud->setListView('admin.location.list');

        $locations = Location::with('branch')
            ->select([
                'id',
                'branch_code',
                'code',
                'name',
                'description',
                'phone',
                'email',
                'address',
                'city',
                'pincode',
                'latitude',
                'longitude',
                'is_active',
                'is_sales_location',
                'is_workshop',
                'is_parts_location',
                'is_stock_location',
                'is_office_only',
                'is_mwh',
                'is_lmmws',
            ])
            ->orderBy('id', 'desc')
            ->get();

        $gridData = $locations->map(function ($loc, $index) {
            $mapped = $loc->toArray();
            $mapped['serial_no'] = $index + 1;
            $mapped['is_active'] = $loc->is_active ? 'Active' : 'Inactive';
            $mapped['is_sales_location'] = $loc->is_sales_location ? 'Yes' : 'No';
            $mapped['is_workshop'] = $loc->is_workshop ? 'Yes' : 'No';
            $mapped['is_parts_location'] = $loc->is_parts_location ? 'Yes' : 'No';
            $mapped['is_stock_location'] = $loc->is_stock_location ? 'Yes' : 'No';
            $mapped['is_office_only'] = $loc->is_office_only ? 'Yes' : 'No';
            $mapped['is_mwh'] = $loc->is_mwh ? 'Yes' : 'No';
            $mapped['is_lmmws'] = $loc->is_lmmws ? 'Yes' : 'No';

            $mapped['branch'] = $loc->branch?->name ?? $loc->branch_code ?? '—';

            $editUrl = backpack_url("location/{$loc->id}/edit");

            $mapped['action'] = '
                <div class="d-flex gap-2 justify-content-center">
                    <a href="'.$editUrl.'" class="btn btn-sm btn-primary py-1 px-2" title="Edit">Edit</a>
                </div>
            ';

            return $mapped;
        })->values();

        return view('admin.location.list', [
            'title' => 'All Locations',
            'gridConfig' => [
                'columns' => [
                    ['field' => 'serial_no', 'headerName' => 'S.No.'],
                    ['field' => 'branch',      'headerName' => 'Branch Code'],
                    ['field' => 'code',      'headerName' => 'Code'],
                    ['field' => 'name',      'headerName' => 'Name'],
                    ['field' => 'description',    'headerName' => 'Description'],
                    ['field' => 'phone',     'headerName' => 'Phone'],
                    ['field' => 'email',     'headerName' => 'Email'],
                    ['field' => 'address',   'headerName' => 'Address'],
                    ['field' => 'city',      'headerName' => 'City'],
                    ['field' => 'pincode',   'headerName' => 'Pincode'],
                    ['field' => 'latitude',  'headerName' => 'Latitude'],
                    ['field' => 'longitude', 'headerName' => 'Longitude'],
                    ['field' => 'is_active', 'headerName' => 'Is Active'],
                    ['field' => 'is_sales_location', 'headerName' => 'Is Sales'],
                    ['field' => 'is_workshop', 'headerName' => 'Is Workshop'],
                    ['field' => 'is_parts_location', 'headerName' => 'Is Parts'],
                    ['field' => 'is_stock_location', 'headerName' => 'Is Stock'],
                    ['field' => 'is_office_only', 'headerName' => 'Is Office Only'],
                    ['field' => 'is_mwh', 'headerName' => 'Is MWH'],
                    ['field' => 'is_lmmws', 'headerName' => 'LMMWS'],
                    ['field' => 'action',    'headerName' => 'Actions'],
                ],
                'data' => $gridData,
            ],
        ]);
    }

    public function create()
    {
        if (! backpack_user()->can('location.create')) {
            abort(403, 'Unauthorized. You do not have permission to create locations.');
        }

        $this->crud->setCreateView('admin.location.create');

        return view('admin.location.create', [
            'title' => 'Add New Location',
            'branches' => Branch::orderBy('name')->get(),
        ]);
    }

    public function store(LocationRequest $request)
    {
        if (! backpack_user()->can('location.create')) {
            abort(403, 'Unauthorized. You do not have permission to create locations.');
        }

        $validated = $request->validated();

        $location = Location::create($validated);

        if ($request->hasFile('location_image')) {
            $location
                ->addMediaFromRequest('location_image')
                ->toMediaCollection('location_image');
        }

        \Alert::success('Location created successfully!')->flash();

        return redirect(backpack_url('location'));
    }

    public function edit($id)
    {
        if (! backpack_user()->can('location.edit')) {
            abort(403, 'Unauthorized. You do not have permission to edit locations.');
        }

        $this->crud->setEditView('admin.location.edit');

        $location = Location::findOrFail($id);

        $branches = Branch::orderBy('name')->get();

        return view('admin.location.edit', [
            'title' => 'Edit Location - '.$location->name,
            'location' => $location,
            'branches' => $branches,
        ]);
    }

    public function update(LocationRequest $request, $id)
    {
        if (! backpack_user()->can('location.edit')) {
            abort(403, 'Unauthorized. You do not have permission to edit locations.');
        }

        $location = Location::findOrFail($id);

        $validated = $request->validated();

        $location->update($validated);
        if ($request->hasFile('location_image')) {
            $location
                ->addMediaFromRequest('location_image')
                ->toMediaCollection('location_image');
        }

        \Alert::success('Location updated successfully!')->flash();

        return redirect(backpack_url('location'));
    }

    public function destroy($id)
    {
        if (! backpack_user()->can('location.delete')) {
            abort(403, 'Unauthorized. You do not have permission to delete locations.');
        }

        return $this->crud->delete($id);
    }
}
