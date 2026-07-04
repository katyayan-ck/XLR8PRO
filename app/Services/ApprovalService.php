<?php

namespace App\Services;

use App\Models\ApprovalHierarchy;
use App\Models\GraphNode;
use App\Models\GraphEdge;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * ApprovalService - Approval Workflow Management Service
 * 
 * Handles complex approval hierarchies, workflow traversal,
 * and approval chain management with topic-specific rules.
 * 
 * Topics: booking, sale, quote, expense, etc.
 * 
 * @package App\Services
 */
class ApprovalService
{
    /**
     * Initialize approval workflow for a document
     * 
     * @param string $topic Topic/document type (e.g., 'booking', 'sale')
     * @param array $combo Combo filter (e.g., ['brand_id' => 1, 'segment_id' => 2])
     * @param User $initiator User initiating the approval
     * @return GraphNode Root node of approval chain
     */
    public function initializeApproval(
        string $topic,
        array $combo,
        User $initiator
    ): GraphNode {
        // Get all active approval hierarchies for topic, ordered by level
        $hierarchies = ApprovalHierarchy::where('topic', $topic)
            ->where('is_active', true)
            ->orderBy('level')
            ->get();

        if ($hierarchies->isEmpty()) {
            throw new \Exception("No approval hierarchy found for topic: {$topic}");
        }

        // Create initiator node
        $previousNode = GraphNode::create([
            'user_id' => $initiator->id,
            'role' => 'initiator',
            'attributes' => [
                'topic' => $topic,
                'combo' => $combo,
                'status' => 'initiated',
                'initiated_at' => now()->toIso8601String(),
            ],
        ]);

        // Create approval chain
        foreach ($hierarchies as $hierarchy) {
            // Validate combo matches hierarchy requirements
            if (!$this->comboMatches($combo, $hierarchy->combo_json)) {
                continue; // Skip this approval level if combo doesn't match
            }

            // Create approver node
            $approverNode = GraphNode::create([
                'user_id' => $hierarchy->approver_id,
                'role' => "approver_level_{$hierarchy->level}",
                'attributes' => [
                    'topic' => $topic,
                    'combo' => $combo,
                    'level' => $hierarchy->level,
                    'status' => 'pending',
                ],
            ]);

            // Create edge from previous to current
            GraphEdge::create([
                'from_node_id' => $previousNode->id,
                'to_node_id' => $approverNode->id,
                'type' => 'approval_next',
                'level' => $hierarchy->level,
                'powers' => $hierarchy->powers_json,
            ]);

            $previousNode = $approverNode;
        }

        return $previousNode;
    }

    /**
     * Get next approver in chain
     * 
     * @param GraphNode $currentNode
     * @return GraphNode|null
     */
    public function getNextApprover(GraphNode $currentNode): ?GraphNode
    {
        $nextEdge = $currentNode->outgoingEdges()->first();

        if (!$nextEdge) {
            return null; // No more approvers
        }

        return $nextEdge->toNode;
    }

    /**
     * Approve document at current level
     * 
     * @param GraphNode $approvalNode
     * @param User $approver
     * @param string $note Optional approval note
     * @return bool
     */
    public function approveDocument(
        GraphNode $approvalNode,
        User $approver,
        string $note = ''
    ): bool {
        // Verify approver has authority
        if ($approvalNode->user_id !== $approver->id) {
            throw new \Exception('User is not the approver for this level');
        }

        // Update node status
        $attributes = $approvalNode->attributes ?? [];
        $attributes['status'] = 'approved';
        $attributes['approved_at'] = now()->toIso8601String();
        $attributes['approved_by'] = $approver->id;
        if ($note) {
            $attributes['note'] = $note;
        }

        $approvalNode->update(['attributes' => $attributes]);

        return true;
    }

    /**
     * Reject document and return to previous level
     * 
     * @param GraphNode $approvalNode
     * @param User $rejecter
     * @param string $reason Rejection reason
     * @return bool
     */
    public function rejectDocument(
        GraphNode $approvalNode,
        User $rejecter,
        string $reason
    ): bool {
        if ($approvalNode->user_id !== $rejecter->id) {
            throw new \Exception('User is not authorized to reject at this level');
        }

        $attributes = $approvalNode->attributes ?? [];
        $attributes['status'] = 'rejected';
        $attributes['rejected_at'] = now()->toIso8601String();
        $attributes['rejected_by'] = $rejecter->id;
        $attributes['rejection_reason'] = $reason;

        $approvalNode->update(['attributes' => $attributes]);

        // Mark all downstream nodes as cancelled
        $this->cancelDownstreamApprovals($approvalNode);

        return true;
    }

    /**
     * Get complete approval chain for document
     * 
     * @param GraphNode $rootNode
     * @return Collection
     */
    public function getApprovalChain(GraphNode $rootNode): Collection
    {
        $chain = collect([$rootNode]);
        $current = $rootNode;

        while ($next = $this->getNextApprover($current)) {
            $chain->push($next);
            $current = $next;
        }

        return $chain;
    }

