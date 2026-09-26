<?php

declare(strict_types=1);

namespace App\Services\IAM;

use App\Models\Admin\UserScope;
use App\Support\Entity\EntityService;
use App\Support\Entity\Field;
use Illuminate\Support\Fluent;
use Illuminate\Validation\Rule;

/**
 * A user's data scopes (xlr8_admin_user_scopes) — their only write path (DEC-050/054). Used by
 * the User screen (add-on scopes), the Users_Import sheet and the User_Scopes sheet.
 *
 * One row per (user, type, code); the unique key covers deleted rows, so a granted code reuses
 * its row (restored/re-activated). Revoking deactivates the row (is_active = 0, to_date = today)
 * rather than deleting it, so the grant history stays — readers only use active rows.
 *
 * @extends EntityService<UserScope>
 */
final class UserScopeService extends EntityService
{
    /** scope_type => master table whose `code` the scope_code must exist in */
    public const TYPES = [
        'branch' => 'xlr8_admin_branch',
        'location' => 'xlr8_admin_location',
        'department' => 'xlr8_admin_department',
        'division' => 'xlr8_admin_division',
        'vertical' => 'xlr8_admin_vertical',
        'segment' => 'xlr8_vehicle_segment',
        'sub_segment' => 'xlr8_vehicle_subsegment',
        'model' => 'xlr8_vehicle_model',
        'variant' => 'xlr8_vehicle_variant',
    ];

    protected function model(): string
    {
        return UserScope::class;
    }

    protected function naturalKey(): array
    {
        return ['user_id', 'scope_type', 'scope_code'];
    }

    public function fields(): array
    {
        $exists = [];
        foreach (self::TYPES as $type => $table) {
            $exists[] = Rule::when(fn (Fluent $in) => $in->scope_type === $type, [Rule::exists($table, 'code')->whereNull('deleted_at')]);
        }

        return [
            Field::make('user_id')->rules('integer', Rule::exists('users', 'id'))->required()->immutable(),
            Field::choice('scope_type', array_keys(self::TYPES))->label('Scope Type')->required()->immutable(),
            Field::code('scope_code', 50)->label('Scope Code')->rules(...$exists)
                ->unique(['user_id', 'scope_type'], includeTrashed: true)->required()->immutable(),
            Field::flag('is_active', true),
            Field::date('from_date'),
            Field::date('to_date')->rules('after_or_equal:from_date'),
        ];
    }

    /**
     * Give a user one scope code: re-activates (and restores) its row, or creates it.
     *
     * @return 'inserted'|'activated'|'unchanged'
     */
    public function grant(int $userId, string $type, string $code): string
    {
        $key = $this->normalise(['user_id' => $userId, 'scope_type' => $type, 'scope_code' => $code]);
        $scope = UserScope::withTrashed()->where($key)->first();

        if ($scope === null) {
            $this->create($key + ['is_active' => true, 'from_date' => now()->toDateString()]);

            return 'inserted';
        }
        if ($scope->is_active && ! $scope->trashed()) {
            return 'unchanged';
        }

        if ($scope->trashed()) {
            $scope->restore();
        }
        $this->update($scope, ['is_active' => true, 'from_date' => now()->toDateString(), 'to_date' => null]);

        return 'activated';
    }

    /** Take a scope away (kept as an inactive row ending today). */
    public function revoke(UserScope $scope): void
    {
        if ($scope->is_active) {
            $this->update($scope, ['is_active' => false, 'to_date' => now()->toDateString()]);
        }
    }

    /**
     * Make the user's active codes of one type exactly $codes.
     *
     * @param  list<string>  $codes
     * @return array{inserted: int, activated: int, deactivated: int}
     */
    public function sync(int $userId, string $type, array $codes): array
    {
        $counts = ['inserted' => 0, 'activated' => 0, 'deactivated' => 0];
        $wanted = [];
        foreach (array_filter($codes) as $code) {
            $wanted[] = $this->normaliseField('scope_code', $code);
        }
        $wanted = array_values(array_unique($wanted));

        foreach ($wanted as $code) {
            $result = $this->grant($userId, $type, $code);
            if ($result !== 'unchanged') {
                $counts[$result]++;
            }
        }

        $stale = UserScope::where('user_id', $userId)->where('scope_type', $this->normaliseField('scope_type', $type))
            ->where('is_active', true)->get()
            ->reject(fn (UserScope $s) => in_array(strtoupper((string) $s->scope_code), $wanted, true));
        foreach ($stale as $scope) {
            $this->revoke($scope);
            $counts['deactivated']++;
        }

        return $counts;
    }
}
