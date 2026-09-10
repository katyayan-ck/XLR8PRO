<?php

namespace App\Models\Admin;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\BaseModel;
use App\Models\Traits\HasColumnTransformations;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Branch extends BaseModel
{
    use SoftDeletes, CrudTrait, HasColumnTransformations;

    protected $table = 'xlr8_admin_branch';

    // Schema has code (varchar 255 unique) AND branch_code (varchar 10 short org key).
    // All cross-table FKs in employee/location/post use branch_code.
    protected $fillable = [
        'code',
        'name',
        'description',
        'phone',
        'email',
        'address',
        'city',
        'state',
        'pincode',
        'latitude',
        'longitude',
        'is_head_office',
        'is_active',
    ];

    protected array $columnTransformations = [
        'code'      => ['trim', 'uppercase_alphanumeric_dash_underscore'],
        'name'      => ['trim_spaces', 'title_case'],
        'city'      => ['trim_spaces', 'title_case'],
        'email'     => ['trim', 'lowercase'],
        'phone'     => 'numeric',
        'pincode'   => 'numeric',
    ];

    public function registerMediaCollections(): void
    {
        parent::registerMediaCollections();

        $this->addMediaCollection('branch_image')
            ->singleFile()
            ->acceptsMimeTypes([
                'image/jpeg',
                'image/png',
                'image/webp'
            ])
            ->useDisk('public');
    }

    // protected $casts = [
    //     'is_head_office' => 'boolean',
    //     'is_active'      => 'boolean',
    //     'latitude'       => 'float',
    //     'longitude'      => 'float',
    // ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'is_head_office' => 'boolean',
            'latitude'       => 'float',
            'longitude'      => 'float',
            // is_active already in BaseModel, no need to repeat
        ]);
    }
    public function getRouteKeyName(): string
    {
        return 'code';
    }

    // ── Relations ─────────────────────────────────────────────────────────────
    public function locations(): HasMany
    {
        // location.branch_code → branch.branch_code
        return $this->hasMany(Location::class, 'branch_code', 'code');
    }

    public function primaryEmployees(): HasMany
    {
        return $this->hasMany(Employee::class, 'primary_branch_code', 'branch_code');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(\App\Models\Iam\Post::class, 'branch_code', 'branch_code');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────
    // public function scopeActive($q)
    // {
    //     return $q->where('is_active', true);
    // }
    public function scopeHeadOffice($q)
    {
        return $q->where('is_head_office', true);
    }
    public function scopeByCity($q, $city)
    {
        return $q->where('city', $city);
    }
    // public function scopeByState($q, $s)
    // {
    //     return $q->where('state', $s);
    // }

    // ── Mutators ──────────────────────────────────────────────────────────────
    // public function setBranchCodeAttribute(string $v): void
    // {
    //     $this->attributes['branch_code'] = strtoupper(trim($v));
    // }

    // ── Accessors ─────────────────────────────────────────────────────────────
    public function getFullAddressAttribute(): string
    {
        return implode(', ', array_filter([
            $this->address,
            $this->city,
            $this->pincode
        ]));
    }
}
