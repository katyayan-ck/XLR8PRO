<?php

namespace Tests\Feature\Platform\Concerns;

use App\Models\Approval\ApprovalRule;
use App\Models\Approval\ApprovalTopic;
use App\Models\User;
use App\Services\Platform\Approval\Entities\ApprovalRuleService;
use Illuminate\Support\Collection;

/**
 * Real users from the xlrm_testing copy (the suite's convention): people with a designation that
 * exists in the master, and people with a valid primary mobile.
 */
trait PlatformFixtures
{
    /** @return Collection<int, object{id: int, designation_code: string}> distinct designations */
    protected function staffWithDesignations(int $count): Collection
    {
        $rows = User::withoutGlobalScopes()->from('users as u')->toBase()->join('xlr8_admin_employee as e', 'e.code', '=', 'u.employee_code')
            ->join('xlr8_admin_designation as d', 'd.code', '=', 'e.designation_code')
            ->where('u.is_active', 1)->whereNull('e.deleted_at')
            ->whereNotIn('u.id', User::role('superadmin')->pluck('id'))
            ->select('u.id', 'e.designation_code')->orderBy('u.id')->get()->unique('designation_code')->take($count)->values();
        if ($rows->count() < $count) {
            $this->markTestSkipped("Needs {$count} active staff with distinct, valid designations.");
        }

        return $rows;
    }

    /** @return Collection<int, object{id: int, person_code: string, mobile: string}> */
    protected function peopleWithMobiles(int $count): Collection
    {
        $rows = User::withoutGlobalScopes()->from('users as u')->toBase()->join('xlr8_admin_person_contacts as c', 'c.person_code', '=', 'u.person_code')
            ->where('c.data_type', 'Mobile')->where('c.contact_type', 'Primary')->whereNull('c.deleted_at')->where('u.is_active', 1)
            ->select('u.id', 'u.person_code', 'c.contact_detail as mobile')->orderBy('u.id')->get()
            ->filter(fn ($r) => preg_match('/^[6-9]\d{9}$/', substr(preg_replace('/\D/', '', $r->mobile), -10)))
            ->unique('person_code')->take($count)->values();
        if ($rows->count() < $count) {
            $this->markTestSkipped("Needs {$count} users with a valid primary mobile.");
        }

        return $rows;
    }

    protected function superAdmin(): User
    {
        return User::role('superadmin')->firstOrFail();
    }

    /**
     * An approval rule on an item with one level per given designation and max.
     *
     * @param  list<array{0: string, 1: int|float}>  $levels  [designation, max]
     * @param  array<string, string>  $scope
     */
    protected function approvalRule(string $itemKey, array $levels, array $scope = [], string $valueType = 'AMOUNT'): ApprovalRule
    {
        $topic = ApprovalTopic::query()->where('item_key', $itemKey)->orWhere('code', $itemKey)->firstOrFail();
        $input = ['topic_id' => $topic->id, 'is_active' => true, 'levels' => []];
        foreach ($scope as $dim => $value) {
            $input[$dim.'_code'] = $value;
        }
        foreach (array_values($levels) as $i => [$designation, $max]) {
            $input['levels'][] = ['level_no' => $i + 1, 'designation_code' => $designation, 'value_type' => $valueType, 'max_value' => $max];
        }

        return app(ApprovalRuleService::class)->create($input);
    }
}
