<?php

namespace App\Http\Controllers\Admin;

use App\Models\Admin\Employee;
use App\Services\HR\HRJourneyService;
use App\Exceptions\DomainException;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Illuminate\Http\Request;

class HRRelievingController extends CrudController
{
    protected HRJourneyService $hrService;

    public function __construct(HRJourneyService $hrService)
    {
        parent::__construct();
        $this->hrService = $hrService;
    }

    public function index()
    {
        $employees = Employee::with(['person', 'postAssignments' => fn ($q) =>
                $q->whereNull('to_date')->with('post.designation')
            ])
            ->whereHas('postAssignments', fn ($q) => $q->whereNull('to_date'))
            ->active()
            ->orderBy('code')
            ->get();

        return view('admin.hr.relieve', compact('employees'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'emp_code'        => 'required|string',
            'separation_date' => 'required|date',
            'relieving_type'  => 'required|in:relieving,termination,absconding,resignation',
        ]);

        try {
            $relieved = $this->hrService->separate(
                $validated['emp_code'],
                $validated['separation_date'],
                $validated['relieving_type']
            );

            flash("Employee {$validated['emp_code']} relieved from {$relieved->count()} post(s).")->success();
        } catch (DomainException $e) {
            flash($e->getMessage())->error();
        }

        return redirect(backpack_url('emp-post-assignment'));
    }
}
