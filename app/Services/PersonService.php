<?php

namespace App\Services;

use App\Models\Admin\Person;
use App\Models\Admin\PersonContact;
use App\Models\Admin\PersonAddress;
use App\Models\Admin\PersonBankingDetail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Throwable;

class PersonService
{
    public const DATA_TYPES      = ['Mobile', 'Email', 'Landline', 'Fax'];
    public const CONTACT_TYPES   = ['Primary', 'Alternate', 'Office', 'Home', 'Emergency'];
    public const ADDRESS_TYPES   = ['Primary', 'Office', 'Home', 'Alternate', 'Permanent'];
    public const ACCOUNT_TYPES   = ['Primary', 'Secondary', 'Joint', 'Trust'];
    public const ACCOUNT_NATURES = ['Savings', 'Current', 'Salary', 'NRO', 'NRE'];

    // ─────────────────────────────────────────────────────────────────────
    // 1. SMART FIND
    // ─────────────────────────────────────────────────────────────────────

    public static function find(string|array $criteria, array $options = []): ?Person
    {
        $query = self::buildQuery($criteria, $options);

        $person = $query->first();

        if (!$person && ($options['fail'] ?? false)) {
            throw (new \Illuminate\Database\Eloquent\ModelNotFoundException)
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

        $orderBy  = $options['orderBy']  ?? 'display_name';
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
            'with'        => $with,
            'withTrashed' => $options['withTrashed'] ?? false,
        ]);

        if (!$person) {
            return null;
        }

        if (!($options['asArray'] ?? true)) {
            return $person;
        }

