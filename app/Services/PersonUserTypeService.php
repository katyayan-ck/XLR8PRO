<?php
	
	namespace App\Services;
	
	use App\Models\Admin\Person;
	use App\Models\Admin\PersonUserType;
	use App\Models\User;
	use Illuminate\Support\Collection;
	use Illuminate\Support\Facades\DB;
	use Illuminate\Support\Facades\Log;
	
	class PersonUserTypeService
	{
		// -------------------------------------------------
		// Read helpers
		// -------------------------------------------------
		
		public static function getUserTypes(string $personCode, bool $activeOnly = true): Collection
		{
			$q = PersonUserType::with('user')
            ->where('person_code', $personCode);
			
			if ($activeOnly) {
				$q->where('is_active', true);
			}
			
			return $q->orderByDesc('is_primary')
			->orderBy('user_type')
			->get();
		}
		
		public static function getPrimary(string $personCode): ?PersonUserType
		{
			return PersonUserType::where('person_code', $personCode)
            ->where('is_primary', true)
            ->where('is_active', true)
            ->first();
		}
		
		public static function formatForProfile(string $personCode): array
		{
			return self::getUserTypes($personCode)
            ->map(fn (PersonUserType $r) => [
			'id'         => $r->id,
			'user_type'  => $r->user_type,
			'is_primary' => $r->is_primary,
			'is_active'  => $r->is_active,
			'user_id'    => $r->user_id,
			'username'   => $r->user?->username,
			'meta'       => $r->meta,
            ])
            ->values()
            ->toArray();
		}
		
		// -------------------------------------------------
		// Write helpers
		// -------------------------------------------------
		
		public static function assign(
        string $personCode,
        string $userType,
        ?int $userId = null,
        bool $isPrimary = false,
        array $meta = []
		): PersonUserType {
			$userType = self::normalize($userType);
			
			return DB::transaction(function () use ($personCode, $userType, $userId, $isPrimary, $meta) {
				$record = PersonUserType::updateOrCreate(
                [
				'person_code' => $personCode,
				'user_type'   => $userType,
                ],
                [
				'user_id'   => $userId,
				'is_active' => true,
				'meta'      => $meta ?: null,
                ]
				);
				
				if ($isPrimary) {
					$record->makePrimary();
				}
				
				return $record->fresh(['user']);
			});
		}
		
		public static function setPrimary(string $personCode, string $userType): bool
		{
			$record = PersonUserType::where('person_code', $personCode)
            ->where('user_type', self::normalize($userType))
            ->where('is_active', true)
            ->first();
			
			if (!$record) {
				return false;
			}
			
			$record->makePrimary();
			return true;
		}
		
		public static function remove(string $personCode, string $userType): bool
		{
			$record = PersonUserType::where('person_code', $personCode)
            ->where('user_type', self::normalize($userType))
            ->first();
			
			if (!$record) {
				return false;
			}
			
			$record->delete();
			return true;
		}
		
		// -------------------------------------------------
		// Sync methods
		// -------------------------------------------------
		
		/**
			* Back-fill from existing users table.
			* Creates one primary record per user (user_id is set).
		*/
		public static function syncFromUsers(): array
		{
			$stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];
			
			User::withTrashed()->chunkById(200, function ($users) use (&$stats) {
				foreach ($users as $user) {
					if (empty($user->person_code) || empty($user->user_type)) {
						$stats['skipped']++;
						continue;
					}
					
					$exists = PersonUserType::withTrashed()
                    ->where('person_code', $user->person_code)
                    ->where('user_type', $user->user_type)
                    ->exists();
					
					self::assign(
                    personCode: $user->person_code,
                    userType:   $user->user_type,
                    userId:     $user->id,
                    isPrimary:  true,
                    meta:       ['source' => 'users_sync']
					);
					
					$exists ? $stats['updated']++ : $stats['created']++;
				}
			});
			
			Log::info('PersonUserTypeService::syncFromUsers', $stats);
			return $stats;
		}
		
		/**
			* Ensure every Person has at least one record.
			* Persons that currently have zero entries get a placeholder
			* with user_id = null and user_type = 'Person'.
		*/
		public static function syncFromPersons(): array
		{
			$stats = ['created' => 0, 'skipped' => 0];
			
			Person::withTrashed()->chunkById(200, function ($persons) use (&$stats) {
				foreach ($persons as $person) {
					$hasAny = PersonUserType::withTrashed()
                    ->where('person_code', $person->person_code)
                    ->exists();
					
					if ($hasAny) {
						$stats['skipped']++;
						continue;
					}
					
					// Create a neutral placeholder
					self::assign(
                    personCode: $person->person_code,
                    userType:   'Person',          // neutral type
                    userId:     null,
                    isPrimary:  true,
                    meta:       ['source' => 'persons_sync']
					);
					
					$stats['created']++;
				}
			});
			
			Log::info('PersonUserTypeService::syncFromPersons', $stats);
			return $stats;
		}
		
		// -------------------------------------------------
		// Private
		// -------------------------------------------------
		
		private static function normalize(string $type): string
		{
			$type = ucfirst(strtolower(trim($type)));
			
			$map = [
            'Employee'  => 'Emp',
            'Customer'  => 'Cust',
            'Insurance' => 'Insurer',
            'Insu'      => 'Insurer',
			];
			
			return $map[$type] ?? $type;
		}
		
		/**
			* Get a clean, ready-to-use summary of a person's user-type associations
			* along with basic identity information.
			*
			* Returns:
			* [
			*     'person_code'    => string,
			*     'person_name'    => string|null,
			*     'employee_code'  => string|null,
			*     'primary_mobile' => string|null,
			*     'primary_email'  => string|null,
			*     'user_types'     => [
			*         [
			*             'user_type'  => 'Emp',
			*             'is_primary' => true,
			*             'user_id'    => 4,
			*             'username'   => 'bmpl-0033',
			*         ],
			*         ...
			*     ],
			*     'primary_type'   => [...]   // the one marked is_primary
			* ]
		*/
		public static function getSummary(string $personCode): ?array
		{
			$person = \App\Models\Admin\Person::with(['employee', 'user'])
			->where('person_code', $personCode)
			->first();
			
			if (!$person) {
				return null;
			}
			
			$userTypes = self::getUserTypes($personCode);   // existing method (Collection)
			
			$formattedTypes = $userTypes->map(function ($item) {
				return [
				'user_type'  => $item->user_type,
				'is_primary' => (bool) $item->is_primary,
				'user_id'    => $item->user_id,
				'username'   => $item->user?->username,
				];
			})->values()->toArray();
			
			return [
			'person_code'    => $person->person_code,
			'person_name'    => $person->display_name,
			'employee_code'  => $person->employee?->code,
			'primary_mobile' => $person->primary_mobile,
			'primary_email'  => $person->primary_email,
			'user_types'     => $formattedTypes,
			'primary_type'   => collect($formattedTypes)->firstWhere('is_primary', true),
			];
		}
		
	}	