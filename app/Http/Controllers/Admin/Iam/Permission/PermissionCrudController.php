<?php

namespace App\Http\Controllers\Admin\Iam\Permission;

use App\Http\Requests\PermissionRequest;
use App\Models\IAM\Module;
use App\Models\IAM\Permission;
use App\Models\IAM\Process;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

class PermissionCrudController extends CrudController
{
    use CreateOperation;
    use DeleteOperation;
    use ListOperation;
    use UpdateOperation;

    public function setup()
    {
        CRUD::setModel(Permission::class);
        CRUD::setRoute(config('backpack.base.route_prefix').'/iam/permission');
        CRUD::setEntityNameStrings('permission', 'permissions');
    }

    protected function setupListOperation()
    {
        if (! backpack_user()->can('IAM_RBAC_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view permissions.');
        }

        $this->crud->setListView('admin.permission.list');
    }

    public function index()
    {
        if (! backpack_user()->can('IAM_RBAC_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view permissions.');
        }

        $this->crud->setListView('admin.permission.list');

        $permissions = Permission::with([
            'module',
            'process',
        ])
            ->orderBy('name')
            ->get();

        $gridData = $permissions->map(function ($permission, $index) {

            $mapped = $permission->toArray();

            $mapped['serial_no'] =
                $index + 1;

            $mapped['module_name'] =
                $permission->module?->name ?? '—';

            $mapped['process_name'] =
                $permission->process?->name ?? '—';

            $editUrl =
                backpack_url(
                    "iam/permission/{$permission->id}/edit"
                );

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

        return view(
            'admin.permission.list',
            [
                'title' => 'All Permissions',

                'gridConfig' => [

                    'columns' => [

                        [
                            'field' => 'serial_no',
                            'headerName' => 'S.No.',
                        ],

                        [
                            'field' => 'module_name',
                            'headerName' => 'Module',
                        ],

                        [
                            'field' => 'process_name',
                            'headerName' => 'Process',
                        ],

                        [
                            'field' => 'name',
                            'headerName' => 'Permission Name',
                        ],

                        [
                            'field' => 'guard_name',
                            'headerName' => 'Guard',
                        ],

                        [
                            'field' => 'action',
                            'headerName' => 'Actions',
                        ],
                    ],

                    'data' => $gridData,
                ],
            ]
        );
    }

    public function create()
    {
        if (! backpack_user()->can('IAM_RBAC_MANAGE')) {
            abort(403, 'Unauthorized. You do not have permission to create permissions.');
        }

        return view(
            'admin.permission.create',
            [
                'title' => 'Add Permission',

                'modules' => Module::where(
                    'is_active',
                    1
                )
                    ->orderBy('name')
                    ->get(),
            ]
        );
    }

    public function store(PermissionRequest $request)
    {
        if (! backpack_user()->can('IAM_RBAC_MANAGE')) {
            abort(403, 'Unauthorized. You do not have permission to create permissions.');
        }

        $validated = $request->validated();

        Permission::create(
            $validated
        );

        \Alert::success(
            'Permission created successfully!'
        )->flash();

        return redirect(
            backpack_url('iam/permission')
        );
    }

    public function edit($id)
    {
        if (! backpack_user()->can('IAM_RBAC_MANAGE')) {
            abort(403, 'Unauthorized. You do not have permission to edit permissions.');
        }

        $permission =
            Permission::findOrFail($id);

        $parts = explode(
            '_',
            $permission->name
        );

        $suffix = '';

        if (count($parts) > 2) {

            $suffix = implode(
                '_',
                array_slice($parts, 2)
            );
        }

        return view(
            'admin.permission.edit',
            [

                'title' => 'Edit Permission',

                'permission' => $permission,

                'modules' => Module::where(
                    'is_active',
                    1
                )
                    ->orderBy('name')
                    ->get(),

                'processes' => Process::where(
                    'module_code',
                    $permission->module_code
                )
                    ->where(
                        'is_active',
                        1
                    )
                    ->orderBy('name')
                    ->get(),

                'suffix' => $suffix,
            ]
        );
    }

    public function update(PermissionRequest $request, $id)
    {
        if (! backpack_user()->can('IAM_RBAC_MANAGE')) {
            abort(403, 'Unauthorized. You do not have permission to edit permissions.');
        }

        $permission =
            Permission::findOrFail(
                $id
            );

        $validated = $request->validated();

        $permission->update(
            $validated
        );

        \Alert::success(
            'Permission updated successfully!'
        )->flash();

        return redirect(
            backpack_url('iam/permission')
        );
    }

    public function destroy($id)
    {
        if (! backpack_user()->can('IAM_RBAC_MANAGE')) {
            abort(403, 'Unauthorized. You do not have permission to delete permissions.');
        }

        return $this->crud->delete($id);
    }

    public function getProcesses($moduleCode)
    {
        return Process::where(
            'module_code',
            $moduleCode
        )
            ->where('is_active', 1)
            ->orderBy('name')
            ->get([
                'code',
                'name',
            ]);
    }
}
