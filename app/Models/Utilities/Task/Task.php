<?php

namespace App\Models\Utilities\Task;

use App\Models\BaseModel;
use App\Models\Traits\HasCommunications;
use App\Models\Traits\HasDocuments;
use App\Models\User;
use App\Services\Platform\Task\TaskService;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A work item (FRS §5). Written only by TaskService; people and their roles live in task_person.
 *
 * @property int $id
 * @property string $title
 * @property string $type
 * @property ?string $priority
 * @property string $status
 * @property int $owner_id
 * @property ?string $details
 * @property ?\Illuminate\Support\Carbon $deadline
 * @property ?string $ref_type
 * @property ?int $ref_id
 * @property bool $is_group
 * @property ?\Illuminate\Support\Carbon $submitted_at
 * @property ?\Illuminate\Support\Carbon $closed_at
 * @property ?\Illuminate\Support\Carbon $created_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, TaskPerson> $people
 */
class Task extends BaseModel
{
    use HasCommunications, HasDocuments;

    protected $table = 'xlr8_utils_task';

    protected $fillable = [
        'title', 'type', 'priority', 'status', 'owner_id', 'details', 'deadline',
        'ref_type', 'ref_id', 'is_group', 'submitted_at', 'closed_at',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'deadline' => 'datetime',
            'submitted_at' => 'datetime',
            'closed_at' => 'datetime',
            'is_group' => 'boolean',
        ]);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return HasMany<TaskPerson, $this> */
    public function people(): HasMany
    {
        return $this->hasMany(TaskPerson::class, 'task_id');
    }

    /** Anyone associated with the task (or a task admin) sees its conversation. */
    public function chatCanView(int $userId): bool
    {
        return app(TaskService::class)->role($this, $userId) !== null || (bool) User::query()->find($userId)?->can('UTL_TASK_ADMIN');
    }

    /** Snoopers read silently (rights matrix, FRS §5.3). */
    public function chatCanRemark(int $userId): bool
    {
        return in_array('remark', app(TaskService::class)->rights($this, $userId), true);
    }

    protected function getCommMasterTitle(): string
    {
        return "Task #{$this->id}: {$this->title}";
    }
}
