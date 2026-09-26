<?php

namespace App\Models\Admin;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class PersonBankingDetail extends Model
{
    use CrudTrait, SoftDeletes;

    protected $table = 'xlr8_admin_person_banking_details';

    /*
    |--------------------------------------------------------------------------
    | DESIGN RULES
    |  - account_type replaces is_primary flag
    |  - One Primary per person_code (DB UNIQUE enforced)
    |  - First bank record for a person is auto-set to Primary
    |  - Use makePrimary() to change Primary account
    |--------------------------------------------------------------------------
    */

    const ACCOUNT_TYPES = ['Primary', 'Secondary', 'Joint', 'Trust'];

    const ACCOUNT_NATURES = ['Savings', 'Current', 'Salary', 'NRO', 'NRE'];

    protected $fillable = [
        'person_code',
        'account_type',
        'bank_name',
        'branch_name',
        'account_number',
        'account_holder_name',
        'ifsc_code',
        'micr_code',
        'account_nature',
        'is_verified',
        'verified_at',
        'verified_by',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // ── Boot ──────────────────────────────────────────────────────────────────

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (PersonBankingDetail $b) {
            if (empty($b->account_type)) {
                $exists = static::where('person_code', $b->person_code)
                    ->whereNull('deleted_at')
                    ->exists();

                $b->account_type = $exists ? 'Secondary' : 'Primary';
            }

            if (auth()->check() && empty($b->created_by)) {
                $b->created_by = auth()->id();
            }
        });

        static::updating(function (PersonBankingDetail $b) {
            if (auth()->check()) {
                $b->updated_by = auth()->id();
            }
        });

        static::deleting(function (PersonBankingDetail $b) {
            if (! $b->isForceDeleting() && auth()->check()) {
                $b->deleted_by = auth()->id();
                $b->saveQuietly();
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
     * Make this account the Primary. Demotes current Primary to Secondary.
     *
     * account_type is a fixed DB ENUM with a unique index on (person_code,
     * account_type) — see PersonContact::makesPrimary()'s docblock for why a
     * naive demote-then-promote can collide when this row is already the
     * 'Secondary' slot the old Primary is about to be demoted into.
     */
    public function makePrimary(): void
    {
        DB::transaction(function () {
            $oldPrimary = static::where('person_code', $this->person_code)
                ->where('account_type', 'Primary')
                ->where('id', '!=', $this->id)
                ->whereNull('deleted_at')
                ->first();

            if ($oldPrimary) {
                if ($this->account_type === 'Secondary') {
                    $usedTypes = static::where('person_code', $this->person_code)
                        ->whereNull('deleted_at')
                        ->pluck('account_type')
                        ->all();

                    $freeType = collect(self::ACCOUNT_TYPES)->first(fn ($t) => ! in_array($t, $usedTypes, true));

                    if ($freeType) {
                        $this->account_type = $freeType;
                        $this->save();
                    }
                }

                $oldPrimary->update(['account_type' => 'Secondary', 'updated_by' => auth()->id()]);
            }

            $this->account_type = 'Primary';
            $this->save();
        });
    }

    public function markVerified(): void
    {
        $this->update([
            'is_verified' => true,
            'verified_at' => now(),
            'verified_by' => auth()->id(),
        ]);
    }

    // ── Accessors ─────────────────────────────────────────────────────────────

    public function getMaskedAccountAttribute(): string
    {
        if (strlen($this->account_number) <= 6) {
            return str_repeat('*', strlen($this->account_number));
        }

        return substr($this->account_number, 0, 2)
            .str_repeat('*', strlen($this->account_number) - 6)
            .substr($this->account_number, -4);
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopePrimary($q)
    {
        return $q->where('account_type', 'Primary');
    }

    public function scopeVerified($q)
    {
        return $q->where('is_verified', true);
    }
}
