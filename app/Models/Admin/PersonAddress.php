<?php

namespace App\Models\Admin;

use App\Models\Admin\Concerns\SwapsPrimarySlot;
use App\Models\Traits\HasColumnTransformations;
use App\Services\Person\PersonAddressService;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class PersonAddress extends Model
{
    use CrudTrait, HasColumnTransformations, SoftDeletes, SwapsPrimarySlot;

    /** Field rules and transformations live in the entity service (DEC-050/053). */
    protected string $entityService = PersonAddressService::class;

    protected $table = 'xlr8_admin_person_addresses';

    /*
    |--------------------------------------------------------------------------
    | DESIGN RULES
    |  - address_type replaces is_primary + type columns
    |  - One Primary per person_code (DB UNIQUE enforced)
    |  - First address for a person is auto-set to Primary
    |  - Use makePrimary() to change the Primary address
    |  - NO is_primary, notes columns
    |--------------------------------------------------------------------------
    */

    const ADDRESS_TYPES = ['Primary', 'Office', 'Home', 'Alternate', 'Permanent'];

    protected $fillable = [
        'person_code',
        'address_type',
        'address_line_1',
        'address_line_2',
        'landmark',
        'city',
        'taluka',
        'district',
        'state',
        'country',
        'pincode',
        'latitude',
        'longitude',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // ── Boot ──────────────────────────────────────────────────────────────────

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (PersonAddress $a) {
            if (empty($a->address_type)) {
                $exists = static::where('person_code', $a->person_code)
                    ->whereNull('deleted_at')
                    ->exists();

                $a->address_type = $exists ? 'Alternate' : 'Primary';
            }

            if (auth()->check() && empty($a->created_by)) {
                $a->created_by = auth()->id();
            }
        });

        static::updating(function (PersonAddress $a) {
            if (auth()->check()) {
                $a->updated_by = auth()->id();
            }
        });

        static::deleting(function (PersonAddress $a) {
            if (! $a->isForceDeleting() && auth()->check()) {
                $a->deleted_by = auth()->id();
                $a->saveQuietly();
            }
        });
    }

    // ── Relationships ──────────────────────────────────────────────────────────

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'person_code', 'person_code');
    }

    // ── Business logic ────────────────────────────────────────────────────────

    /**
     * Make this address the Primary; the old Primary takes this row's former slot.
     * See SwapsPrimarySlot (DEC-053).
     */
    public function makePrimary(): void
    {
        $this->swapIntoPrimary('address_type', ['person_code'], self::ADDRESS_TYPES);
    }

    // ── Accessors ─────────────────────────────────────────────────────────────

    public function getFullAddressAttribute(): string
    {
        return collect([
            $this->address_line_1,
            $this->address_line_2,
            $this->landmark ? "Near {$this->landmark}" : null,
            $this->city,
            $this->district,
            "{$this->state} - {$this->pincode}",
            $this->country,
        ])->filter()->implode(', ');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopePrimary($q)
    {
        return $q->where('address_type', 'Primary');
    }

    public function scopeByType($q, string $t)
    {
        return $q->where('address_type', $t);
    }
}
