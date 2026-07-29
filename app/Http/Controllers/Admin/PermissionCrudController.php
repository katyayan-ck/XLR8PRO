<?php

namespace App\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Http\Request;
use App\Models\IAM\Permission;

class PermissionCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;

    public function setup()
    {
        CRUD::setModel(Permission::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/permission');
        CRUD::setEntityNameStrings('permission', 'permissions');
    }

    protected function setupListOperation()
    {
        $this->crud->setListView('admin.permission.list');
    }

    public function index()
    {
        $this->crud->setListView('admin.permission.list');

        $permissions = Permission::with([
            'module',
            'process'
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
                    "permission/{$permission->id}/edit"
                );

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

        return view(
            'admin.permission.list',
            [
                'title' => 'All Permissions',

                'gridConfig' => [

                    'columns' => [

                        [
                            'field' => 'serial_no',
                            'headerName' => 'S.No.'
                        ],

                        [
                            'field' => 'module_name',
                            'headerName' => 'Module'
                        ],

                        [
                            'field' => 'process_name',
                            'headerName' => 'Process'
                        ],

                        [
                            'field' => 'name',
                            'headerName' => 'Permission Name'
                        ],

                        [
                            'field' => 'guard_name',
                            'headerName' => 'Guard'
                        ],

                        [
                            'field' => 'action',
                            'headerName' => 'Actions'
                        ]
                    ],

                    'data' => $gridData
                ]
            ]
        );
    }

    public function create()
    {
        return view(
            'admin.permission.create',
            [
                'title' => 'Add Permission',

                'modules' =>
                    \App\Models\IAM\Module::where(
                        'is_active',
                        1
                    )
                        ->orderBy('name')
                        ->get()
            ]
        );
    }

    public function store(Request $request)
    {
        $validated =
            $request->validate([

                'module_code' =>
                    'required|exists:xlr8_iam_module,code',

                'process_code' =>
                    'required|exists:xlr8_iam_process,code',

                'name' =>
                    'required|string|unique:xlr8_iam_permissions,name',

                'guard_name' =>
                    'required|string',
            ]);

        \App\Models\IAM\Permission::create(
            $validated
        );

        \Alert::success(
            'Permission created successfully!'
        )->flash();

        return redirect(
            backpack_url('permission')
        );
    }
    public function edit($id)
    {
        $permission =
            \App\Models\IAM\Permission::findOrFail($id);

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

                'title' =>
                    'Edit Permission',

                'permission' =>
                    $permission,

                'modules' =>
                    \App\Models\IAM\Module::where(
                        'is_active',
                        1
                    )
                        ->orderBy('name')
                        ->get(),

                'processes' =>
                    \App\Models\IAM\Process::where(
                        'module_code',
                        $permission->module_code
                    )
                        ->where(
                            'is_active',
                            1
                        )
                        ->orderBy('name')
                        ->get(),

                'suffix' =>
                    $suffix,
            ]
        );
    }

    public function update(
        Request $request,
        $id
    ) {
        $permission =
            \App\Models\IAM\Permission::findOrFail(
                $id
            );

        $validated = $request->validate([

            'module_code' =>
                'required|exists:xlr8_iam_module,code',

            'process_code' =>
                'required|exists:xlr8_iam_process,code',

            'name' =>
                'required|string|unique:xlr8_iam_permissions,name,' .
                $permission->id,

            'guard_name' =>
                'required|string',
        ]);

        $permission->update(
            $validated
        );

        \Alert::success(
            'Permission updated successfully!'
        )->flash();

        return redirect(
            backpack_url('permission')
        );
    }
    public function getProcesses($moduleCode)
    {
        return \App\Models\IAM\Process::where(
            'module_code',
            $moduleCode
        )
            ->where('is_active', 1)
            ->orderBy('name')
            ->get([
                'code',
                'name'
            ]);
    }
}
