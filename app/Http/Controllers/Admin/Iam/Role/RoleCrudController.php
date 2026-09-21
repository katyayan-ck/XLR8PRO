<?php

namespace App\Http\Controllers\Admin\Iam\Role;

use App\Http\Requests\RoleRequest;
use App\Models\IAM\Permission;
use App\Models\IAM\Role;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

class RoleCrudController extends CrudController
{
    use CreateOperation;
    use DeleteOperation;
    use ListOperation;
    use UpdateOperation;

    public function setup()
    {
        CRUD::setModel(Role::class);
        CRUD::setRoute(config('backpack.base.route_prefix').'/iam/role');
        CRUD::setEntityNameStrings('role', 'roles');
    }

    protected function setupListOperation()
    {
        if (! backpack_user()->can('IAM_RBAC_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view roles.');
        }

        $this->crud->addClause('where', 'is_post', false);
        $this->crud->setListView('admin.role.list');
    }

    public function index()
    {
        if (! backpack_user()->can('IAM_RBAC_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view roles.');
        }

        $this->crud->setListView('admin.role.list');

        $roles = Role::withCount('permissions')
            ->select([
                'id',
                'name',
                'guard_name',
                'created_at',
                'updated_at',
            ])
            ->orderBy('id', 'desc')
            ->get();

        $gridData = $roles->map(function ($role, $index) {
            $mapped = $role->toArray();
            $mapped['serial_no'] = $index + 1;
            $mapped['permissions_count'] = $role->permissions_count.' permissions';

            $editUrl = backpack_url("iam/role/{$role->id}/edit");

            $mapped['action'] = '
                <div class="d-flex gap-2 justify-content-center">
                    <a href="'.$editUrl.'" class="btn btn-sm btn-primary py-1 px-2" title="Edit">Edit</a>
                </div>
            ';

            return $mapped;
        })->values();

        return view('admin.role.list', [
            'title' => 'All Roles',
            'gridConfig' => [
                'columns' => [
                    ['field' => 'serial_no',          'headerName' => 'S.No.'],
                    ['field' => 'name',               'headerName' => 'Role Name'],
                    ['field' => 'guard_name',         'headerName' => 'Guard'],
                    ['field' => 'permissions_count',  'headerName' => 'Permissions'],
                    ['field' => 'created_at',         'headerName' => 'Created At'],
                    ['field' => 'action',             'headerName' => 'Actions'],
                ],
                'data' => $gridData,
            ],
        ]);
    }

    public function create()
    {
        if (! backpack_user()->can('IAM_RBAC_MANAGE')) {
            abort(403, 'Unauthorized. You do not have permission to create roles.');
        }

        $this->crud->setCreateView('admin.role.create');

        return view('admin.role.create', [
            'title' => 'Add New Role',
            'permissions' => Permission::orderBy('name')->get(),
        ]);
    }

    public function store(RoleRequest $request)
    {
        if (! backpack_user()->can('IAM_RBAC_MANAGE')) {
            abort(403, 'Unauthorized. You do not have permission to create roles.');
        }

        $validated = $request->validated();

        $role = Role::create([
            'name' => $validated['name'],
            'guard_name' => $validated['guard_name'],
        ]);

        if (! empty($validated['permissions'])) {
            $role->permissions()->sync($validated['permissions']);
        }

        \Alert::success('Role created successfully!')->flash();

        return redirect(backpack_url('iam/role'));
    }

    public function edit($id)
    {
        if (! backpack_user()->can('IAM_RBAC_MANAGE')) {
            abort(403, 'Unauthorized. You do not have permission to edit roles.');
        }

        $this->crud->setEditView('admin.role.edit');

        $role = Role::with('permissions')->findOrFail($id);

        return view('admin.role.edit', [
            'title' => 'Edit Role',
            'role' => $role,
            'permissions' => Permission::orderBy('name')->get(),
        ]);
    }

    public function update(RoleRequest $request, $id)
    {
        if (! backpack_user()->can('IAM_RBAC_MANAGE')) {
            abort(403, 'Unauthorized. You do not have permission to edit roles.');
        }

        $role = Role::findOrFail($id);

        $validated = $request->validated();

        $role->update([
            'name' => $validated['name'],
            'guard_name' => $validated['guard_name'],
        ]);

        $role->permissions()->sync($validated['permissions'] ?? []);

        \Alert::success('Role updated successfully!')->flash();

        return redirect(backpack_url('iam/role'));
    }

    public function destroy($id)
    {
        if (! backpack_user()->can('IAM_RBAC_MANAGE')) {
            abort(403, 'Unauthorized. You do not have permission to delete roles.');
        }

        return $this->crud->delete($id);
    }
}
