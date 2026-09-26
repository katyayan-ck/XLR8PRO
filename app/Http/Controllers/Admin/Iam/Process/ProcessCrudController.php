<?php

namespace App\Http\Controllers\Admin\Iam\Process;

use App\Http\Requests\ProcessRequest;
use App\Models\IAM\Process;
use App\Services\IAM\RbacService;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Exception;

class ProcessCrudController extends CrudController
{
    use CreateOperation;
    use DeleteOperation;
    use ListOperation;
    use UpdateOperation;

    public function setup()
    {
        CRUD::setModel(Process::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/iam/process');
        CRUD::setEntityNameStrings('process', 'processes');
    }

    protected function setupListOperation()
    {
        if (! backpack_user()->can('IAM_RBAC_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view processes.');
        }

        $this->crud->setListView('admin.iam.process.list');
    }

    public function index()
    {
        if (! backpack_user()->can('IAM_RBAC_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view processes.');
        }

        $this->crud->setListView('admin.iam.process.list');

        $processes = Process::with('module')->orderBy('id', 'desc')->get();

        $gridData = $processes->map(function ($process, $index) {
            $mapped = $process->toArray();
            $mapped['serial_no'] = $index + 1;
            $mapped['module_name'] = $process->module?->name ?? '—';
            $mapped['is_active'] = $process->is_active ? 'Active' : 'Inactive';
            $editUrl = backpack_url("iam/process/{$process->id}/edit");
            $mapped['action'] = '
                <div class="d-flex gap-2 justify-content-center">
                    <a href="'.$editUrl.'" class="btn btn-sm btn-primary py-1 px-2">Edit</a>
                </div>
            ';
            return $mapped;
        })->values();

        return view('admin.iam.process.list', [
            'title' => 'All Processes',
            'gridConfig' => [
                'columns' => [
                    ['field' => 'serial_no', 'headerName' => 'S.No.'],
                    ['field' => 'module_name', 'headerName' => 'Module'],
                    ['field' => 'code', 'headerName' => 'Code'],
                    ['field' => 'name', 'headerName' => 'Process Name'],
                    ['field' => 'description', 'headerName' => 'Description'],
                    ['field' => 'is_active', 'headerName' => 'Active'],
                    ['field' => 'action', 'headerName' => 'Actions'],
                ],
                'data' => $gridData,
            ],
        ]);
    }

    public function create(RbacService $rbacService)
    {
        if (! backpack_user()->can('IAM_RBAC_MANAGE')) {
            abort(403, 'Unauthorized. You do not have permission to create processes.');
        }

        $this->crud->setCreateView('admin.iam.process.form');

        return view('admin.iam.process.form', [
            'title' => 'Add New Process',
            'modules' => $rbacService->getActiveModules(),
            'activePermissions' => [], // Empty array for create mode
        ]);
    }

    public function edit($id, RbacService $rbacService)
    {
        if (! backpack_user()->can('IAM_RBAC_MANAGE')) {
            abort(403, 'Unauthorized. You do not have permission to edit processes.');
        }

        $this->crud->setEditView('admin.iam.process.form');

        $process = Process::findOrFail($id);

        return view('admin.iam.process.form', [
            'title' => 'Edit Process - ' . $process->name,
            'process' => $process,
            'modules' => $rbacService->getActiveModules(),
            'activePermissions' => $rbacService->getPermissionNamesByProcess($process->code),
        ]);
    }

    public function store(ProcessRequest $request)
    {
        if (! backpack_user()->can('IAM_RBAC_MANAGE')) {
            abort(403, 'Unauthorized. You do not have permission to create processes.');
        }

        $validated = $request->validated();
        $validated['is_active'] = $request->boolean('is_active');

        Process::create($validated);

        \Alert::success('Process created successfully!')->flash();

        return redirect(backpack_url('iam/process'));
    }

    public function update(ProcessRequest $request, $id, RbacService $rbacService)
    {
        if (! backpack_user()->can('IAM_RBAC_MANAGE')) {
            abort(403, 'Unauthorized. You do not have permission to edit processes.');
        }

        $process = Process::findOrFail($id);
        
        $validated = $request->validated();
        $validated['is_active'] = $request->boolean('is_active');

        try {
            $rbacService->updateProcess($process, $validated);
        } catch (Exception $e) {
            \Alert::error($e->getMessage())->flash();
            return redirect()->back()->withInput();
        }

        \Alert::success('Process updated successfully!')->flash();

        return redirect(backpack_url('iam/process'));
    }

    public function destroy($id)
    {
        if (! backpack_user()->can('IAM_RBAC_MANAGE')) {
            abort(403, 'Unauthorized. You do not have permission to delete processes.');
        }

        return $this->crud->delete($id);
    }
}