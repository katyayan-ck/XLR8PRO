<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\{HasMany, HasOne};
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use App\Models\User;
use Backpack\CRUD\app\Models\Traits\CrudTrait;

class Person extends Model implements HasMedia
{
    use SoftDeletes, InteractsWithMedia, CrudTrait;

    protected $table = 'xlr8_admin_person';

    /*
    |--------------------------------------------------------------------------
    | DESIGN RULES
    |  - person_code is IMMUTABLE. Derived at creation, never changed.
    |  - Individual: PAN (priority) → Aadhaar → fallback PERS-XXXXXX
    |    If Aadhaar used at creation and PAN added later → code stays as Aadhaar.
    |  - Legal entity: PAN → TAN → GST → fallback
    |  - All child tables link via person_code, NOT person_id integer FK
    |  - NO mobile_/email_ columns — these live in xlr8_admin_person_contacts
    |--------------------------------------------------------------------------
    */

    protected $fillable = [
        'person_code',
        'entity_type',
        'salutation',
        'first_name',
        'middle_name',
        'last_name',
        'display_name',
        'gender',
        'dob',
        'marital_status',
        'spouse_name',
        'occupation',
        'aadhaar_no',
        'pan_no',
        'gst_no',
        'tan_no',
        'extra_data',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'dob'        => 'date',
        'extra_data' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // ── Boot ──────────────────────────────────────────────────────────────────

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Person $p) {
            if (empty($p->person_code)) {
                $p->person_code = static::deriveCode($p);
            }
            if (auth()->check() && empty($p->created_by)) {
                $p->created_by = auth()->id();
            }
        });

        static::updating(function (Person $p) {
            // IMMUTABLE — silently revert any change attempt
            if ($p->isDirty('person_code')) {
                $p->person_code = $p->getOriginal('person_code');
            }
            if (auth()->check()) {
                $p->updated_by = auth()->id();
            }
        });

        static::deleting(function (Person $p) {
            if (!$p->isForceDeleting() && auth()->check()) {
                $p->deleted_by = auth()->id();
                $p->saveQuietly();
            }
        });
    }

    // ── Code derivation ───────────────────────────────────────────────────────

    public static function deriveCode(Person $p): string
    {
        if ($p->entity_type === 'legal_entity') {
            $raw = $p->pan_no
                ?? $p->tan_no
                ?? static::generateFallbackCode();
        } else {
            $raw = $p->pan_no ?? $p->aadhaar_no ?? static::generateFallbackCode();
        }
        return strtoupper(trim($raw));
    }

    public static function generateFallbackCode(): string
    {
        $last = static::withTrashed()->max('id') ?? 0;
        return 'PERS-' . str_pad($last + 1, 6, '0', STR_PAD_LEFT);
    }

    // ── Relationships ──────────────────────────────────────────────────────────

    public function contacts(): HasMany
    {
        return $this->hasMany(PersonContact::class, 'person_code', 'person_code');
    }

    public function mobileContacts(): HasMany
    {
        return $this->contacts()->where('data_type', 'Mobile');
    }

    public function emailContacts(): HasMany
    {
        return $this->contacts()->where('data_type', 'Email');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(PersonAddress::class, 'person_code', 'person_code');
    }

    public function bankingDetails(): HasMany
    {
        return $this->hasMany(PersonBankingDetail::class, 'person_code', 'person_code');
    }

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class, 'person_code', 'person_code');
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'person_code', 'person_code');
    }

    public function headedDepartments(): HasMany
    {
        return $this->hasMany(Department::class, 'head_id');
    }

    public function headedDivisions(): HasMany
    {
        return $this->hasMany(Division::class, 'head_id');
    }

    public function garages(): HasMany
    {
        return $this->hasMany(Garage::class);
    }

    // ── Accessors ─────────────────────────────────────────────────────────────

    public function getFullNameAttribute(): string
    {
        return trim(collect([$this->first_name, $this->middle_name, $this->last_name])
            ->filter()
            ->implode(' '));
    }

    public function getPrimaryMobileAttribute(): ?string
    {
        return $this->mobileContacts()
            ->where('contact_type', 'Primary')
            ->whereNull('deleted_at')
            ->value('contact_detail');
    }

    public function getPrimaryEmailAttribute(): ?string
    {
        return $this->emailContacts()
            ->where('contact_type', 'Primary')
            ->whereNull('deleted_at')
            ->value('contact_detail');
    }

    public function getPrimaryAddressAttribute(): ?PersonAddress
    {
        return $this->addresses()
            ->where('address_type', 'Primary')
            ->whereNull('deleted_at')
            ->first();
    }

    public function getPrimaryBankAttribute(): ?PersonBankingDetail
    {
        return $this->bankingDetails()
            ->where('account_type', 'Primary')
            ->whereNull('deleted_at')
            ->first();
    }
    // Add at the end of accessors section
    public function getAllEmailsAttribute(): \Illuminate\Support\Collection
    {
        return $this->emailContacts()->pluck('contact_detail');
    }

    public function getAllMobilesAttribute(): \Illuminate\Support\Collection
    {
        return $this->mobileContacts()->pluck('contact_detail');
    }

    public function getAllAddressesAttribute(): \Illuminate\Support\Collection
    {
        return $this->addresses;
    }

    public function getAllBankingAttribute(): \Illuminate\Support\Collection
    {
        return $this->bankingDetails;
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeSearch($q, string $term)
    {
        return $q->where(
            fn($s) => $s
                ->where('first_name',    'like', "%{$term}%")
                ->orWhere('last_name',   'like', "%{$term}%")
                ->orWhere('display_name', 'like', "%{$term}%")
                ->orWhere('person_code', 'like', "%{$term}%")
                ->orWhere('pan_no',      'like', "%{$term}%")
                ->orWhere('aadhaar_no',  'like', "%{$term}%")
        );
    }

    public function scopeIndividuals($q)
    {
        return $q->where('entity_type', 'individual');
    }
    public function scopeLegalEntities($q)
    {
        return $q->where('entity_type', 'legal_entity');
    }

    // ── Media ─────────────────────────────────────────────────────────────────

    public function registerMediaCollections(): void
    {
        // parent::registerMediaCollections();

        $this->addMediaCollection('identity_documents')
            ->acceptsMimeTypes(['application/pdf', 'image/jpeg', 'image/png'])
            ->useDisk('public');

        $this->addMediaCollection('profile_photos')
            ->acceptsMimeTypes(['image/jpeg', 'image/png'])
            ->useDisk('public');
    }
}
