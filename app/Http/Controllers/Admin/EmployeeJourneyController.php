<?php

namespace App\Http\Controllers\Admin;

use App\Models\Admin\Employee;
use App\Services\HR\HRJourneyService;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Illuminate\Http\Request;

class EmployeeJourneyController extends CrudController
{
    protected HRJourneyService $hrService;

    public function __construct(HRJourneyService $hrService)
    {
        parent::__construct();
        $this->hrService = $hrService;
    }

    public function index()
    {
        $employees = Employee::with('person')
            ->active()
            ->orderBy('code')
            ->get();

        return view('admin.hr.journey-select', compact('employees'));
    }

    public function show(string $empCode)
    {
        $employee = Employee::with(['person', 'designation', 'primaryBranch'])
            ->where('code', $empCode)
            ->firstOrFail();

        $journey = $this->hrService->getJourney($empCode)
            ->map(fn ($a) => [
                'post_code'       => $a->post_code,
                'designation'     => $a->post?->designation?->name ?? '—',
                'branch'          => $a->post?->branch?->name ?? '—',
                'assignment_type' => $a->assignment_type,
                'relieving_type'  => $a->relieving_type,
                'from_date'       => $a->from_date?->format('d-M-Y'),
                'to_date'         => $a->to_date?->format('d-M-Y') ?? 'Current',
                'duration'        => $a->from_date?->diffForHumans($a->to_date ?? now(), true),
            ]);

        return view('admin.hr.journey', compact('employee', 'journey'));
    }
}
