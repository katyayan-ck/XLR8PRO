<?php

declare(strict_types=1);

namespace App\Services\Person;

use App\Models\Admin\Person;
use App\Rules\AadhaarNumber;
use App\Rules\Gstin;
use App\Rules\PanNumber;
use App\Rules\TanNumber;
use App\Services\IdentifierService;
use App\Support\Entity\EntityService;
use App\Support\Entity\Field;
use App\Support\Entity\ValueTransformer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * The person row (xlr8_admin_person) — its only write path (DEC-050/053).
 *
 * - person_code is generated at create (PERS-######) and never changes; it is never a government ID (BUG-206,
 *   DEC-095 #15). An upsert without a code finds the existing person by Aadhaar, PAN, then TAN (deleted ones
 *   restored), so the same person is not created twice.
 * - display_name and first/middle/last name fill each other: a missing display name is built from
 *   the parts, missing parts are split from the display name.
 * - Aadhaar/PAN/TAN/GSTIN are unique across all persons, deleted ones included (DB unique keys).
 * - Virtual `mobile` sets the primary mobile contact; photo/documents go to the media library.
 *
 * @extends EntityService<Person>
 */
final class PersonRecordService extends EntityService
{
    public const ENTITY_TYPES = ['individual', 'legal_entity'];

    public const SALUTATIONS = ['Mr', 'Mrs', 'Ms', 'Dr'];

    public const GENDERS = ['Male', 'Female', 'Other', 'Prefer not to say'];

    public const MARITAL_STATUSES = ['Single', 'Married', 'Divorced', 'Widowed'];

    public function __construct(private readonly PersonContactService $contacts) {}

    protected function model(): string
    {
        return Person::class;
    }

    protected function naturalKey(): array
    {
        return ['person_code'];
    }

    public function fields(): array
    {
        $identifiers = app(IdentifierService::class);

        return [
            Field::code('person_code', 20)->label(__('org.fields.person_code'))
                ->format('Derived: Aadhaar → PAN (individual), PAN → TAN (legal entity), else PERS-######')
                ->unique(includeTrashed: true)->immutable(),
            Field::choice('entity_type', self::ENTITY_TYPES)->label(__('org.fields.entity_type'))->required()->default('individual'),
            Field::make('salutation')->label(__('org.fields.salutation'))
                ->transform(fn (string $v) => rtrim($v, '.'), ...Field::choice('salutation', self::SALUTATIONS)->transforms)
                ->rules(Rule::in(self::SALUTATIONS))->format('One of: '.implode(', ', self::SALUTATIONS)),
            Field::text('display_name', 120)->label(__('org.fields.display_name'))
                ->format('Name as shown; Title Case for individuals, as typed for legal entities, max 120')
                ->transform('strip_tags', 'trim_spaces')->required(),
            Field::name('first_name', 60)->label(__('org.fields.first_name')),
            Field::name('middle_name', 60)->label(__('org.fields.middle_name')),
            Field::name('last_name', 60)->label(__('org.fields.last_name')),
            Field::choice('gender', self::GENDERS)->label(__('org.fields.gender')),
            Field::date('dob')->label(__('org.fields.dob'))->rules('before_or_equal:today'),
            Field::choice('marital_status', self::MARITAL_STATUSES)->label(__('org.fields.marital_status')),
            Field::name('spouse_name', 100)->label(__('org.fields.spouse_name')),
            Field::text('occupation', 80)->label(__('org.fields.occupation')),
            Field::make('aadhaar_no')->label(__('org.fields.aadhaar_no'))->format('12 digits; spaces/dashes removed')
                ->transform(fn (string $v) => $identifiers->normalizeAadhaar($v) ?? $v)
                ->rules(new AadhaarNumber)->unique(includeTrashed: true),
            Field::make('pan_no')->label(__('org.fields.pan_no'))->format('PAN, upper-case (ABCDE1234F)')
                ->transform('trim', 'uppercase')->rules(new PanNumber)->unique(includeTrashed: true),
            Field::make('tan_no')->label(__('org.fields.tan_no'))->format('TAN, upper-case')
                ->transform('trim', 'uppercase')->rules(new TanNumber)->unique(includeTrashed: true),
            Field::make('gst_no')->label(__('org.fields.gst_no'))->format('GSTIN, upper-case')
                ->transform('trim', 'uppercase')->rules(new Gstin)->unique(includeTrashed: true),
            Field::json('extra_data'),
            Field::phone('mobile')->label(__('org.fields.mobile'))->virtual(),
            Field::image('profile_photo')->label('Profile Photo'),
            Field::documents('identity_documents')->label('Identity Documents'),
            Field::flag('remove_profile_photo', false)->virtual(),
            Field::make('remove_identity_documents')->rules('array')->virtual(),
        ];
    }

