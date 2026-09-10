<?php

namespace App\Http\Controllers\Admin;

use App\Models\Admin\DesigDeptTree;
use App\Models\Admin\Designation;
use App\Models\Admin\Department;
use App\Models\Admin\Division;
use App\Models\Admin\Branch;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Http\Request;

class DesigDeptTreeCrudController extends CrudController
{
    public function setup(): void
    {
        CRUD::setModel(DesigDeptTree::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/desig-dept-tree');
        CRUD::setEntityNameStrings('designation tree entry', 'designation tree');
    }

    protected function setupListOperation(): void
    {
        $this->crud->setListView('admin.desig-dept-tree.list');
    }

    public function index()
    {
        $this->crud->hasAccessOrFail('list');

        $entries = DesigDeptTree::with(['designation', 'department', 'division', 'branch', 'parent'])
            ->orderBy('branch_code')
            ->orderBy('dept_code')
            ->orderBy('hierarchy_level')
            ->get();

        $gridData = $entries->map(function ($entry, $index) {
            return [
                'id'              => $entry->id,
                'serial_no'       => $index + 1,
                'desig_code'      => $entry->desig_code,
                'designation'     => $entry->designation?->name ?? '—',
                'branch'          => $entry->branch?->name ?? '—',
                'department'      => $entry->department?->name ?? '—',
                'division'        => $entry->division?->name ?? '—',
                'hierarchy_level' => $entry->hierarchy_level,
                'rank'            => $entry->rank,
                'parent_desig'    => $entry->parent?->designation?->name ?? '—',
                'is_top_mgmt'     => $entry->is_top_mgmt,
                'is_active'       => $entry->is_active,
            ];
        })->values();

        return view('admin.desig-dept-tree.list', compact('gridData'));
    }

    public function create()
    {
        $this->crud->hasAccessOrFail('create');
        $this->crud->setCreateView('admin.desig-dept-tree.create');

        $designations = Designation::active()->orderBy('name')->get();
        $departments  = Department::orderBy('name')->get();
        $divisions    = Division::orderBy('name')->get();
        $branches     = Branch::orderBy('name')->get();
        $parents      = DesigDeptTree::with('designation')->orderBy('hierarchy_level')->get();

        return view('admin.desig-dept-tree.create', compact(
            'designations', 'departments', 'divisions', 'branches', 'parents'
        ));
    }

    public function store(Request $request)
    {
        $this->crud->hasAccessOrFail('create');

        $validated = $request->validate([
            'desig_code'      => 'required|string|exists:xlr8_admin_designation,code',
            'branch_code'     => 'nullable|string|exists:xlr8_admin_branch,code',
            'dept_code'       => 'nullable|string|exists:xlr8_admin_department,code',
            'div_code'        => 'nullable|string|exists:xlr8_admin_division,code',
            'hierarchy_level' => 'required|integer|min:0',
            'rank'            => 'integer|min:0',
            'parent_id'       => 'nullable|exists:xlr8_admin_desig_dept_tree,id',
            'is_top_mgmt'     => 'boolean',
            'is_active'       => 'boolean',
        ]);

        DesigDeptTree::create(array_merge($validated, [
            'created_by' => backpack_auth()->id(),
        ]));

        flash('Designation tree entry created successfully!')->success();
        return redirect(backpack_url('desig-dept-tree'));
    }

    public function edit($id)
    {
        $this->crud->hasAccessOrFail('update');
        $this->crud->setEditView('admin.desig-dept-tree.edit');

        $entry        = DesigDeptTree::findOrFail($id);
        $designations = Designation::active()->orderBy('name')->get();
        $departments  = Department::orderBy('name')->get();
        $divisions    = Division::orderBy('name')->get();
        $branches     = Branch::orderBy('name')->get();
        $parents      = DesigDeptTree::with('designation')
                            ->where('id', '!=', $id)
                            ->orderBy('hierarchy_level')
                            ->get();

        return view('admin.desig-dept-tree.edit', compact(
            'entry', 'designations', 'departments', 'divisions', 'branches', 'parents'
        ));
    }

    public function update(Request $request, $id)
    {
        $this->crud->hasAccessOrFail('update');

        $entry = DesigDeptTree::findOrFail($id);

        $validated = $request->validate([
            'desig_code'      => 'required|string|exists:xlr8_admin_designation,code',
            'branch_code'     => 'nullable|string|exists:xlr8_admin_branch,code',
            'dept_code'       => 'nullable|string|exists:xlr8_admin_department,code',
            'div_code'        => 'nullable|string|exists:xlr8_admin_division,code',
            'hierarchy_level' => 'required|integer|min:0',
            'rank'            => 'integer|min:0',
            'parent_id'       => 'nullable|exists:xlr8_admin_desig_dept_tree,id',
            'is_top_mgmt'     => 'boolean',
            'is_active'       => 'boolean',
        ]);

        $entry->update(array_merge($validated, ['updated_by' => backpack_auth()->id()]));

        flash('Designation tree entry updated successfully!')->success();
        return redirect(backpack_url('desig-dept-tree'));
    }
}
