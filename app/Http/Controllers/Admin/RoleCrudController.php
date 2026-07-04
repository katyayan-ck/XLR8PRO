<?php

namespace App\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Http\Request;
use App\Models\IAM\Role;
use App\Models\IAM\Permission;
use Spatie\Permission\Models\Role as SpatieRole; // Agar original Spatie model bhi use karna ho to

class RoleCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;

    public function setup()
    {
        CRUD::setModel(Role::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/role');
        CRUD::setEntityNameStrings('role', 'roles');
    }

    protected function setupListOperation()
    {
    $this->crud->addClause('where', 'is_post', false); // To show only non-post roles
    $this->crud->setListView('admin.role.list');
    }

    public function index()
    {
        $this->crud->setListView('admin.role.list');

        $roles = Role::withCount('permissions')
            ->select([
                'id',
                'name',
                'guard_name',
                'created_at',
                'updated_at'
            ])
            ->orderBy('id', 'desc')
            ->get();

        $gridData = $roles->map(function ($role, $index) {
            $mapped = $role->toArray();
            $mapped['serial_no'] = $index + 1;
            $mapped['permissions_count'] = $role->permissions_count . ' permissions';

            $editUrl = backpack_url("role/{$role->id}/edit");

            $mapped['action'] = '
                <div class="d-flex gap-2 justify-content-center">
                    <a href="' . $editUrl . '" class="btn btn-sm btn-primary py-1 px-2" title="Edit">Edit</a>
                </div>
            ';

            return $mapped;
        })->values();

        return view('admin.role.list', [
            'title' => 'All Roles',
            'gridConfig' => [
                'columns' => [
                    ['field' => 'serial_no',          'headerName' => 'S.No'],
                    ['field' => 'name',               'headerName' => 'Role Name'],
                    ['field' => 'guard_name',         'headerName' => 'Guard'],
                    ['field' => 'permissions_count',  'headerName' => 'Permissions'],
                    ['field' => 'created_at',         'headerName' => 'Created At'],
                    ['field' => 'action',             'headerName' => 'Actions']
                ],
                'data' => $gridData
            ]
        ]);
    }

    public function create()
    {
        $this->crud->setCreateView('admin.role.create');

        return view('admin.role.create', [
            'title'       => 'Add New Role',
            'permissions' => Permission::orderBy('name')->get()
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|unique:xlr8_iam_roles,name|max:255',
            'guard_name'  => 'required|in:web,api',
            'permissions' => 'array',
            'permissions.*' => 'exists:xlr8_iam_permissions,id'
        ]);

        $role = Role::create([
            'name'       => $validated['name'],
            'guard_name' => $validated['guard_name'],
        ]);

        if (!empty($validated['permissions'])) {
            $role->permissions()->sync($validated['permissions']);
        }

        \Alert::success('Role created successfully!')->flash();
        return redirect(backpack_url('role'));
    }

    public function edit($id)
    {
        $this->crud->setEditView('admin.role.edit');

        $role = Role::with('permissions')->findOrFail($id);

        return view('admin.role.edit', [
            'title'       => 'Edit Role',
            'role'        => $role,
            'permissions' => Permission::orderBy('name')->get()
        ]);
    }

    public function update(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        $validated = $request->validate([
            'name'        => 'required|unique:xlr8_iam_roles,name,' . $id . '|max:255',
            'guard_name'  => 'required|in:web,api',
            'permissions' => 'array',
            'permissions.*' => 'exists:xlr8_iam_permissions,id'
        ]);

        $role->update([
            'name'       => $validated['name'],
            'guard_name' => $validated['guard_name'],
        ]);

        $role->permissions()->sync($validated['permissions'] ?? []);

        \Alert::success('Role updated successfully!')->flash();
        return redirect(backpack_url('role'));
    }
}
