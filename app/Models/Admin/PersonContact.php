<?php

namespace App\Models\Admin;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PersonContact extends Model
{
    use SoftDeletes, CrudTrait;

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

    const DATA_TYPES    = ['Mobile', 'Email', 'Landline', 'Fax'];
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
            if (!$c->isForceDeleting() && auth()->check()) {
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
     * Promote this contact to Primary for its data_type.
     * The existing Primary for this (person_code, data_type) is demoted to Alternate.
     * DB unique constraint on (person_code, data_type, contact_type='Primary') is respected.
     */
    public function makesPrimary(): void
    {
        // Demote current Primary
        static::where('person_code',   $this->person_code)
            ->where('data_type',     $this->data_type)
            ->where('contact_type',  'Primary')
            ->where('id', '!=',      $this->id)
            ->whereNull('deleted_at')
            ->update(['contact_type' => 'Alternate', 'updated_by' => auth()->id()]);

        $this->contact_type = 'Primary';
        $this->save();
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