    /**
     * Get approval status for document
     * 
     * @param GraphNode $rootNode
     * @return array
     */
    public function getApprovalStatus(GraphNode $rootNode): array
    {
        $chain = $this->getApprovalChain($rootNode);

        $status = [
            'initiated_at' => $rootNode->attributes['initiated_at'] ?? null,
            'initiator_id' => $rootNode->user_id,
            'topic' => $rootNode->attributes['topic'] ?? null,
            'overall_status' => $this->calculateOverallStatus($chain),
            'approvers' => [],
        ];

        foreach ($chain as $node) {
            if ($node->role === 'initiator') {
                continue;
            }

            $status['approvers'][] = [
                'user_id' => $node->user_id,
                'user_name' => $node->user->name,
                'level' => $node->attributes['level'] ?? 0,
                'status' => $node->attributes['status'] ?? 'pending',
                'approved_at' => $node->attributes['approved_at'] ?? null,
                'note' => $node->attributes['note'] ?? null,
            ];
        }

        return $status;
    }

    /**
     * Check if document can proceed to next approval
     * 
     * @param GraphNode $currentNode
     * @return bool
     */
    public function canProceedToNext(GraphNode $currentNode): bool
    {
        $attributes = $currentNode->attributes ?? [];
        return ($attributes['status'] ?? null) === 'approved';
    }

    /**
     * Check if approval is complete
     * 
     * @param GraphNode $rootNode
     * @return bool
     */
    public function isApprovalComplete(GraphNode $rootNode): bool
    {
        $chain = $this->getApprovalChain($rootNode);

        // Check if all nodes are approved
        foreach ($chain as $node) {
            if ($node->role === 'initiator') {
                continue;
            }

            $status = $node->attributes['status'] ?? 'pending';
            if ($status !== 'approved') {
                return false;
            }
        }

        return true;
    }

    /**
     * Get approvers at specific level
     * 
     * @param string $topic
     * @param int $level
     * @return Collection
     */
    public function getApproversAtLevel(string $topic, int $level): Collection
    {
        return ApprovalHierarchy::where('topic', $topic)
            ->where('level', $level)
            ->where('is_active', true)
            ->with('approver')
            ->pluck('approver')
            ->unique('id');
    }

    /**
     * Check if combo matches hierarchy filter
     * 
     * @param array $combo
     * @param array|null $hierarchyCombo
     * @return bool
     */
    private function comboMatches(array $combo, ?array $hierarchyCombo): bool
    {
        if ($hierarchyCombo === null || empty($hierarchyCombo)) {
            return true; // No filter = applies to all
        }

        // Check if all hierarchy combo requirements are met
        foreach ($hierarchyCombo as $key => $value) {
            if (!isset($combo[$key]) || $combo[$key] != $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * Calculate overall approval status
     * 
     * @param Collection $chain
     * @return string
     */
    private function calculateOverallStatus(Collection $chain): string
    {
        $statuses = $chain
            ->where('role', '!=', 'initiator')
            ->pluck('attributes.status')
            ->unique();

        if ($statuses->contains('rejected')) {
            return 'rejected';
        }

        if ($statuses->count() === 1 && $statuses->first() === 'approved') {
            return 'approved';
        }

        if ($statuses->contains('pending')) {
            return 'pending';
        }

        return 'initiated';
    }

    /**
     * Cancel all downstream approvals
     * 
     * @param GraphNode $fromNode
     * @return void
     */
    private function cancelDownstreamApprovals(GraphNode $fromNode): void
    {
        $downstreamNodes = $this->getDownstreamNodes($fromNode);

        foreach ($downstreamNodes as $node) {
            $attributes = $node->attributes ?? [];
            $attributes['status'] = 'cancelled';
            $attributes['cancelled_at'] = now()->toIso8601String();
            $node->update(['attributes' => $attributes]);
        }
    }

    /**
     * Get all downstream nodes in approval chain
     * 
     * @param GraphNode $fromNode
     * @return Collection
     */
    private function getDownstreamNodes(GraphNode $fromNode): Collection
    {
        $downstream = collect();
        $current = $fromNode;

        while ($next = $this->getNextApprover($current)) {
            $downstream->push($next);
            $current = $next;
        }

        return $downstream;
    }

    /**
     * Get pending approvals for user
     * 
     * @param User $user
     * @return Collection
     */
    public function getUserPendingApprovals(User $user): Collection
    {
        return GraphNode::where('user_id', $user->id)
            ->where('role', 'like', 'approver_%')
            ->get()
            ->filter(function ($node) {
                return ($node->attributes['status'] ?? null) === 'pending';
            });
    }

    /**
     * Get approval history for topic
     * 
     * @param string $topic
     * @return Collection
     */
    public function getApprovalHistory(string $topic): Collection
    {
        return GraphNode::where('attributes->topic', $topic)
            ->whereNotNull('attributes->approved_at')
            ->orderByDesc('attributes->approved_at')
            ->get();
    }
}
