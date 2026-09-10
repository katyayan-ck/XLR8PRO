<?php

namespace App\Models\Traits;

use Graphp\Graph\Graph;
use Graphp\Algorithms\BreadthFirstSearch;
use Illuminate\Support\Facades\Cache;
use App\Models\User;

trait GraphTraversalTrait
{
    public function traverseForUser(User $user, ?string $type = null, ?string $attribute = null): array
    {
        if (!config('app.rbac_graph')) {
            return [];
        }

        $cacheKey = "user_graph_{$user->id}_{$type}_{$attribute}";
        return Cache::remember($cacheKey, 60, function () use ($user, $type, $attribute) {
            $graph = new Graph();
            $nodes = \App\Models\Core\GraphNode::with('outgoingEdges', 'incomingEdges')->get();
            foreach ($nodes as $node) {
                $vertex = $graph->createVertex(['id' => $node->id, 'attributes' => json_decode($node->attributes, true)]);
                foreach ($node->outgoingEdges as $edge) {
                    $toVertex = $graph->getVertex($edge->to_node_id);
                    $graph->createEdgeDirected($vertex, $toVertex, ['type' => $edge->type, 'level' => $edge->level]);
                }
            }

            $start = $graph->getVertex($user->graphNode->id);
            $algo = new BreadthFirstSearch($start);
            $visited = $algo->getVertices();

            $result = [];
            foreach ($visited as $vertex) {
                $attrs = $vertex->getAttribute('attributes');
                if ($attribute) {
                    $result = array_merge($result, $attrs[$attribute] ?? []);
                } else if ($type && $vertex->hasEdgesOutWithAttribute('type', $type)) {
                    $result[] = $vertex->getId();
                }
            }

            return array_unique($result);
        });
    }
}
