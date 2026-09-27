<?php

namespace App\Models\Utilities\Task;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A person's role on a task: OWNER, ASSIGNEE, FOLLOWER or SNOOPER.
 *
 * @property int $id
 * @property int $task_id
 * @property int $user_id
 * @property string $role
 * @property-read ?User $user
 */
class TaskPerson extends Model
{
    protected $table = 'xlr8_utils_task_person';

    protected $fillable = ['task_id', 'user_id', 'role'];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
