<?php

namespace App\Models\Admin;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class PersonContact extends Model
{
    use CrudTrait, SoftDeletes;

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
     * Promote this contact to Primary for its data_type.
     * The existing Primary for this (person_code, data_type) is demoted to Alternate.
     *
     * contact_type is a fixed 5-value DB ENUM with a unique index on
     * (person_code, data_type, contact_type) — MySQL checks that constraint per
     * statement (no deferred checking), so if this row is already 'Alternate' and
     * the old Primary is about to be demoted to 'Alternate' too, a naive demote-
     * then-promote sequence would momentarily give both rows the same enum value
     * and fail. Stage this row through a free contact_type first when that
     * collision is possible.
     */
    public function makesPrimary(): void
    {
        DB::transaction(function () {
            $oldPrimary = static::where('person_code', $this->person_code)
                ->where('data_type', $this->data_type)
                ->where('contact_type', 'Primary')
                ->where('id', '!=', $this->id)
                ->whereNull('deleted_at')
                ->first();

            if ($oldPrimary) {
                if ($this->contact_type === 'Alternate') {
                    $usedTypes = static::where('person_code', $this->person_code)
                        ->where('data_type', $this->data_type)
                        ->whereNull('deleted_at')
                        ->pluck('contact_type')
                        ->all();

                    $freeType = collect(self::CONTACT_TYPES)->first(fn ($t) => ! in_array($t, $usedTypes, true));

                    if ($freeType) {
                        $this->contact_type = $freeType;
                        $this->save();
                    }
                }

                $oldPrimary->update(['contact_type' => 'Alternate', 'updated_by' => auth()->id()]);
            }

            $this->contact_type = 'Primary';
            $this->save();
        });
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
