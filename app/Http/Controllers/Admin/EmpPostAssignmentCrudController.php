<?php

namespace App\Http\Controllers\Admin;

use App\Models\Admin\EmpPostAssignment;
use App\Models\Admin\Employee;
use App\Models\IAM\Post;
use App\Services\HR\HRJourneyService;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Http\Request;

class EmpPostAssignmentCrudController extends CrudController
{
    protected HRJourneyService $hrService;

    public function __construct(HRJourneyService $hrService)
    {
        parent::__construct();
        $this->hrService = $hrService;
    }

    public function setup(): void
    {
        CRUD::setModel(EmpPostAssignment::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/emp-post-assignment');
        CRUD::setEntityNameStrings('post assignment', 'post assignments');
    }

    protected function setupListOperation(): void
    {
        $this->crud->setListView('admin.emp-post-assignment.list');
    }

    public function index()
    {
        $this->crud->hasAccessOrFail('list');

        $assignments = EmpPostAssignment::with(['post.designation', 'post.branch'])
            ->orderBy('from_date', 'desc')
            ->get();

        $gridData = $assignments->map(function ($a, $index) {
            return [
                'id'              => $a->id,
                'serial_no'       => $index + 1,
                'emp_code'        => $a->emp_code,
                'post_code'       => $a->post_code,
                'designation'     => $a->post?->designation?->name ?? '—',
                'branch'          => $a->post?->branch?->name ?? '—',
                'assignment_type' => $a->assignment_type,
                'relieving_type'  => $a->relieving_type,
                'from_date'       => $a->from_date?->format('d-M-Y'),
                'to_date'         => $a->to_date?->format('d-M-Y') ?? 'Active',
                'is_active'       => $a->isActive(),
            ];
        })->values();

        return view('admin.emp-post-assignment.list', compact('gridData'));
    }

    public function create()
    {
        $this->crud->hasAccessOrFail('create');
        $this->crud->setCreateView('admin.emp-post-assignment.create');

        // Only show employees without a current primary assignment (eligible for onboarding)
        $employees = Employee::with('person')
            ->whereDoesntHave('postAssignments', fn ($q) =>
                $q->where('assignment_type', 'primary')->whereNull('to_date')
            )
            ->active()
            ->orderBy('code')
            ->get();

        // Only vacant posts
        $posts = Post::withoutGlobalScopes()
            ->withCount('currentEmployees')
            ->active()
            ->get()
            ->filter(fn ($p) => $p->isVacant());

        return view('admin.emp-post-assignment.create', compact('employees', 'posts'));
    }

    public function store(Request $request)
    {
        $this->crud->hasAccessOrFail('create');

        $validated = $request->validate([
            'emp_code'  => 'required|string',
            'post_code' => 'required|string',
            'from_date' => 'required|date',
        ]);

        $this->hrService->onboard(
            $validated['emp_code'],
            $validated['post_code'],
            $validated['from_date']
        );

        flash('Employee onboarded to post successfully!')->success();
        return redirect(backpack_url('emp-post-assignment'));
    }

    public function edit($id)
    {
        $this->crud->hasAccessOrFail('update');
        $this->crud->setEditView('admin.emp-post-assignment.edit');

        $assignment = EmpPostAssignment::with('post')->findOrFail($id);

        return view('admin.emp-post-assignment.edit', compact('assignment'));
    }

    public function update(Request $request, $id)
    {
        $this->crud->hasAccessOrFail('update');

        $assignment = EmpPostAssignment::findOrFail($id);

        $validated = $request->validate([
            'to_date'        => 'nullable|date|after_or_equal:' . $assignment->from_date,
            'relieving_type' => 'nullable|string|in:transfer,relieving,separation,termination',
        ]);

        $assignment->update(array_merge($validated, ['updated_by' => backpack_auth()->id()]));

        flash('Assignment updated successfully!')->success();
        return redirect(backpack_url('emp-post-assignment'));
    }
}
