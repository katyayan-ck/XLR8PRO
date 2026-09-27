<?php

namespace Tests\Feature\Platform;

use App\Models\User;
use App\Models\Utilities\Task\Task;
use App\Services\Platform\Task\TaskService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Platform\Concerns\PlatformFixtures;
use Tests\TestCase;

/**
 * Task rights matrix (FRS §5.3), inboxes and people rebuild (DEC-062).
 */
class TaskServiceTest extends TestCase
{
    use DatabaseTransactions, PlatformFixtures;

    /** @var array{owner: int, a1: int, a2: int, follower: int, snooper: int} */
    private array $p;

    private TaskService $tasks;

    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();
        $ids = $this->staffWithDesignations(5)->pluck('id')->all();
        $this->p = array_combine(['owner', 'a1', 'a2', 'follower', 'snooper'], $ids);
        $this->tasks = app(TaskService::class);
        $result = $this->tasks->create(['title' => 'Collect PAN', 'type' => 'ASSIGNED_TASK', 'owner_id' => $this->p['owner'],
            'assignees' => [$this->p['a1'], $this->p['a2']], 'followers' => [$this->p['follower']], 'snoopers' => [$this->p['snooper']]], $this->p['owner']);
        $this->task = Task::query()->findOrFail($result->get('id'));
    }

    public function test_assigned_task_without_assignee_is_rejected(): void
    {
        $this->assertSame('ASSIGNEE_REQUIRED', $this->tasks->create(['title' => 'X', 'type' => 'ASSIGNED_TASK'], $this->p['owner'])->code);
    }

    public function test_self_task_assigns_the_owner_whatever_the_client_sends(): void
    {
        $id = $this->tasks->create(['title' => 'Call', 'type' => 'SELF_TASK', 'assignees' => [$this->p['a1']]], $this->p['owner'])->get('id');

        $this->assertSame([$this->p['owner']], Task::find($id)->people()->where('role', 'ASSIGNEE')->pluck('user_id')->all());
    }

    public function test_group_flag_follows_the_assignee_count(): void
    {
        $this->assertTrue($this->task->is_group);

        $this->tasks->update($this->task->id, ['assignees' => [$this->p['a1']]], $this->p['owner']);

        $this->assertFalse($this->task->fresh()->is_group);
    }

    public function test_each_role_sees_the_task_only_in_its_own_inbox(): void
    {
        $this->assertTrue(collect($this->tasks->inbox($this->p['snooper'], 'SNOOPED')->items())->contains('id', $this->task->id));
        $this->assertFalse(collect($this->tasks->inbox($this->p['snooper'], 'ASSIGNED')->items())->contains('id', $this->task->id));
        $this->assertTrue(collect($this->tasks->inbox($this->p['owner'], 'CREATED')->items())->contains('id', $this->task->id));
    }

    public function test_snooper_may_read_but_not_remark(): void
    {
        $this->assertTrue($this->task->chatCanView($this->p['snooper']));
        $this->assertSame('FORBIDDEN', $this->tasks->followUp($this->task->id, $this->p['snooper'], 'hi')->code);
    }

    public function test_rights_matrix_transitions(): void
    {
        $t = $this->task->id;
        $this->assertSame('FORBIDDEN_TRANSITION', $this->tasks->followUp($t, $this->p['follower'], null, 'INPROGRESS')->code);
        $this->assertTrue($this->tasks->followUp($t, $this->p['a1'], 'on it', 'INPROGRESS')->ok);
        $this->assertSame('FORBIDDEN_TRANSITION', $this->tasks->followUp($t, $this->p['owner'], null, 'SUBMITTED')->code);
        $this->assertSame('FORBIDDEN_TRANSITION', $this->tasks->followUp($t, $this->p['a1'], null, 'CLOSED')->code);
        $this->assertTrue($this->tasks->followUp($t, $this->p['a2'], 'done', 'SUBMITTED')->ok);
        $this->assertTrue($this->tasks->followUp($t, $this->p['owner'], null, 'CLOSED')->ok);
        $this->assertTrue($this->tasks->followUp($t, $this->p['owner'], 'redo', 'REOPENED')->ok);
        $this->assertSame('REOPENED', $this->task->fresh()->status);
    }

    public function test_unauthorised_viewer_gets_no_task_data(): void
    {
        $outsider = User::query()->whereNotIn('id', array_values($this->p))->whereNotIn('id', User::role('superadmin')->pluck('id'))
            ->get()->first(fn ($u) => ! $u->can('UTL_TASK_ADMIN'));

        $result = $this->tasks->get($this->task->id, $outsider->id);

        $this->assertSame(['UNAUTHORISED', []], [$result->code, $result->data]);
    }

    public function test_editing_people_removes_dropped_roles_without_orphans(): void
    {
        $this->tasks->update($this->task->id, ['assignees' => [$this->p['a1']], 'followers' => [$this->p['a2']], 'snoopers' => []], $this->p['owner']);

        $this->assertSame(['FOLLOWER'], $this->task->people()->where('user_id', $this->p['a2'])->pluck('role')->all());
        $this->assertSame(3, $this->task->people()->count());
    }
}
