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

        $this->crud->setListView('admin.iam.role.list');
    }

    public function index()
    {
        if (! backpack_user()->can('IAM_RBAC_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view roles.');
        }

        // Spatie roles are stored in the designation table (config/permission.php), so the
        // Designation screen is the one place to manage them and their permissions (DEC-018).
        return redirect(backpack_url('org/designation'));
    }

    public function create()
    {
        if (! backpack_user()->can('IAM_RBAC_MANAGE')) {
            abort(403, 'Unauthorized. You do not have permission to create roles.');
        }

        $this->crud->setCreateView('admin.iam.role.create');

        return view('admin.iam.role.create', [
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

        $this->crud->setEditView('admin.iam.role.edit');

        $role = Role::with('permissions')->findOrFail($id);

        return view('admin.iam.role.edit', [
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
