<?php

namespace App\Http\Controllers\Admin\Utils\Platform;

use App\Http\Controllers\Controller;
use App\Models\Utilities\Task\Task;
use App\Services\KeywordValueService;
use App\Services\OrgService;
use App\Services\Platform\Task\TaskService;
use App\Support\Result;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Prologue\Alerts\Facades\Alert;

/**
 * Task screens (FRS §5, TSK-09): four inboxes, create / edit, view with follow-up. Access to a
 * single task follows its people (TaskService::rights), not a screen permission.
 */
class TaskController extends Controller
{
    public function __construct(private readonly TaskService $tasks) {}

    public function index(Request $request): View
    {
        if (! backpack_user()->can('UTL_TASK_VIEW')) {
            abort(403);
        }
        $box = array_key_exists(strtoupper((string) $request->query('box')), TaskService::BOXES) ? strtoupper($request->query('box')) : 'ASSIGNED';

        return view('admin.utils.platform.tasks.index', [
            'title' => 'Tasks',
            'box' => $box,
            'filters' => $request->only(['status', 'q']),
            'rows' => $this->tasks->inbox(backpack_user()->id, $box, $request->only(['status', 'q'])),
            'counts' => $this->tasks->inboxCounts(backpack_user()->id),
        ]);
    }

    public function create(Request $request): View
    {
        if (! backpack_user()->can('UTL_TASK_CREATE')) {
            abort(403);
        }

        return view('admin.utils.platform.tasks.form', $this->formData() + [
            'title' => 'New task',
            'task' => null,
            'ref' => ['type' => $request->query('ref_type'), 'id' => $request->query('ref_id')],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (! backpack_user()->can('UTL_TASK_CREATE')) {
            abort(403);
        }
        $result = $this->tasks->create($this->payload($request));
        if (! $result->ok) {
            return $this->failed($result);
        }
        Alert::success('Task created.')->flash();

        return redirect()->route('utils.tasks.show', $result->get('id'));
    }

    public function show(int $id): View
    {
        $result = $this->tasks->get($id, backpack_user()->id);
        abort_unless($result->ok, 403, 'You cannot see this task.');

        return view('admin.utils.platform.tasks.show', [
            'title' => 'Task #'.$id,
            'task' => $result->data,
            'model' => Task::query()->findOrFail($id),
        ]);
    }

    public function edit(int $id): View
    {
        $result = $this->tasks->get($id, backpack_user()->id);
        abort_unless($result->ok && in_array('edit', $result->get('user_can'), true), 403, 'Only the owner may edit this task.');

        return view('admin.utils.platform.tasks.form', $this->formData() + ['title' => 'Edit task #'.$id, 'task' => $result->data, 'ref' => null]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $result = $this->tasks->update($id, $this->payload($request));
        if (! $result->ok) {
            return $this->failed($result);
        }
        Alert::success('Task updated.')->flash();

        return redirect()->route('utils.tasks.show', $id);
    }

    public function followUp(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'remark' => 'nullable|string|max:5000',
            'status' => 'nullable|string|max:20',
            'file' => 'nullable|file',
        ]);
        $result = $this->tasks->followUp($id, backpack_user()->id, $data['remark'] ?? null, $data['status'] ?? null, $request->file('file'));
        $result->ok ? Alert::success('Follow-up saved.')->flash() : Alert::error($result->message)->flash();

        return back();
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $result = $this->tasks->delete($id, backpack_user()->id, $request->boolean('confirm'));
        if (! $result->ok) {
            Alert::error($result->message)->flash();

            return back();
        }
        Alert::success('Task deleted.')->flash();

        return redirect()->route('utils.tasks.index', ['box' => 'CREATED']);
    }

    /** @return array<string, mixed> */
    private function payload(Request $request): array
    {
        $data = $request->validate([
            'title' => 'required|string|max:250',
            'type' => 'required|string|max:50',
            'priority' => 'nullable|string|max:50',
            'assignees' => 'nullable|array',
            'assignees.*' => 'integer',
            'followers' => 'nullable|array',
            'followers.*' => 'integer',
            'snoopers' => 'nullable|array',
            'snoopers.*' => 'integer',
            'details' => 'nullable|string|max:20000',
            'deadline' => 'nullable|date',
            'ref_type' => 'nullable|string|max:30',
            'ref_id' => 'nullable|integer',
        ]);

        return $data + ['assignees' => [], 'followers' => [], 'snoopers' => []];
    }

    /** @return array<string, mixed> */
    private function formData(): array
    {
        return [
            'types' => KeywordValueService::getEnum('TASK_TYPE'),
            'priorities' => KeywordValueService::getEnum('TASK_PRIORITY'),
            'team' => OrgService::teamOptions(),
        ];
    }

    private function failed(Result $result): RedirectResponse
    {
        Alert::error($result->message)->flash();

        return back()->withInput();
    }
}
