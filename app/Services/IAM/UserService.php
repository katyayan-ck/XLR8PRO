<?php

declare(strict_types=1);

namespace App\Services\IAM;

use App\Models\User;
use App\Support\Entity\EntityService;
use App\Support\Entity\Field;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

/**
 * Login accounts (users) — their only write path (DEC-050/054). Used by the User screens
 * (create, edit, suspend/revoke/activate) and the user importer.
 *
 * - `username`: lower-case, letters/digits and . _ - @; unique including deleted accounts.
 * - `password`: taken exactly as typed (never trimmed), min 8, stored hashed; a blank password
 *   on update keeps the current one.
 * - Roles, permission overrides and `bypass_data_scoping` are not fields here: they are IAM
 *   decisions made by the calling workflow, never by data entry.
 *
 * @extends EntityService<User>
 */
final class UserService extends EntityService
{
    public const USER_TYPES = ['Emp', 'Cust', 'DSA', 'Insurer', 'Associate'];

    protected function model(): string
    {
        return User::class;
    }

    protected function naturalKey(): array
    {
        return ['username'];
    }

    public function fields(): array
    {
        return [
            Field::make('username')->label(__('org.fields.username'))->format('Lower-case: a-z 0-9 . _ - @, max 50')
                ->transform('trim', 'lowercase')->rules('string', 'max:50', 'regex:/^[a-z0-9._@\-]+$/')
                ->required()->unique(includeTrashed: true),
            Field::make('password')->label(__('org.fields.password'))->format('At least 8 characters, stored hashed')
                ->raw()->rules('string', 'min:8')->required(),
            Field::choice('user_type', self::USER_TYPES)->label('User Type')->required()->default('Emp'),
            Field::reference('person_code', 'xlr8_admin_person', 20, 'person_code')->label(__('org.fields.person_code')),
            Field::reference('employee_code', 'xlr8_admin_employee', 20)->label('Emp Code'),
            Field::flag('is_active', true)->label('Login Active'),
        ];
    }

    protected function beforeCreate(array &$data): void
    {
        $data['password'] = Hash::make((string) $data['password']);
    }

    /**
     * @param  User  $model
     * @param  array<string, mixed>  $input
     */
    public function update(Model $model, array $input): Model
    {
        if (($input['password'] ?? '') === '' || $input['password'] === null) {
            unset($input['password']);
        }

        return parent::update($model, $input);
    }

    /** @param  User  $model */
    protected function beforeUpdate(Model $model, array &$data): void
    {
        if (isset($data['password'])) {
            $data['password'] = Hash::make((string) $data['password']);
        }
    }
}