        return self::formatProfile($person, $options);
    }

    // ─────────────────────────────────────────────────────────────────────
    // 4. UPSERT
    // ─────────────────────────────────────────────────────────────────────

    public static function upsert(array $data, array $options = []): Person
    {
        return DB::transaction(function () use ($data, $options) {

            $personCode = self::normalizeCode($data['person_code'] ?? null);

            if (!$personCode) {
                $temp = new Person([
                    'entity_type' => $data['entity_type'] ?? 'individual',
                    'pan_no'      => self::normalizeCode($data['pan_no'] ?? null),
                    'aadhaar_no'  => self::normalizeCode($data['aadhaar_no'] ?? null),
                    'tan_no'      => self::normalizeCode($data['tan_no'] ?? null),
                ]);
                $personCode = Person::deriveCode($temp);
            }

            $attrs = self::preparePersonAttributes($data, $personCode);

            $person = Person::withTrashed()->updateOrCreate(
                ['person_code' => $personCode],
                $attrs
            );

            if ($person->trashed() && ($options['restore'] ?? true)) {
                $person->restore();
            }

            if (!empty($data['contacts']) && is_array($data['contacts'])) {
                foreach ($data['contacts'] as $c) {
                    self::upsertContact($personCode, $c);
                }
            }

            if (!empty($data['addresses']) && is_array($data['addresses'])) {
                foreach ($data['addresses'] as $a) {
                    self::upsertAddress($personCode, $a);
                }
            }

            if (!empty($data['banking']) && is_array($data['banking'])) {
                foreach ($data['banking'] as $b) {
                    self::upsertBanking($personCode, $b);
                }
            }

            $with = $options['with'] ?? ['contacts', 'addresses', 'bankingDetails'];
            return $person->fresh($with);
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    // 5. CHILD UPSERTS + PRIMARY
    // ─────────────────────────────────────────────────────────────────────

    public static function upsertContact(string $personCode, array $data): PersonContact
    {
        $dataType    = self::normalizeEnum($data['data_type'] ?? 'Mobile', self::DATA_TYPES);
        $contactType = self::normalizeEnum($data['contact_type'] ?? null, self::CONTACT_TYPES);
        $detail      = $dataType === 'Mobile'
            ? self::cleanPhone($data['contact_detail'] ?? null)
            : self::n($data['contact_detail'] ?? null);

        if (!$detail) {
            throw new \InvalidArgumentException("contact_detail is required for {$dataType}");
        }

        $contact = PersonContact::updateOrCreate(
            [
                'person_code'  => $personCode,
                'data_type'    => $dataType,
                'contact_type' => $contactType ?: 'Alternate',
            ],
            [
                'contact_detail' => $detail,
            ]
        );

        if (($data['contact_type'] ?? null) === 'Primary' || ($data['is_primary'] ?? false)) {
            $contact->makesPrimary();
        }

        return $contact->fresh();
    }

    public static function upsertAddress(string $personCode, array $data): PersonAddress
    {
        $addressType = self::normalizeEnum($data['address_type'] ?? null, self::ADDRESS_TYPES) ?: 'Alternate';

        $address = PersonAddress::updateOrCreate(
            [
                'person_code'  => $personCode,
                'address_type' => $addressType,
            ],
            [
                'address_line_1' => self::s($data['address_line_1'] ?? null),
                'address_line_2' => self::s($data['address_line_2'] ?? null),
                'landmark'       => self::s($data['landmark'] ?? null),
                'city'           => self::s($data['city'] ?? null),
                'taluka'         => self::s($data['taluka'] ?? null),
                'district'       => self::s($data['district'] ?? null),
                'state'          => self::s($data['state'] ?? null),
                'country'        => self::s($data['country'] ?? 'India'),
                'pincode'        => self::s($data['pincode'] ?? null),
                'latitude'       => $data['latitude']  ?? null,
                'longitude'      => $data['longitude'] ?? null,
            ]
        );

        if (($data['address_type'] ?? null) === 'Primary' || ($data['is_primary'] ?? false)) {
            $address->makePrimary();
        }

        return $address->fresh();
    }

    public static function upsertBanking(string $personCode, array $data): PersonBankingDetail
    {
        $accountType = self::normalizeEnum($data['account_type'] ?? null, self::ACCOUNT_TYPES) ?: 'Secondary';

        $banking = PersonBankingDetail::updateOrCreate(
            [
                'person_code'  => $personCode,
                'account_type' => $accountType,
            ],
            [
                'bank_name'           => self::s($data['bank_name'] ?? null),
                'branch_name'         => self::s($data['branch_name'] ?? null),
                'account_number'      => self::s($data['account_number'] ?? null),
                'account_holder_name' => self::s($data['account_holder_name'] ?? null),
                'ifsc_code'           => self::normalizeCode($data['ifsc_code'] ?? null),
                'micr_code'           => self::s($data['micr_code'] ?? null),
                'account_nature'      => self::normalizeEnum($data['account_nature'] ?? 'Savings', self::ACCOUNT_NATURES),
                'is_verified'         => (bool) ($data['is_verified'] ?? false),
            ]
        );

        if (($data['account_type'] ?? null) === 'Primary' || ($data['is_primary'] ?? false)) {
            $banking->makePrimary();
        }

        return $banking->fresh();
    }

    public static function setPrimary(string $type, string $personCode, int|string $identifier): bool
    {
        $type = strtolower($type);

        return match ($type) {
            'contact', 'contacts'  => self::setPrimaryContact($personCode, $identifier),
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
                'person_code', 'code'
                    => $query->where('person_code', self::normalizeCode($value)),

                'pan_no', 'pan'
                    => $query->where('pan_no', self::normalizeCode($value)),

                'aadhaar_no', 'aadhaar', 'aadhar'
                    => $query->where('aadhaar_no', self::normalizeCode($value)),

                'tan_no', 'tan'
                    => $query->where('tan_no', self::normalizeCode($value)),

                'gst_no', 'gst'
                    => $query->where('gst_no', self::normalizeCode($value)),

                'entity_type'
                    => $query->where('entity_type', $value),

                'gender'
                    => $query->where('gender', $value),

                'mobile', 'phone'
                    => $query->whereHas('contacts', function (Builder $c) use ($value) {
                        $clean = self::cleanPhone($value) ?? $value;
                        $c->where('data_type', 'Mobile')
                          ->where('contact_detail', $clean)
                          ->whereNull('deleted_at');
                    }),

                'email'
                    => $query->whereHas('contacts', function (Builder $c) use ($value) {
                        $c->where('data_type', 'Email')
                          ->where('contact_detail', strtolower($value))
                          ->whereNull('deleted_at');
                    }),

                'account_number', 'account_no', 'bank_account'
                    => $query->whereHas('bankingDetails', function (Builder $b) use ($value) {
                        $b->where('account_number', $value)->whereNull('deleted_at');
                    }),

                // NEW: username or employee_code
                'username_or_emp', 'username', 'employee_code', 'emp_code'
                    => $query->where(function ($q) use ($value) {
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
        if (preg_match('/^[a-z0-9\-_]+$/i', $value) && !preg_match('/^\d+$/', $value)) {
            return ['username_or_emp' => strtolower($value)];
        }

        // Fallback → person_code
        return ['person_code' => $upper];
    }

    private static function preparePersonAttributes(array $data, string $personCode): array
    {
        $fullName = self::s($data['display_name'] ?? null);
        if (!$fullName && !empty($data['first_name'])) {
            $fullName = trim(implode(' ', array_filter([
                $data['first_name'] ?? '',
                $data['middle_name'] ?? '',
                $data['last_name'] ?? '',
            ])));
        }

        $nameParts = self::splitName($fullName);

        return array_filter([
            'person_code'    => $personCode,
            'entity_type'    => $data['entity_type'] ?? 'individual',
            'salutation'     => self::s($data['salutation'] ?? null),
            'first_name'     => self::s($data['first_name'] ?? $nameParts['first']),
            'middle_name'    => self::s($data['middle_name'] ?? $nameParts['middle']),
            'last_name'      => self::s($data['last_name'] ?? $nameParts['last']),
            'display_name'   => $fullName ?: null,
            'gender'         => self::s($data['gender'] ?? null),
            'dob'            => self::parseDate($data['dob'] ?? null),
            'marital_status' => self::s($data['marital_status'] ?? null),
            'spouse_name'    => self::s($data['spouse_name'] ?? null),
            'occupation'     => self::s($data['occupation'] ?? null),
            'pan_no'         => self::normalizeCode($data['pan_no'] ?? null),
            'aadhaar_no'     => self::normalizeCode($data['aadhaar_no'] ?? null),
            'tan_no'         => self::normalizeCode($data['tan_no'] ?? null),
            'gst_no'         => self::normalizeCode($data['gst_no'] ?? null),
            'extra_data'     => $data['extra_data'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');
    }

    private static function formatProfile(Person $p, array $options = []): array
    {
        $includeMedia = $options['includeMedia'] ?? true;

        $userTypes = PersonUserTypeService::getUserTypes($p->person_code)
            ->map(fn ($r) => [
                'id'         => $r->id,
                'user_type'  => $r->user_type,
                'is_primary' => (bool) $r->is_primary,
            ])
            ->values()
            ->toArray();

        $primaryRecord = PersonUserTypeService::getPrimary($p->person_code);

        $profile = [
            'person_code'    => $p->person_code,
            'display_name'   => $p->display_name,
            'employee_code'  => $p->employee?->code,
            'user_id'        => $primaryRecord?->user_id,
            'username'       => $primaryRecord?->user?->username,

            'primary_mobile' => $p->primary_mobile,
            'primary_email'  => $p->primary_email,

            'user_types'     => $userTypes,

            'contacts'  => $p->contacts->map(fn ($c) => [
                'id'             => $c->id,
                'data_type'      => $c->data_type,
                'contact_type'   => $c->contact_type,
                'contact_detail' => $c->contact_detail,
            ])->values()->toArray(),

            'addresses' => $p->addresses->map(fn ($a) => [
                'id'             => $a->id,
                'address_type'   => $a->address_type,
                'address_line_1' => $a->address_line_1,
                'address_line_2' => $a->address_line_2,
                'city'           => $a->city,
                'state'          => $a->state,
                'pincode'        => $a->pincode,
                'country'        => $a->country,
            ])->values()->toArray(),

            'banking' => $p->bankingDetails->map(fn ($b) => [
                'id'                  => $b->id,
                'account_type'        => $b->account_type,
                'bank_name'           => $b->bank_name,
                'account_number'      => $b->account_number,
                'ifsc_code'           => $b->ifsc_code,
                'account_holder_name' => $b->account_holder_name,
                'account_nature'      => $b->account_nature,
                'is_verified'         => (bool) $b->is_verified,
            ])->values()->toArray(),
        ];

        if ($includeMedia) {
            $profile['media'] = [
                'profile_photo' => $p->getFirstMediaUrl('profile_photos') ?: null,
                'identity_documents' => $p->getMedia('identity_documents')->map(fn ($m) => [
                    'id'        => $m->id,
                    'file_name' => $m->file_name,
                    'mime_type' => $m->mime_type,
                    'size'      => $m->size,
                    'url'       => $m->getUrl(),
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

        if (!$contact) {
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

        if (!$address) {
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

        if (!$bank) {
            return false;
        }

        $bank->makePrimary();
        return true;
    }

    // ── Tiny normalisers ─────────────────────────────────────────────────

    private static function s(mixed $v): string
    {
        return trim((string) ($v ?? ''));
    }

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

    private static function normalizeEnum(?string $v, array $allowed): ?string
    {
        if (!$v) {
            return null;
        }
        $v = ucfirst(strtolower(trim($v)));
        foreach ($allowed as $a) {
            if (strcasecmp($a, $v) === 0) {
                return $a;
            }
        }
        return null;
    }

    /**
     * FIXED cleanPhone – the previous ltrim('91') was the bug
     */
    private static function cleanPhone(?string $v): ?string
    {
        if (!$v) {
            return null;
        }

        // Keep only digits
        $v = preg_replace('/\D/', '', $v);

        // Remove leading 91 only when it is a 12-digit Indian number
        if (strlen($v) === 12 && str_starts_with($v, '91')) {
            $v = substr($v, 2);
        }

        // Remove a single leading 0
        if (strlen($v) === 11 && str_starts_with($v, '0')) {
            $v = substr($v, 1);
        }

        return strlen($v) === 10 ? $v : null;
    }

    private static function parseDate(mixed $v): ?string
    {
        if (!$v || trim((string) $v) === '') {
            return null;
        }
        try {
            return Carbon::parse($v)->format('Y-m-d');
        } catch (Throwable) {
            return null;
        }
    }

    private static function splitName(?string $full): array
    {
        $parts = array_values(array_filter(explode(' ', trim($full ?? ''))));
        return [
            'first'  => $parts[0] ?? '',
            'middle' => $parts[1] ?? '',
            'last'   => count($parts) > 2 ? implode(' ', array_slice($parts, 2)) : ($parts[1] ?? ''),
        ];
    }
}