<?php

namespace App\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Http\Request;
use App\Models\Admin\UserType;

class UserTypeCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;

    public function setup()
    {
        CRUD::setModel(UserType::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/user-type');
        CRUD::setEntityNameStrings('user type', 'user types');
    }

    protected function setupListOperation()
    {
        $this->crud->setListView('admin.user-type.list');
    }

    public function index()
    {
        $this->crud->setListView('admin.user-type.list');

        $userTypes = UserType::select([
            'id',
            'code',
            'display_name',
            'description',
            'is_active'
        ])->orderBy('id', 'desc')->get();

        $gridData = $userTypes->map(function ($userType, $index) {
            $mapped = $userType->toArray();
            $mapped['serial_no'] = $index + 1;
            $mapped['is_active'] = $userType->is_active;

            $editUrl = backpack_url("user-type/{$userType->id}/edit");

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

        return view('admin.user-type.list', [
            'title' => 'All User Types',
            'gridConfig' => [
                'columns' => [
                    ['field' => 'serial_no',     'headerName' => 'S.No'],
                    ['field' => 'code',          'headerName' => 'Code'],
                    ['field' => 'display_name',  'headerName' => 'Name'],
                    ['field' => 'description',   'headerName' => 'Description'],
                    ['field' => 'is_active',     'headerName' => 'Active'],
                    ['field' => 'action',        'headerName' => 'Actions']
                ],
                'data' => $gridData
            ]
        ]);
    }

    public function create()
    {
        $this->crud->setCreateView('admin.user-type.create');

        return view('admin.user-type.create', [
            'title' => 'Add New User Type',
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code'         => 'required|string|max:5|unique:xlr8_iam_user_type,code',
            'display_name' => 'required|string|max:100',
            'description'  => 'nullable|string',
            'is_active'    => 'boolean',
        ]);

        UserType::create($validated);

        \Alert::success('User Type created successfully!')->flash();

        return redirect(backpack_url('user-type'));
    }

    public function edit($id)
    {
        $this->crud->setEditView('admin.user-type.edit');

        $userType = UserType::findOrFail($id);

        return view('admin.user-type.edit', [
            'title'     => 'Edit User Type - ' . $userType->display_name,
            'userType'  => $userType,
            'is_active'    => 'boolean',

        ]);
    }

    public function update(Request $request, $id)
    {
        $userType = UserType::findOrFail($id);

        $validated = $request->validate([
            'code'         => 'required|string|max:5|unique:xlr8_iam_user_type,code,' . $id,
            'display_name' => 'required|string|max:100',
            'description'  => 'nullable|string',
            'is_active'    => 'boolean',
        ]);

        $userType->update($validated);

        \Alert::success('User Type updated successfully!')->flash();

        return redirect(backpack_url('user-type'));
    }
}
