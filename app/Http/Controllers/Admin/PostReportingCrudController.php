<?php

namespace App\Http\Controllers\Admin;

use App\Models\IAM\PostReporting;
use App\Models\IAM\Post;
use App\Services\IAM\ReportingService;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Http\Request;

class PostReportingCrudController extends CrudController
{
    protected ReportingService $reportingService;

    public function __construct(ReportingService $reportingService)
    {
        parent::__construct();
        $this->reportingService = $reportingService;
    }

    public function setup(): void
    {
        CRUD::setModel(PostReporting::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/post-reporting');
        CRUD::setEntityNameStrings('reporting line', 'reporting lines');
    }

    protected function setupListOperation(): void
    {
        $this->crud->setListView('admin.post-reporting.list');
    }

    public function index()
    {
        $this->crud->hasAccessOrFail('list');

        $lines = PostReporting::with(['post', 'reportsToPost'])
            ->orderBy('from_date', 'desc')
            ->get();

        $gridData = $lines->map(function ($line, $index) {
            return [
                'id'                => $line->id,
                'serial_no'         => $index + 1,
                'post_code'         => $line->post_code,
                'reports_to_code'   => $line->reports_to_post_code,
                'topic'             => $line->topic,
                'param_type'        => $line->param_type,
                'param_value'       => $line->param_value ?? '*',
                'priority'          => $line->priority,
                'from_date'         => $line->from_date?->format('d-M-Y'),
                'to_date'           => $line->to_date?->format('d-M-Y') ?? 'Active',
                'is_active'         => $line->to_date === null,
            ];
        })->values();

        return view('admin.post-reporting.list', compact('gridData'));
    }

    public function create()
    {
        $this->crud->hasAccessOrFail('create');
        $this->crud->setCreateView('admin.post-reporting.create');

        $posts = Post::withoutGlobalScopes()
            ->with('designation')
            ->active()
            ->orderBy('post_code')
            ->get();

        return view('admin.post-reporting.create', compact('posts'));
    }

    public function store(Request $request)
    {
        $this->crud->hasAccessOrFail('create');

        $validated = $request->validate([
            'post_code'           => 'required|string',
            'reports_to_post_code'=> 'required|string|different:post_code',
            'topic'               => 'required|string|max:50',
            'param_type'          => 'nullable|string|max:30',
            'param_value'         => 'nullable|string|max:30',
            'priority'            => 'integer|min:1|max:100',
            'from_date'           => 'required|date',
        ]);

        $this->reportingService->setReportingLine(
            postCode:          $validated['post_code'],
            reportsToPostCode: $validated['reports_to_post_code'],
            topic:             $validated['topic'],
            fromDate:          $validated['from_date'],
            paramType:         $validated['param_type'] ?? null,
            paramValue:        $validated['param_value'] ?? null,
            priority:          $validated['priority'] ?? 10,
        );

        flash('Reporting line created successfully!')->success();
        return redirect(backpack_url('post-reporting'));
    }

    public function edit($id)
    {
        $this->crud->hasAccessOrFail('update');
        $this->crud->setEditView('admin.post-reporting.edit');

        $line  = PostReporting::findOrFail($id);
        $posts = Post::withoutGlobalScopes()
            ->with('designation')
            ->active()
            ->orderBy('post_code')
            ->get();

        return view('admin.post-reporting.edit', compact('line', 'posts'));
    }

    public function update(Request $request, $id)
    {
        $this->crud->hasAccessOrFail('update');

        $line      = PostReporting::findOrFail($id);
        $validated = $request->validate([
            'to_date'  => 'nullable|date|after_or_equal:' . $line->from_date,
            'priority' => 'integer|min:1|max:100',
        ]);

        $line->update(array_merge($validated, ['updated_by' => backpack_auth()->id()]));

        flash('Reporting line updated successfully!')->success();
        return redirect(backpack_url('post-reporting'));
    }
}
