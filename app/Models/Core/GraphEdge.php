<?php

namespace App\Models\Core;

use App\Models\BaseModel;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GraphEdge extends BaseModel
{
    use CrudTrait;
    use HasFactory;
    protected $fillable = ['from_node_id', 'to_node_id', 'type', 'level', 'powers'];

    protected $casts = ['powers' => 'array'];

    public function fromNode()
    {
        return $this->belongsTo(GraphNode::class, 'from_node_id');
    }

    public function toNode()
    {
        return $this->belongsTo(GraphNode::class, 'to_node_id');
    }

    // Match topic/combo
    public function matches(string $topic, array $queryCombo): bool
    {
        if ($this->powers['topic'] ?? '' !== $topic) return false;
        $edgeCombo = $this->powers['combo_json'] ?? [];
        foreach ($queryCombo as $k => $v) {
            if (($edgeCombo[$k] ?? null) !== $v && ($edgeCombo[$k] ?? null) !== null) return false;
        }
        return true;
    }
}
