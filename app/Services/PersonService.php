<?php

namespace App\Services;

use App\Models\Admin\Person;
use App\Models\Admin\PersonAddress;
use App\Models\Admin\PersonBankingDetail;
use App\Models\Admin\PersonContact;
use App\Services\Person\PersonAddressService;
use App\Services\Person\PersonBankingService;
use App\Services\Person\PersonContactService;
use App\Services\Person\PersonRecordService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PersonService
{
    public const DATA_TYPES = ['Mobile', 'Email', 'Landline', 'Fax'];

    public const CONTACT_TYPES = ['Primary', 'Alternate', 'Office', 'Home', 'Emergency'];

    public const ADDRESS_TYPES = ['Primary', 'Office', 'Home', 'Alternate', 'Permanent'];

    public const ACCOUNT_TYPES = ['Primary', 'Secondary', 'Joint', 'Trust'];

    public const ACCOUNT_NATURES = ['Savings', 'Current', 'Salary', 'NRO', 'NRE'];

    // ─────────────────────────────────────────────────────────────────────
    // 1. SMART FIND
    // ─────────────────────────────────────────────────────────────────────

    public static function find(string|array $criteria, array $options = []): ?Person
    {
        $query = self::buildQuery($criteria, $options);

        $person = $query->first();

        if (! $person && ($options['fail'] ?? false)) {
            throw (new ModelNotFoundException)
                ->setModel(Person::class, is_string($criteria) ? $criteria : json_encode($criteria));
        }

        return $person;
    }

    // ─────────────────────────────────────────────────────────────────────
    // 2. SMART SEARCH
    // ─────────────────────────────────────────────────────────────────────

    public static function search(array $criteria = [], array $options = []): EloquentCollection
    {
        $query = self::buildQuery($criteria, $options);

        if ($q = trim($options['q'] ?? '')) {
            $query->where(function (Builder $s) use ($q) {
                $s->where('display_name', 'like', "%{$q}%")
                    ->orWhere('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%")
                    ->orWhere('person_code', 'like', "%{$q}%")
                    ->orWhere('pan_no', 'like', "%{$q}%")
                    ->orWhere('aadhaar_no', 'like', "%{$q}%");
            });
        }

        if ($limit = $options['limit'] ?? null) {
            $query->limit((int) $limit);
        }
        if ($offset = $options['offset'] ?? null) {
            $query->offset((int) $offset);
        }

        $orderBy = $options['orderBy'] ?? 'display_name';
        $orderDir = $options['orderDir'] ?? 'asc';
        $query->orderBy($orderBy, $orderDir);

        return $query->get();
    }

    // ─────────────────────────────────────────────────────────────────────
    // 3. FULL PROFILE (get)
    // ─────────────────────────────────────────────────────────────────────

    public static function get(string $personCode, array $options = []): Person|array|null
    {
        $with = ['contacts', 'addresses', 'bankingDetails', 'employee'];

        if ($options['withUser'] ?? false) {
            $with[] = 'user';
        }

        $person = self::find($personCode, [
            'with' => $with,
            'withTrashed' => $options['withTrashed'] ?? false,
        ]);

        if (! $person) {
            return null;
        }

        if (! ($options['asArray'] ?? true)) {
            return $person;
        }

        return self::formatProfile($person, $options);
    }

    // ─────────────────────────────────────────────────────────────────────
    // 4. UPSERT — delegates to the entity services (DEC-050/053), which own every
    //    field's format, transformation and validation (ValidationException on bad data).
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Create or update a person (by the given person_code, else by Aadhaar / PAN / TAN — BUG-206; a deleted one is restored)
     * plus any `contacts`, `addresses` and `banking` rows, in one transaction.
     *
     * @param  array<string, mixed>  $data
     * @param  array{with?: list<string>}  $options
     *
     * @throws ValidationException
     */
    public static function upsert(array $data, array $options = []): Person
    {
        return DB::transaction(function () use ($data, $options) {
            $person = app(PersonRecordService::class)->upsert(
                array_diff_key($data, array_flip(['contacts', 'addresses', 'banking']))
            );

            foreach ((array) ($data['contacts'] ?? []) as $contact) {
                self::upsertContact($person->person_code, $contact);
            }
            foreach ((array) ($data['addresses'] ?? []) as $address) {
                self::upsertAddress($person->person_code, $address);
            }
            foreach ((array) ($data['banking'] ?? []) as $banking) {
                self::upsertBanking($person->person_code, $banking);
            }

            return $person->fresh($options['with'] ?? ['contacts', 'addresses', 'bankingDetails']);
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    // 5. CHILD UPSERTS (one row per type slot) + PRIMARY
    // ─────────────────────────────────────────────────────────────────────

    /** @param  array<string, mixed>  $data */
    public static function upsertContact(string $personCode, array $data): PersonContact
    {
        return app(PersonContactService::class)->upsert(['person_code' => $personCode] + $data + ['data_type' => 'Mobile']);
    }

    /** @param  array<string, mixed>  $data */
    public static function upsertAddress(string $personCode, array $data): PersonAddress
    {
        return app(PersonAddressService::class)->upsert(['person_code' => $personCode] + $data);
    }

    /** @param  array<string, mixed>  $data */
    public static function upsertBanking(string $personCode, array $data): PersonBankingDetail
    {
        return app(PersonBankingService::class)->upsert(['person_code' => $personCode] + $data);
    }

    public static function setPrimary(string $type, string $personCode, int|string $identifier): bool
    {
        $type = strtolower($type);

        return match ($type) {
            'contact', 'contacts' => self::setPrimaryContact($personCode, $identifier),
            'address', 'addresses' => self::setPrimaryAddress($personCode, $identifier),
            'banking', 'bank', 'banks' => self::setPrimaryBanking($personCode, $identifier),
            default => throw new \InvalidArgumentException("Unknown type [{$type}] for setPrimary"),
        };
    }

    // ─────────────────────────────────────────────────────────────────────
    // PRIVATE HELPERS
    // ─────────────────────────────────────────────────────────────────────

    private static function buildQuery(string|array $criteria, array $options = []): Builder
    {
        $query = Person::query();

        if ($options['withTrashed'] ?? false) {
            $query->withTrashed();
        }

        if ($with = $options['with'] ?? null) {
            $query->with($with);
        }

        if (is_string($criteria)) {
            $criteria = self::detectCriteria(trim($criteria));
        }

        foreach ($criteria as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $value = is_string($value) ? trim($value) : $value;

            match (strtolower($key)) {
                'person_code', 'code' => $query->where('person_code', self::normalizeCode($value)),

                'pan_no', 'pan' => $query->where('pan_no', self::normalizeCode($value)),

                'aadhaar_no', 'aadhaar', 'aadhar' => $query->where('aadhaar_no', self::normalizeCode($value)),

                'tan_no', 'tan' => $query->where('tan_no', self::normalizeCode($value)),

                'gst_no', 'gst' => $query->where('gst_no', self::normalizeCode($value)),

                'entity_type' => $query->where('entity_type', $value),

                'gender' => $query->where('gender', $value),

                'mobile', 'phone' => $query->whereHas('contacts', function (Builder $c) use ($value) {
                    $clean = self::cleanPhone($value) ?? $value;
                    $c->where('data_type', 'Mobile')
                        ->where('contact_detail', $clean)
                        ->whereNull('deleted_at');
                }),

                'email' => $query->whereHas('contacts', function (Builder $c) use ($value) {
                    $c->where('data_type', 'Email')
                        ->where('contact_detail', strtolower($value))
                        ->whereNull('deleted_at');
                }),

                'account_number', 'account_no', 'bank_account' => $query->whereHas('bankingDetails', function (Builder $b) use ($value) {
                    $b->where('account_number', $value)->whereNull('deleted_at');
                }),

                // NEW: username or employee_code
                'username_or_emp', 'username', 'employee_code', 'emp_code' => $query->where(function ($q) use ($value) {
                    $q->whereHas('user', fn ($u) => $u->where('username', $value))
                        ->orWhereHas('employee', fn ($e) => $e->where('code', strtoupper($value)));
                }),

                default => null,
            };
        }

        return $query;
    }

    private static function detectCriteria(string $value): array
    {
        $value = trim($value);
        $upper = strtoupper($value);

        // PAN
        if (preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', $upper)) {
            return ['pan_no' => $upper];
        }

        // Aadhaar (12 digits)
        if (preg_match('/^\d{12}$/', $value)) {
            return ['aadhaar_no' => $value];
        }

        // Mobile (10 digits after cleaning)
        $phone = self::cleanPhone($value);
        if ($phone && strlen($phone) === 10) {
            return ['mobile' => $phone];
        }

        // Email
        if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return ['email' => strtolower($value)];
        }

        // Username / employee_code style (bmpl-0282, BMPL-0282, etc.)
        if (preg_match('/^[a-z0-9\-_]+$/i', $value) && ! preg_match('/^\d+$/', $value)) {
            return ['username_or_emp' => strtolower($value)];
        }

        // Fallback → person_code
        return ['person_code' => $upper];
    }

    private static function formatProfile(Person $p, array $options = []): array
    {
        $includeMedia = $options['includeMedia'] ?? true;

        $userTypes = PersonUserTypeService::getUserTypes($p->person_code)
            ->map(fn ($r) => [
                'id' => $r->id,
                'user_type' => $r->user_type,
                'is_primary' => (bool) $r->is_primary,
            ])
            ->values()
            ->toArray();

        $primaryRecord = PersonUserTypeService::getPrimary($p->person_code);

        $profile = [
            'person_code' => $p->person_code,
            'display_name' => $p->display_name,
            'employee_code' => $p->employee?->code,
            'user_id' => $primaryRecord?->user_id,
            'username' => $primaryRecord?->user?->username,

            'primary_mobile' => $p->primary_mobile,
            'primary_email' => $p->primary_email,

            'user_types' => $userTypes,

            'contacts' => $p->contacts->map(fn ($c) => [
                'id' => $c->id,
                'data_type' => $c->data_type,
                'contact_type' => $c->contact_type,
                'contact_detail' => $c->contact_detail,
            ])->values()->toArray(),

            'addresses' => $p->addresses->map(fn ($a) => [
                'id' => $a->id,
                'address_type' => $a->address_type,
                'address_line_1' => $a->address_line_1,
                'address_line_2' => $a->address_line_2,
                'city' => $a->city,
                'state' => $a->state,
                'pincode' => $a->pincode,
                'country' => $a->country,
            ])->values()->toArray(),

            'banking' => $p->bankingDetails->map(fn ($b) => [
                'id' => $b->id,
                'account_type' => $b->account_type,
                'bank_name' => $b->bank_name,
                'account_number' => $b->account_number,
                'ifsc_code' => $b->ifsc_code,
                'account_holder_name' => $b->account_holder_name,
                'account_nature' => $b->account_nature,
                'is_verified' => (bool) $b->is_verified,
            ])->values()->toArray(),
        ];

        if ($includeMedia) {
            $profile['media'] = [
                'profile_photo' => $p->getFirstMediaUrl('profile_photos') ?: null,
                'identity_documents' => $p->getMedia('identity_documents')->map(fn ($m) => [
                    'id' => $m->id,
                    'file_name' => $m->file_name,
                    'mime_type' => $m->mime_type,
                    'size' => $m->size,
                    'url' => $m->getUrl(),
                ])->values()->toArray(),
            ];
        }

        return $profile;
    }

    // ── Primary helpers ──────────────────────────────────────────────────

    private static function setPrimaryContact(string $personCode, int|string $identifier): bool
    {
        $contact = is_numeric($identifier)
            ? PersonContact::where('person_code', $personCode)->where('id', $identifier)->first()
            : PersonContact::where('person_code', $personCode)
                ->where(function ($q) use ($identifier) {
                    $q->where('contact_detail', $identifier)
                        ->orWhereRaw("CONCAT(data_type, ':', contact_type) = ?", [$identifier]);
                })->first();

        if (! $contact) {
            return false;
        }

        $contact->makesPrimary();

        return true;
    }

    private static function setPrimaryAddress(string $personCode, int|string $identifier): bool
    {
        $address = is_numeric($identifier)
            ? PersonAddress::where('person_code', $personCode)->where('id', $identifier)->first()
            : PersonAddress::where('person_code', $personCode)->where('address_type', $identifier)->first();

        if (! $address) {
            return false;
        }

        $address->makePrimary();

        return true;
    }

    private static function setPrimaryBanking(string $personCode, int|string $identifier): bool
    {
        $bank = is_numeric($identifier)
            ? PersonBankingDetail::where('person_code', $personCode)->where('id', $identifier)->first()
            : PersonBankingDetail::where('person_code', $personCode)->where('account_type', $identifier)->first();

        if (! $bank) {
            return false;
        }

        $bank->makePrimary();

        return true;
    }

    // ── Tiny normalisers ─────────────────────────────────────────────────

    private static function n(mixed $v): ?string
    {
        $v = trim((string) ($v ?? ''));

        return in_array(strtolower($v), ['', 'null', 'n/a', 'na', '-', '?'], true) ? null : $v;
    }

    private static function normalizeCode(?string $v): ?string
    {
        $v = self::n($v);

        return $v ? strtoupper($v) : null;
    }

    private static function cleanPhone(?string $v): ?string
    {
        return app(IdentifierService::class)->cleanMobile($v);
    }
}
