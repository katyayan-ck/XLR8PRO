<?php

namespace App\Models\Admin;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class PersonAddress extends Model
{
    use CrudTrait, SoftDeletes;

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
     * Make this address the Primary. Demotes current Primary to Alternate.
     *
     * address_type is a fixed DB ENUM with a unique index on (person_code,
     * address_type) — see PersonContact::makesPrimary()'s docblock for why a
     * naive demote-then-promote can collide when this row is already the
     * 'Alternate' slot the old Primary is about to be demoted into.
     */
    public function makePrimary(): void
    {
        DB::transaction(function () {
            $oldPrimary = static::where('person_code', $this->person_code)
                ->where('address_type', 'Primary')
                ->where('id', '!=', $this->id)
                ->whereNull('deleted_at')
                ->first();

            if ($oldPrimary) {
                if ($this->address_type === 'Alternate') {
                    $usedTypes = static::where('person_code', $this->person_code)
                        ->whereNull('deleted_at')
                        ->pluck('address_type')
                        ->all();

                    $freeType = collect(self::ADDRESS_TYPES)->first(fn ($t) => ! in_array($t, $usedTypes, true));

                    if ($freeType) {
                        $this->address_type = $freeType;
                        $this->save();
                    }
                }

                $oldPrimary->update(['address_type' => 'Alternate', 'updated_by' => auth()->id()]);
            }

            $this->address_type = 'Primary';
            $this->save();
        });
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
