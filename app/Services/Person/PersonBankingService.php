<?php

declare(strict_types=1);

namespace App\Services\Person;

use App\Models\Admin\PersonBankingDetail;
use App\Services\Person\Concerns\TypedSlots;
use App\Support\Entity\EntityService;
use App\Support\Entity\Field;
use Illuminate\Database\Eloquent\Model;

/**
 * Person bank accounts (xlr8_admin_person_banking_details) — their only write path (DEC-050/053).
 * One row per (person, account type); see TypedSlots for the Primary rules.
 *
 * @extends EntityService<PersonBankingDetail>
 */
final class PersonBankingService extends EntityService
{
    use TypedSlots;

    protected function model(): string
    {
        return PersonBankingDetail::class;
    }

    protected function naturalKey(): array
    {
        return ['person_code', 'account_type'];
    }

    public function fields(): array
    {
        return [
            Field::reference('person_code', 'xlr8_admin_person', 20, 'person_code')->label(__('org.fields.person_code'))->required()->immutable(),
            Field::choice('account_type', PersonBankingDetail::ACCOUNT_TYPES)->label(__('org.fields.account_type'))
                ->format('One of: '.implode(', ', PersonBankingDetail::ACCOUNT_TYPES).'; blank = Primary if free, else the next free type')
                ->unique(['person_code'], includeTrashed: true),
            Field::text('bank_name', 80)->label(__('org.fields.bank_name'))->transform('strip_tags', 'trim_spaces'),
            Field::text('branch_name', 80)->label(__('org.fields.branch_name'))->transform('strip_tags', 'trim_spaces'),
            Field::make('account_number')->label(__('org.fields.account_number'))->format('Letters/digits, spaces removed, max 30')
                ->transform(fn (string $v) => (string) preg_replace('/[\s\-]/', '', $v))->rules('alpha_num', 'max:30'),
            Field::name('account_holder_name', 100)->label(__('org.fields.account_holder_name')),
            Field::make('ifsc_code')->label(__('org.fields.ifsc_code'))->format('IFSC: 4 letters, 0, 6 letters/digits (upper-case)')
                ->transform('trim', 'uppercase')->rules('regex:/^[A-Z]{4}0[A-Z0-9]{6}$/'),
            Field::make('micr_code')->label('MICR Code')->format('9 digits')->transform('trim')->rules('digits:9'),
            Field::choice('account_nature', PersonBankingDetail::ACCOUNT_NATURES)->label('Account Nature')->default('Savings'),
            Field::flag('is_verified', false)->label(__('org.fields.is_verified')),
            Field::flag('is_primary', false)->virtual(),
        ];
    }

    protected function slotField(): string
    {
        return 'account_type';
    }

    protected function slotGroup(): array
    {
        return ['person_code'];
    }

    protected function slotTypes(): array
    {
        return PersonBankingDetail::ACCOUNT_TYPES;
    }

    /** @param  PersonBankingDetail  $model */
    protected function promote(Model $model): void
    {
        $model->makePrimary();
    }
}
