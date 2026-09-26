<?php

namespace App\Models\Admin;

use App\Models\Admin\Concerns\SwapsPrimarySlot;
use App\Models\Traits\HasColumnTransformations;
use App\Services\Person\PersonContactService;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PersonContact extends Model
{
    use CrudTrait, HasColumnTransformations, SoftDeletes, SwapsPrimarySlot;

    /** Field rules and transformations live in the entity service (DEC-050/053). */
    protected string $entityService = PersonContactService::class;

    protected $table = 'xlr8_admin_person_contacts';

    /*
    |--------------------------------------------------------------------------
    | DESIGN RULES
    |  - One record per contact detail value
    |  - data_type: Mobile | Email | Landline | Fax
    |  - contact_type: Primary | Alternate | Office | Home | Emergency
    |  - RULE: Only ONE Primary allowed per (person_code, data_type)
    |  - First entry for a person_code + data_type is auto-set to Primary
    |  - Use makesPrimary() to promote any entry to Primary
    |    (auto-demotes the existing Primary to Alternate)
    |  - NO name, relationship, notes, is_primary, phone, email columns
    |--------------------------------------------------------------------------
    */

    const DATA_TYPES = ['Mobile', 'Email', 'Landline', 'Fax'];

    const CONTACT_TYPES = ['Primary', 'Alternate', 'Office', 'Home', 'Emergency'];

    protected $fillable = [
        'person_code',
        'data_type',
        'contact_type',
        'contact_detail',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // ── Boot ──────────────────────────────────────────────────────────────────

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (PersonContact $c) {
            // Auto-assign Primary if this is the first entry of this data_type for this person
            if (empty($c->contact_type)) {
                $exists = static::where('person_code', $c->person_code)
                    ->where('data_type', $c->data_type)
                    ->whereNull('deleted_at')
                    ->exists();

                $c->contact_type = $exists ? 'Alternate' : 'Primary';
            }

            if (auth()->check() && empty($c->created_by)) {
                $c->created_by = auth()->id();
            }
        });

        static::updating(function (PersonContact $c) {
            if (auth()->check()) {
                $c->updated_by = auth()->id();
            }
        });

        static::deleting(function (PersonContact $c) {
            if (! $c->isForceDeleting() && auth()->check()) {
                $c->deleted_by = auth()->id();
                $c->saveQuietly();
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
     * Promote this contact to Primary for its data_type; the old Primary takes this row's former slot.
     * See SwapsPrimarySlot (DEC-053).
     */
    public function makesPrimary(): void
    {
        $this->swapIntoPrimary('contact_type', ['person_code', 'data_type'], self::CONTACT_TYPES);
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopePrimary($q)
    {
        return $q->where('contact_type', 'Primary');
    }

    public function scopeByDataType($q, $type)
    {
        return $q->where('data_type', $type);
    }

    public function scopeMobiles($q)
    {
        return $q->where('data_type', 'Mobile');
    }

    public function scopeEmails($q)
    {
        return $q->where('data_type', 'Email');
    }

    public function scopeEmergency($q)
    {
        return $q->where('contact_type', 'Emergency');
    }
}