    /**
     * Create, or update the person with the same (given or derived) code; a deleted person with
     * that code is restored first.
     *
     * @param  array<string, mixed>  $input
     * @param  list<string>|null  $key
     */
    public function upsert(array $input, ?array $key = null): Model
    {
        $normalised = $this->normalise($input);
        $code = $normalised['person_code'] ?? null;
        if ($code === null) {
            // BUG-206 (DEC-095 #15): the same person is found by a government ID, never by turning it into the code
            $existing = $this->findByIdentifiers($normalised);
            if ($existing !== null) {
                $code = (string) $existing->getAttribute('person_code');
                $input['person_code'] = $code;
            }
        }
        if ($code !== null) {
            Person::onlyTrashed()->where('person_code', $code)->first()?->restore();
        }

        return parent::upsert($input, $key);
    }

    /**
     * The person (deleted ones included) holding one of these identifiers: Aadhaar, then PAN, then TAN.
     *
     * @param  array<string, mixed>  $data  normalised input
     */
    private function findByIdentifiers(array $data): ?Person
    {
        foreach (['aadhaar_no', 'pan_no', 'tan_no'] as $column) {
            $value = $data[$column] ?? null;
            if ($value !== null && $value !== '') {
                $person = Person::withTrashed()->where($column, $value)->first();
                if ($person !== null) {
                    return $person;
                }
            }
        }

        return null;
    }

    protected function derive(array $data, array $input): array
    {
        $parts = array_filter([$data['first_name'] ?? null, $data['middle_name'] ?? null, $data['last_name'] ?? null]);

        if (($data['display_name'] ?? null) !== null && ($data['entity_type'] ?? 'individual') === 'individual') {
            $data['display_name'] = (new ValueTransformer)->run((string) $data['display_name'], 'title_case');
        }

        if (($data['display_name'] ?? null) === null && $parts !== []) {
            $data['display_name'] = $this->normaliseField('display_name', implode(' ', $parts));
        } elseif (($data['display_name'] ?? null) !== null && $parts === [] && ($data['entity_type'] ?? 'individual') === 'individual') {
            $words = preg_split('/\s+/', (string) $data['display_name']) ?: [];
            $data['first_name'] = $this->normaliseField('first_name', array_shift($words));
            if ($words !== []) {
                $data['last_name'] = $this->normaliseField('last_name', array_pop($words));
            }
            if ($words !== []) {
                $data['middle_name'] = $this->normaliseField('middle_name', implode(' ', $words));
            }
        }

        return $data;
    }

    protected function beforeCreate(array &$data): void
    {
        $data['person_code'] ??= Person::generateFallbackCode();
    }

    /**
     * @param  Person  $model
     * @param  array<string, mixed>  $input
     */
    protected function afterSave(Model $model, array $input, bool $created): void
    {
        $mobile = $this->normaliseField('mobile', $input['mobile'] ?? null);
        if ($mobile !== null) {
            $this->contacts->upsert([
                'person_code' => $model->person_code,
                'data_type' => 'Mobile',
                'contact_type' => 'Primary',
                'contact_detail' => $mobile,
            ]);
        }

        if (filter_var($input['remove_profile_photo'] ?? false, FILTER_VALIDATE_BOOL)) {
            $model->clearMediaCollection('profile_photos');
        }
        if (($input['profile_photo'] ?? null) instanceof UploadedFile) {
            $model->addMedia($input['profile_photo'])->toMediaCollection('profile_photos');
        }
        foreach ((array) ($input['identity_documents'] ?? []) as $file) {
            if ($file instanceof UploadedFile) {
                $model->addMedia($file)->toMediaCollection('identity_documents');
            }
        }
        foreach ((array) ($input['remove_identity_documents'] ?? []) as $mediaId) {
            $model->media()->where('id', $mediaId)->where('collection_name', 'identity_documents')->first()?->delete();
        }
    }
}
