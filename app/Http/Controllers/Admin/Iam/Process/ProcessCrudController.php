<?php

namespace App\Http\Controllers\Admin\Iam\Process;

use App\Http\Requests\ProcessRequest;
use App\Models\IAM\Module;
use App\Models\IAM\Permission;
use App\Models\IAM\Process;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

class ProcessCrudController extends CrudController
{
    use CreateOperation;
    use DeleteOperation;
    use ListOperation;
    use UpdateOperation;

    /**
     * No dedicated "process.*" permission exists in xlr8_iam_permissions —
     * reuses "rbac.*", same as ModulesCrudController and PermissionCrudController.
     */
    public function setup()
    {
        CRUD::setModel(Process::class);

        CRUD::setRoute(
            config('backpack.base.route_prefix')
            .'/iam/process'
        );

        CRUD::setEntityNameStrings(
            'process',
            'processes'
        );
    }

    protected function setupListOperation()
    {
        if (! backpack_user()->can('IAM_RBAC_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view processes.');
        }

        $this->crud->setListView(
            'admin.process.list'
        );
    }

    public function index()
    {
        if (! backpack_user()->can('IAM_RBAC_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view processes.');
        }

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
                    "iam/process/{$process->id}/edit"
                );

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

        return view(
            'admin.process.list',
            [
                'title' => 'All Processes',

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
                            'field' => 'code',
                            'headerName' => 'Code',
                        ],

                        [
                            'field' => 'name',
                            'headerName' => 'Process Name',
                        ],

                        [
                            'field' => 'description',
                            'headerName' => 'Description',
                        ],

                        [
                            'field' => 'is_active',
                            'headerName' => 'Active',
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
            abort(403, 'Unauthorized. You do not have permission to create processes.');
        }

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
                    ->get(),
            ]
        );
    }

    public function store(ProcessRequest $request)
    {
        if (! backpack_user()->can('IAM_RBAC_MANAGE')) {
            abort(403, 'Unauthorized. You do not have permission to create processes.');
        }

        $validated = $request->validated();

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
            backpack_url('iam/process')
        );
    }

    public function edit($id)
    {
        if (! backpack_user()->can('IAM_RBAC_MANAGE')) {
            abort(403, 'Unauthorized. You do not have permission to edit processes.');
        }

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

                'title' => 'Edit Process - '
                    .$process->name,

                'process' => $process,

                'modules' => Module::where(
                    'is_active',
                    1
                )
                    ->orderBy('name')
                    ->get(),

                'activePermissions' => $permissions,
            ]
        );
    }

    public function update(
        ProcessRequest $request,
        $id
    ) {
        if (! backpack_user()->can('IAM_RBAC_MANAGE')) {
            abort(403, 'Unauthorized. You do not have permission to edit processes.');
        }

        $process =
            Process::findOrFail($id);

        $validated = $request->validated();

        if (
            $process->is_active == 1 &&
            ! $request->boolean('is_active')
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
            backpack_url('iam/process')
        );
    }

    public function destroy($id)
    {
        if (! backpack_user()->can('IAM_RBAC_MANAGE')) {
            abort(403, 'Unauthorized. You do not have permission to delete processes.');
        }

        return $this->crud->delete($id);
    }
}
