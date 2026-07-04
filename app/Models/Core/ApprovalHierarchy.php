<?php

namespace App\Models\Core;

use App\Models\BaseModel;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;

class ApprovalHierarchy extends BaseModel
{
    use CrudTrait;
    use HasFactory;

    protected $table = 'xlr8_approval_hierarchies';
    protected $fillable = [
        'approver_id',
        'level',
        'topic',
        'combo_json',
        'powers_json',
        'is_active'
    ];

    protected $casts = [
        'combo_json' => 'array',
        'powers_json' => 'array',
        'level' => 'integer',
        'is_active' => 'boolean',
    ];

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    // Link to graph node
    public function graphNode()
    {
        return $this->morphOne(GraphNode::class, 'nodeable');
    }

    // Initiate approval
    public function initiate(array $data): bool
    {
        // Validate combo/powers
        if (!$this->matchesCombo($data['combo'])) {
            return false;
        }
        // Create graph path
        $this->buildGraphPath();
        // Notify approver
        return true;
    }

    // Check combo match
    protected function matchesCombo(array $queryCombo): bool
    {
        $combo = $this->combo_json ?? [];
        foreach ($queryCombo as $key => $value) {
            if (($combo[$key] ?? null) !== $value && ($combo[$key] ?? null) !== null) {
                return false;
            }
        }
        return true;
    }

    // Build graph for levels
    protected function buildGraphPath()
    {
        $start = GraphNode::create(['user_id' => auth()->id(), 'role' => 'initiator', 'attributes' => ['topic' => $this->topic, 'combo_json' => $this->combo_json]]);
        $current = $start;
        for ($i = 1; $i <= $this->level; $i++) {
            $next = GraphNode::create(['user_id' => $this->approver_id, 'role' => "level_$i", 'attributes' => $this->powers_json]);
            GraphEdge::create(['from_node_id' => $current->id, 'to_node_id' => $next->id, 'type' => 'approval', 'level' => $i, 'powers' => $this->powers_json]);
            $current = $next;
        }
    }
}
