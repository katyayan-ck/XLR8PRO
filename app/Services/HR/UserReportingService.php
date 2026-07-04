<?php

namespace App\Services\HR;

use App\Models\Admin\UserReporting;
use App\Models\User;
use App\Services\KeywordValueService;
use Illuminate\Support\Collection;

class UserReportingService
{
    /**
     * Get reporting manager for topic (with scope support + topic master fallback)
     */
    public function getReportingManagerForTopic(User $user, string $topic, array $context = []): ?User
    {
        if (!$user->employee) return null;

        // 1. Check topic-specific override
        $reporting = UserReporting::active()
            ->forTopic($topic)
            ->where('user_id', $user->id)
            ->when(!empty($context), function ($q) use ($context) {
                foreach ($context as $type => $code) {
                    $q->orWhere(fn($sq) => $sq->where('scope_type', $type)->where('scope_code', $code));
                }
            })
            ->orderByRaw('scope_type IS NULL') // Scoped rules first
            ->first();

        if ($reporting) {
            return $reporting->reportsTo;
        }

        // 2. Fallback to default reporting manager
        return $this->getDefaultReportingManager($user);
    }

    public function getDefaultReportingManager(User $user): ?User
    {
        if (!$user->employee || !$user->employee->reporting_manager_code) return null;

        return User::whereHas('employee', fn($q) =>
            $q->where('code', $user->employee->reporting_manager_code)
        )->first();
    }

    /**
     * Get max_levels configured for a topic (from Keyword Master)
     */
    public function getMaxLevelsForTopic(string $topic): int
    {
        $config = KeywordValueService::getByCode('REPORTING_TOPIC', $topic);
        return $config?->extra_data['max_levels'] ?? 5; // system default
    }

    /**
     * Get direct reports (optionally filtered by topic)
     */
    public function getDirectReports(User $manager, ?string $topic = null): Collection
    {
        $query = UserReporting::active()->where('reports_to_user_id', $manager->id);

        if ($topic === 'default') {
            $query->whereNull('topic');
        } elseif ($topic) {
            $query->where('topic', $topic);
        }

        $topicUserIds = $query->pluck('user_id');

        // Include default reporting manager relationships
        $defaultUserIds = User::whereHas('employee', function ($q) use ($manager) {
            $q->where('reporting_manager_code', $manager->employee->code ?? null);
        })->pluck('id');

        $allIds = $topicUserIds->merge($defaultUserIds)->unique();

        return User::whereIn('id', $allIds)->get();
    }

    public function getFullDownline(User $manager, ?string $topic = null, int $maxDepth = 8): Collection
    {
        $downline = collect();
        $currentLevel = $this->getDirectReports($manager, $topic);
        $depth = 0;

        while ($currentLevel->isNotEmpty() && $depth < $maxDepth) {
            $downline = $downline->merge($currentLevel);
            $nextLevel = collect();

            foreach ($currentLevel as $person) {
                $nextLevel = $nextLevel->merge($this->getDirectReports($person, $topic));
            }

            $currentLevel = $nextLevel->unique('id');
            $depth++;
        }

        return $downline->unique('id');
    }
}
