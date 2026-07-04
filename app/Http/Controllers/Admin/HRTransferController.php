<?php

namespace App\Http\Controllers\Admin;

use App\Models\Admin\Employee;
use App\Models\IAM\Post;
use App\Services\HR\HRJourneyService;
use App\Exceptions\DomainException;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Illuminate\Http\Request;

class HRTransferController extends CrudController
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
            ->whereHas('postAssignments', fn ($q) =>
                $q->where('assignment_type', 'primary')->whereNull('to_date')
            )
            ->active()
            ->orderBy('code')
            ->get();

        return view('admin.hr.transfer', compact('employees'));
    }

    public function getPosts(Request $request)
    {
        $posts = Post::withoutGlobalScopes()
            ->withCount('currentEmployees')
            ->active()
            ->get()
            ->filter(fn ($p) => $p->isVacant())
            ->map(fn ($p) => [
                'post_code'   => $p->post_code,
                'label'       => $p->post_code . ' — ' . ($p->designation?->name ?? ''),
                'branch'      => $p->branch?->name ?? '—',
                'vacancy'     => $p->vacancyCount(),
            ])->values();

        return response()->json($posts);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'emp_code'       => 'required|string',
            'new_post_code'  => 'required|string',
            'transfer_date'  => 'required|date',
        ]);

        try {
            $result = $this->hrService->transfer(
                $validated['emp_code'],
                $validated['new_post_code'],
                $validated['transfer_date']
            );

            flash("Transfer complete. Relieved from {$result['relieved']->post_code}, assigned to {$result['assigned']->post_code}.")->success();
        } catch (DomainException $e) {
            flash($e->getMessage())->error();
        }

        return redirect(backpack_url('emp-post-assignment'));
    }
}
