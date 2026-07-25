<?php
	
	namespace App\Models\Admin;
	
	use App\Models\BaseModel;
	use App\Models\User;
	use Illuminate\Database\Eloquent\Relations\BelongsTo;
	use Illuminate\Database\Eloquent\SoftDeletes;
	use Backpack\CRUD\app\Models\Traits\CrudTrait;
	
	class PersonUserType extends BaseModel
	{
		use SoftDeletes, CrudTrait;
		
		protected $table = 'xlr8_admin_person_user_types';
		
		public const USER_TYPES = [
        'Emp',
        'Cust',
        'DSA',
        'Insurer',
        'Associate',
        'Promoter',
        'Referrer',
		];
		
		protected $fillable = [
        'person_code',
        'user_id',
        'user_type',
        'is_primary',
        'is_active',
        'meta',
        'created_by',
        'updated_by',
        'deleted_by',
		];
		
		protected $casts = [
        'is_primary' => 'boolean',
        'is_active'  => 'boolean',
        'meta'       => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
		];
		
		// Relationships
		public function person(): BelongsTo
		{
			return $this->belongsTo(Person::class, 'person_code', 'person_code');
		}
		
		public function user(): BelongsTo
		{
			return $this->belongsTo(User::class, 'user_id');
		}
		
		// Scopes
		public function scopePrimary(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
		{
			return $query->where('is_primary', true);
		}
		
		public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
		{
			return $query->where('is_active', true);
		}
		
		public function scopeOfType(\Illuminate\Database\Eloquent\Builder $query, string $type): \Illuminate\Database\Eloquent\Builder
		{
			return $query->where('user_type', $type);
		}
		
		public function scopeForPerson(\Illuminate\Database\Eloquent\Builder $query, string $personCode): \Illuminate\Database\Eloquent\Builder
		{
			return $query->where('person_code', $personCode);
		}
		
		public function makePrimary(): void
		{
			static::where('person_code', $this->person_code)
            ->where('is_primary', true)
            ->where('id', '!=', $this->id)
            ->whereNull('deleted_at')
            ->update(['is_primary' => false]);
			
			$this->is_primary = true;
			$this->save();
		}
	}	