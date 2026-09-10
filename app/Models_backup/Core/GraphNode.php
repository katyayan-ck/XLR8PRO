<?php

namespace App\Models\Core;

use App\Models\BaseModel;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;

class GraphNode extends BaseModel
{
    use CrudTrait;
    use HasFactory;
    protected $fillable = ['user_id', 'role', 'attributes'];

    protected $casts = ['attributes' => 'array'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function outgoingEdges()
    {
        return $this->hasMany(GraphEdge::class, 'from_node_id');
    }

    public function incomingEdges()
    {
        return $this->hasMany(GraphEdge::class, 'to_node_id');
    }

    // Polymorphic for ApprovalHierarchy
    public function nodeable()
    {
        return $this->morphTo();
    }

    // Get subtree for topic/combo
    public function getSubtree(string $topic, array $combo): array
    {
        $subtree = [$this];
        foreach ($this->outgoingEdges as $edge) {
            if ($edge->matches($topic, $combo)) {
                $subtree = array_merge($subtree, $edge->toNode->getSubtree($topic, $combo));
            }
        }
        return $subtree;
    }

    // Get subtree user IDs
    public function getSubtreeUserIds(string $topic, array $combo): array
    {
        return collect($this->getSubtree($topic, $combo))->pluck('user_id')->unique()->toArray();
    }
}
