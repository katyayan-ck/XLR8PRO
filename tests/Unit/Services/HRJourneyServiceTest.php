<?php

namespace App\Services\HR;

use App\Exceptions\DomainException;
use App\Enums\ErrorCodeEnum;
use App\Models\Admin\EmpPostAssignment;
use App\Models\IAM\Post;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HRJourneyServiceTest extends TestCase
{
    /**
     * Onboard an employee to a post.
     * Creates a primary assignment. Throws if post is full or emp already has primary.
     */
    public function onboard(string $empCode, string $postCode, string $fromDate): EmpPostAssignment
    {
        return DB::transaction(function () use ($empCode, $postCode, $fromDate) {

            // Guard: emp must not already have an active primary assignment
            $alreadyHasPrimary = EmpPostAssignment::where('emp_code', $empCode)
                ->primary()
                ->current()
                ->exists();

            if ($alreadyHasPrimary) {
                // ← LINE 41 — string message FIRST, then ErrorCodeEnum
                throw new DomainException(
                    'Employee already has an active primary post assignment.',
                    ErrorCodeEnum::EMP_ALREADY_HAS_PRIMARY_POST
                );
            }

            // Guard: post must have vacancy
            $post = Post::where('post_code', $postCode)->firstOrFail();

            if (!$post->isVacant()) {
                // ← LINE 284 — string message FIRST, then ErrorCodeEnum
                throw new DomainException(
                    'Post has no vacancy. Cannot assign employee.',
                    ErrorCodeEnum::POST_FULLY_OCCUPIED
                );
            }

            return EmpPostAssignment::create([
                'emp_code'        => $empCode,
                'post_code'       => $postCode,
                'assignment_type' => 'primary',
                'from_date'       => $fromDate,
                'to_date'         => null,
                'relieving_type'  => 'onboarding',
            ]);
        });
    }

    /**
     * Transfer employee from current primary post to a new post.
     * Relieves old assignment and creates new primary.
     */
    public function transfer(string $empCode, string $newPostCode, string $transferDate): array
    {
        return DB::transaction(function () use ($empCode, $newPostCode, $transferDate) {

            // Find and relieve current primary
            $current = EmpPostAssignment::where('emp_code', $empCode)
                ->primary()
                ->current()
                ->firstOrFail();

            $current->update([
                'to_date'       => $transferDate,
                'relieving_type' => 'transfer',
            ]);

            // Assign to new post
            $newPost = Post::where('post_code', $newPostCode)->firstOrFail();

            if (!$newPost->isVacant()) {
                throw new DomainException(
                    'Target post has no vacancy.',
                    ErrorCodeEnum::POST_FULLY_OCCUPIED
                );
            }

            $assigned = EmpPostAssignment::create([
                'emp_code'        => $empCode,
                'post_code'       => $newPostCode,
                'assignment_type' => 'primary',
                'from_date'       => $transferDate,
                'to_date'         => null,
                'relieving_type'  => 'transfer',
            ]);

            return [
                'relieved' => $current->fresh(),
                'assigned' => $assigned,
            ];
        });
    }

    /**
     * Separate employee — close ALL active assignments on the given date.
     */
    public function separate(string $empCode, string $toDate, string $relievingType): Collection
    {
        return DB::transaction(function () use ($empCode, $toDate, $relievingType) {

            $active = EmpPostAssignment::where('emp_code', $empCode)
                ->current()
                ->get();

            foreach ($active as $assignment) {
                $assignment->update([
                    'to_date'        => $toDate,
                    'relieving_type' => $relievingType,
                ]);
            }

            return $active->map->fresh();
        });
    }

    /**
     * Return the full assignment history for an employee in chronological order.
     */
    public function getJourney(string $empCode): Collection
    {
        return EmpPostAssignment::where('emp_code', $empCode)
            ->chronological()
            ->with('post')
            ->get();
    }
}