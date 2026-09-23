<?php

namespace App\Http\Controllers\Admin\Iam\Modules;

use App\Http\Requests\ModulesRequest;
use App\Models\IAM\Module;
use App\Models\IAM\Process;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

class ModulesCrudController extends CrudController
{
    use CreateOperation;
    use DeleteOperation;
    use ListOperation;
    use UpdateOperation;

    /**
     * No dedicated "module.*" permission exists in xlr8_iam_permissions — this
     * reuses "rbac.*", the same pair used by RoleCrudController and
     * PermissionCrudController, since Module/Process management is part of the
     * same RBAC-taxonomy screen set.
     */
    public function setup()
    {
        CRUD::setModel(Module::class);

        CRUD::setRoute(
            config('backpack.base.route_prefix').'/iam/module'
        );

        CRUD::setEntityNameStrings(
            'module',
            'modules'
        );
    }

    protected function setupListOperation()
    {
        if (! backpack_user()->can('IAM_RBAC_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view modules.');
        }

        $this->crud->setListView(
            'admin.iam.modules.list'
        );
    }

    public function index()
    {
        if (! backpack_user()->can('IAM_RBAC_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view modules.');
        }

        $this->crud->setListView(
            'admin.iam.modules.list'
        );

        $modules = Module::orderBy('id', 'desc')
            ->get();

        $gridData = $modules->map(function ($module, $index) {

            $mapped = $module->toArray();

            $mapped['serial_no'] =
                $index + 1;

            $mapped['is_active'] =
                $module->is_active
                ? 'Active'
                : 'Inactive';

            $editUrl =
                backpack_url(
                    "iam/module/{$module->id}/edit"
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
            'admin.iam.modules.list',
            [
                'title' => 'All Modules',

                'gridConfig' => [

                    'columns' => [

                        [
                            'field' => 'serial_no',
                            'headerName' => 'S.No.',
                        ],

                        [
                            'field' => 'code',
                            'headerName' => 'Code',
                        ],

                        [
                            'field' => 'name',
                            'headerName' => 'Module Name',
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
            abort(403, 'Unauthorized. You do not have permission to create modules.');
        }

        $this->crud->setCreateView(
            'admin.iam.modules.create'
        );

        return view(
            'admin.iam.modules.create',
            [
                'title' => 'Add New Module',
            ]
        );
    }

    public function store(ModulesRequest $request)
    {
        if (! backpack_user()->can('IAM_RBAC_MANAGE')) {
            abort(403, 'Unauthorized. You do not have permission to create modules.');
        }

        $validated = $request->validated();

        $validated['is_active'] =
            $request->boolean(
                'is_active'
            );

        Module::create($validated);

        \Alert::success(
            'Module created successfully!'
        )->flash();

        return redirect(
            backpack_url('iam/module')
        );
    }

    public function edit($id)
    {
        if (! backpack_user()->can('IAM_RBAC_MANAGE')) {
            abort(403, 'Unauthorized. You do not have permission to edit modules.');
        }

        $this->crud->setEditView(
            'admin.iam.modules.edit'
        );

        $module = Module::findOrFail($id);

        $activeProcesses =
            Process::where(
                'module_code',
                $module->code
            )
                ->where(
                    'is_active',
                    1
                )
                ->pluck('name')
                ->toArray();

        return view(
            'admin.iam.modules.edit',
            [

                'title' => 'Edit Module - '
                    .$module->name,

                'module' => $module,

                'activeProcesses' => $activeProcesses,
            ]
        );
    }

    public function update(
        ModulesRequest $request,
        $id
    ) {
        if (! backpack_user()->can('IAM_RBAC_MANAGE')) {
            abort(403, 'Unauthorized. You do not have permission to edit modules.');
        }

        $module = Module::findOrFail(
            $id
        );

        $validated = $request->validated();

        if (
            $module->is_active == 1 &&
            ! $request->boolean(
                'is_active'
            )
        ) {

            $activeProcessCount =
                Process::where(
                    'module_code',
                    $module->code
                )
                    ->where(
                        'is_active',
                        1
                    )
                    ->count();

            if (
                $activeProcessCount > 0
            ) {

                \Alert::error(
                    "Cannot deactivate Module. {$activeProcessCount} active Process(es) exist."
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

        $module->update(
            $validated
        );

        \Alert::success(
            'Module updated successfully!'
        )->flash();

        return redirect(
            backpack_url('iam/module')
        );
    }

    public function destroy($id)
    {
        if (! backpack_user()->can('IAM_RBAC_MANAGE')) {
            abort(403, 'Unauthorized. You do not have permission to delete modules.');
        }

        return $this->crud->delete($id);
    }
}
