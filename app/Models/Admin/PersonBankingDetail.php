<?php

namespace App\Models\Admin;

use App\Models\Admin\Concerns\SwapsPrimarySlot;
use App\Models\Traits\HasColumnTransformations;
use App\Services\Person\PersonBankingService;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class PersonBankingDetail extends Model
{
    use CrudTrait, HasColumnTransformations, SoftDeletes, SwapsPrimarySlot;

    /** Field rules and transformations live in the entity service (DEC-050/053). */
    protected string $entityService = PersonBankingService::class;

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
     * Make this account the Primary; the old Primary takes this row's former slot.
     * See SwapsPrimarySlot (DEC-053).
     */
    public function makePrimary(): void
    {
        $this->swapIntoPrimary('account_type', ['person_code'], self::ACCOUNT_TYPES);
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
