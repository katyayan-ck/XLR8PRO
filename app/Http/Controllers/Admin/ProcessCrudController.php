<?php

namespace App\Http\Controllers\Admin;

use App\Models\IAM\Module;
use App\Models\IAM\Process;
use App\Models\IAM\Permission;
use Illuminate\Http\Request;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

class ProcessCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;

    public function setup()
    {
        CRUD::setModel(Process::class);

        CRUD::setRoute(
            config('backpack.base.route_prefix')
            . '/process'
        );

        CRUD::setEntityNameStrings(
            'process',
            'processes'
        );
    }

    protected function setupListOperation()
    {
        $this->crud->setListView(
            'admin.process.list'
        );
    }

    public function index()
    {
        $this->crud->setListView(
            'admin.process.list'
        );

        $processes = Process::with('module')
            ->orderBy('id', 'desc')
            ->get();

        $gridData = $processes->map(function ($process, $index) {

            $mapped = $process->toArray();

            $mapped['serial_no'] =
                $index + 1;

            $mapped['module_name'] =
                $process->module?->name ?? '—';

            $mapped['is_active'] =
                $process->is_active
                ? 'Active'
                : 'Inactive';

            $editUrl =
                backpack_url(
                    "process/{$process->id}/edit"
                );

            $mapped['action'] = '
                <div class="d-flex gap-2 justify-content-center">
                    <a href="' . $editUrl . '"
                       class="btn btn-sm btn-primary py-1 px-2">
                        Edit
                    </a>
                </div>
            ';

            return $mapped;

        })->values();

        return view(
            'admin.process.list',
            [
                'title' => 'All Processes',

                'gridConfig' => [

                    'columns' => [

                        [
                            'field' => 'serial_no',
                            'headerName' => 'S.No'
                        ],

                        [
                            'field' => 'module_name',
                            'headerName' => 'Module'
                        ],

                        [
                            'field' => 'code',
                            'headerName' => 'Code'
                        ],

                        [
                            'field' => 'name',
                            'headerName' => 'Process Name'
                        ],

                        [
                            'field' => 'description',
                            'headerName' => 'Description'
                        ],

                        [
                            'field' => 'is_active',
                            'headerName' => 'Active'
                        ],

                        [
                            'field' => 'action',
                            'headerName' => 'Actions'
                        ],
                    ],

                    'data' => $gridData
                ]
            ]
        );
    }

    public function create()
    {
        $this->crud->setCreateView(
            'admin.process.create'
        );

        return view(
            'admin.process.create',
            [
                'title' => 'Add New Process',

                'modules' => Module::where(
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

                'code' =>
                    'required|string|max:50|unique:xlr8_iam_process,code',

                'name' =>
                    'required|string|max:255',

                'description' =>
                    'nullable|string',

                'is_active' =>
                    'nullable|boolean',
            ]);

        $validated['is_active'] =
            $request->boolean(
                'is_active'
            );

        Process::create(
            $validated
        );

        \Alert::success(
            'Process created successfully!'
        )->flash();

        return redirect(
            backpack_url('process')
        );
    }

    public function edit($id)
    {
        $this->crud->setEditView(
            'admin.process.edit'
        );

        $process =
            Process::findOrFail($id);

        $permissions =
            Permission::where(
                'process_code',
                $process->code
            )
                ->pluck('name')
                ->toArray();

        return view(
            'admin.process.edit',
            [

                'title' =>
                    'Edit Process - '
                    . $process->name,

                'process' =>
                    $process,

                'modules' =>
                    Module::where(
                        'is_active',
                        1
                    )
                        ->orderBy('name')
                        ->get(),

                'activePermissions' =>
                    $permissions,
            ]
        );
    }

    public function update(
        Request $request,
        $id
    ) {
        $process =
            Process::findOrFail($id);

        $validated =
            $request->validate([

                'module_code' =>
                    'required|exists:xlr8_iam_module,code',

                'code' =>
                    'required|string|max:50|unique:xlr8_iam_process,code,' . $id,

                'name' =>
                    'required|string|max:255',

                'description' =>
                    'nullable|string',

                'is_active' =>
                    'boolean',
            ]);

        if (
            $process->is_active == 1 &&
            !$request->boolean('is_active')
        ) {

            $permissionCount =
                Permission::where(
                    'process_code',
                    $process->code
                )
                    ->count();

            if ($permissionCount > 0) {

                \Alert::error(
                    "Cannot deactivate Process. {$permissionCount} Permission(s) exist."
                )->flash();

                return redirect()
                    ->back()
                    ->withInput();
            }
        }

        $validated['is_active'] =
            $request->boolean(
                'is_active'
            );

        $process->update(
            $validated
        );

        \Alert::success(
            'Process updated successfully!'
        )->flash();

        return redirect(
            backpack_url('process')
        );
    }
}