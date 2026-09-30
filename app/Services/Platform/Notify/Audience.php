<?php

declare(strict_types=1);

namespace App\Services\Platform\Notify;

use App\Models\User;
use App\Models\Utilities\CommHistory\CommMaster;
use App\Models\Utilities\CommHistory\CommSubscription;

/**
 * Who receives a notification (FRS §2.2 audience builders). Combines users, designations,
 * branches, person codes and "everyone following this record"; resolves to active user ids.
 *
 *   Audience::users([1, 2])->designation('SM')->branch('BKN')->except([$actorId])
 */
final class Audience
{
    /** @var list<int> */
    private array $userIds = [];

    /** @var list<string> */
    private array $designations = [];

    /** @var list<string> */
    private array $branches = [];

    /** @var list<string> */
    private array $personCodes = [];

    /** @var list<array{string, int}> */
    private array $watchers = [];

    /** @var list<int> */
    private array $excluded = [];

    /** @param  int|list<int>  $ids */
    public static function users(int|array $ids): self
    {
        return (new self)->andUsers($ids);
    }

    public static function designation(string ...$codes): self
    {
        return (new self)->andDesignation(...$codes);
    }

    public static function branch(string ...$codes): self
    {
        return (new self)->andBranch(...$codes);
    }

    public static function persons(string ...$personCodes): self
    {
        return (new self)->andPersons(...$personCodes);
    }

    /** Everyone subscribed to the record's conversation (Chat subscriptions). */
    public static function watchersOf(string $refType, int $refId): self
    {
        return (new self)->andWatchersOf($refType, $refId);
    }

    /** @param  int|list<int>  $ids */
    public function andUsers(int|array $ids): self
    {
        $this->userIds = array_merge($this->userIds, array_map('intval', (array) $ids));

        return $this;
    }

    public function andDesignation(string ...$codes): self
    {
        $this->designations = array_merge($this->designations, array_map('strtoupper', $codes));

        return $this;
    }

    /** Narrow designation matches to employees whose primary branch is one of these. */
    public function andBranch(string ...$codes): self
    {
        $this->branches = array_merge($this->branches, array_map('strtoupper', $codes));

        return $this;
    }

    public function andPersons(string ...$personCodes): self
    {
        $this->personCodes = array_merge($this->personCodes, $personCodes);

        return $this;
    }

    public function andWatchersOf(string $refType, int $refId): self
    {
        $this->watchers[] = [strtoupper($refType), $refId];

        return $this;
    }

    /** @param  int|list<int>  $ids */
    public function except(int|array $ids): self
    {
        $this->excluded = array_merge($this->excluded, array_map('intval', (array) $ids));

        return $this;
    }

    /** @return list<int> active user ids */
    public function resolve(): array
    {
        $ids = $this->userIds;

        if ($this->designations !== [] || ($this->branches !== [] && $this->designations === [])) {
            $query = User::query()->join('xlr8_admin_employee as e', 'e.code', '=', 'users.employee_code')
                ->whereNull('e.deleted_at');
            if ($this->designations !== []) {
                $query->whereIn('e.designation_code', $this->designations);
            }
            if ($this->branches !== []) {
                $query->whereIn('e.primary_branch_code', $this->branches);
            }
            $ids = array_merge($ids, $query->pluck('users.id')->all());
        }

        if ($this->personCodes !== []) {
            $ids = array_merge($ids, User::query()->whereIn('person_code', $this->personCodes)->pluck('id')->all());
        }

        foreach ($this->watchers as [$refType, $refId]) {
            $class = config("platform.entities.{$refType}.model");
            if ($class) {
                $ids = array_merge($ids, CommSubscription::query()->from('xlr8_utils_comm_subscription as s')
                    ->join((new CommMaster)->getTable().' as m', 'm.id', '=', 's.comm_master_id')
                    ->where('m.entityable_type', $class)->where('m.entityable_id', $refId)
                    ->pluck('s.user_id')->all());
            }
        }

        $ids = array_values(array_diff(array_unique(array_map('intval', $ids)), $this->excluded));
        if ($ids === []) {
            return [];
        }

        return User::query()->whereIn('id', $ids)->where('is_active', true)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }
}
