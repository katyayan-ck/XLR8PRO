<?php

namespace App\Http\Controllers\Admin\Org\Location;

use App\Http\Requests\LocationRequest;
use App\Models\Admin\Branch;
use App\Models\Admin\Location;
use App\Services\Org\LocationService;
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
    use ListOperation {
        search as traitSearch;
        showDetailsRow as traitShowDetailsRow;
    }
    use UpdateOperation;

    public function __construct(private LocationService $locations)
    {
        parent::__construct();
    }

    public function search()
    {
        $this->authorizeManage();

        return $this->traitSearch();
    }

    public function showDetailsRow($id)
    {
        $this->authorizeManage();

        return $this->traitShowDetailsRow($id);
    }

    public function setup()
    {
        CRUD::setModel(Location::class);
        CRUD::setRoute(config('backpack.base.route_prefix').'/org/location');
        CRUD::setEntityNameStrings('location', 'locations');
    }

    protected function setupListOperation()
    {
        $this->authorizeManage();

        $this->crud->setListView('admin.org.location.list');
    }

    public function index()
    {
        $this->authorizeManage();

        $this->crud->setListView('admin.org.location.list');

        $locations = Location::select([
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

        // Location::branch() joins on Branch.branch_code, which is never populated (see
        // BUG-082/084) — look Branches up by `code` directly instead of via the relation.
        $branchNamesByCode = Branch::pluck('name', 'code');

        $gridData = $locations->map(function ($loc, $index) use ($branchNamesByCode) {
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

            $mapped['branch'] = $branchNamesByCode->get($loc->branch_code) ?? $loc->branch_code ?? '—';

            $imageUrl = $loc->getFirstMediaUrl('location_image');
            $mapped['image'] = $imageUrl
                ? '<img src="'.$imageUrl.'" style="height:36px;width:36px;object-fit:cover;border-radius:6px;">'
                : '<span class="text-muted">—</span>';

            $editUrl = backpack_url("org/location/{$loc->id}/edit");

            $mapped['action'] = '
                <div class="d-flex gap-2 justify-content-center">
                    <a href="'.$editUrl.'" class="btn btn-sm btn-primary py-1 px-2" title="Edit">Edit</a>
                </div>
            ';

            return $mapped;
        })->values();

        return view('admin.org.location.list', [
            'title' => 'All Locations',
            'gridConfig' => [
                'columns' => [
                    ['field' => 'serial_no', 'headerName' => 'S.No.'],
                    ['field' => 'image',      'headerName' => 'Image'],
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
        $this->authorizeManage();

        // Updated view reference
        $this->crud->setCreateView('admin.org.location.form');

        return view('admin.org.location.form', [
            'title' => 'Add New Location',
            'branches' => Branch::orderBy('name')->get(),
        ]);
    }

    public function edit($id)
    {
        $this->authorizeManage();

        // Updated view reference
        $this->crud->setEditView('admin.org.location.form');

        $location = Location::findOrFail($id);

        $branches = Branch::orderBy('name')->get();

        return view('admin.org.location.form', [
            'title' => 'Edit Location - '.$location->name,
            'location' => $location,
            'branches' => $branches,
        ]);
    }

    public function store(LocationRequest $request)
    {
        $this->authorizeManage();

        $this->locations->create($request->validated(), $request);

        \Alert::success('Location created successfully!')->flash();

        return redirect(backpack_url('org/location'));
    }

    public function update(LocationRequest $request, $id)
    {
        $this->authorizeManage();

        $location = Location::findOrFail($id);

        $result = $this->locations->update($location, $request->validated(), $request);

        if (! $result['ok']) {
            return back()->withInput()->withErrors([
                'is_active' => 'Cannot disable this location — it still has '.implode(' and ', $result['blockers']).'. Disable those first.',
            ]);
        }

        \Alert::success('Location updated successfully!')->flash();

        return redirect(backpack_url('org/location'));
    }

    public function destroy($id)
    {
        $this->authorizeManage();

        return $this->crud->delete($id);
    }

    private function authorizeManage(): void
    {
        if (! backpack_user()->can('ORG_ENTITY_MANAGE')) {
            abort(403, 'Unauthorized. You do not have permission to manage org entities.');
        }
    }
}
