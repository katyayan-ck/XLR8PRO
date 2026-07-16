<?php

namespace App\Services;

use App\Models\Admin\Branch;
use App\Models\Admin\Department;
use App\Models\Admin\Division;
use App\Models\Admin\Location;
use App\Models\Admin\Vertical;
use App\Models\Module\Booking\Bookingamount;
use App\Models\Module\Booking\XL_DSA_MASTER;
use App\Models\User;
use App\Models\Utilities\KeyValue\Keyvalue;
use App\Models\Utilities\KeyValue\KeywordMaster;
use App\Models\Vehicle\Color;
use App\Models\Vehicle\Segment;
use App\Models\Vehicle\SubSegment;
use App\Models\Vehicle\Variant;
use App\Models\Admin\Person;
use App\Models\Vehicle\VehicleModel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use App\Models\Admin\PinCodes;

class OrgService
{
    // ── Private Helpers ──────────────────────────────────────────────────

    private static function userQuery(array $filters = [])
    {
        return User::query()
            ->when(
                $filters['dept_code'] ?? null,
                fn($q, $v) => $q->whereHas('employee', fn($e) => $e->where('primary_dept_code', $v))
            )
            ->when(
                $filters['div_code'] ?? null,
                fn($q, $v) => $q->whereHas('employee', fn($e) => $e->where('primary_div_code', $v))
            )
            ->when(
                $filters['desig_code'] ?? null,
                fn($q, $v) => $q->whereHas('employee', fn($e) => $e->where('designation_code', $v))
            )
            ->when(
                isset($filters['branch_code']) && $filters['branch_code'] !== 'ALL',
                fn($q) => $q->whereHas('branches', fn($b) => $b->where('code', $filters['branch_code']))
            )
            ->select('id', 'username', 'employee_code')
            ->get();
    }

    private static function formatUsers($users): array
    {
        return $users->mapWithKeys(fn($u) => [
            $u->id => $u->employee_code
                ? "{$u->username} ({$u->employee_code})"
                : $u->username,
        ])->toArray();
    }

    private const CACHE_TTL = 3600;

    // ── Master Entities (code-based) ─────────────────────────────────────
    public static function branches(): array
    {
        return Cache::remember(
            'org.branches',
            self::CACHE_TTL,
            fn() => Branch::where('is_active', true)
                ->select('code', 'name')
                ->orderBy('name')
                ->pluck('name', 'code')
                ->toArray()
        );
    }

    public static function locations(?string $branchCode = null): array
    {
        $key = $branchCode ? "org.locations.{$branchCode}" : 'org.locations.all';

        return Cache::remember(
            $key,
            self::CACHE_TTL,
            fn() => Location::where('is_active', true)
                ->when($branchCode, fn($q) => $q->where('branch_code', $branchCode))
                ->select('code', 'name')
                ->orderBy('name')
                ->pluck('name', 'code')
                ->toArray()
        );
    }

    public static function departments(): array
    {
        return Cache::remember(
            'org.departments',
            self::CACHE_TTL,
            fn() => Department::where('is_active', true)
                ->select('code', 'name')
                ->orderBy('name')
                ->pluck('name', 'code')
                ->toArray()
        );
    }

    public static function divisions(?string $deptCode = null): array
    {
        $key = $deptCode ? "org.divisions.{$deptCode}" : 'org.divisions.all';

        return Cache::remember(
            $key,
            self::CACHE_TTL,
            fn() => Division::where('is_active', true)
                ->when($deptCode, fn($q) => $q->where('dept_code', $deptCode))
                ->select('code', 'name')
                ->orderBy('name')
                ->pluck('name', 'code')
                ->toArray()
        );
    }

    public static function verticals(): array
    {
        return Cache::remember(
            'org.verticals',
            self::CACHE_TTL,
            fn() => Vertical::where('is_active', true)
                ->select('code', 'name')
                ->orderBy('name')
                ->pluck('name', 'code')
                ->toArray()
        );
    }

    public static function segments(): array
    {
        return Cache::remember(
            'org.segments',
            self::CACHE_TTL,
            fn() => Segment::where('is_active', true)
                ->select('code', 'name')
                ->orderBy('name')
                ->pluck('name', 'code')
                ->toArray()
        );
    }

    public static function subSegments(?string $segmentCode = null): array
    {
        $key = $segmentCode ? "org.subsegments.{$segmentCode}" : 'org.subsegments.all';

        return Cache::remember(
            $key,
            self::CACHE_TTL,
            fn() => SubSegment::where('is_active', true)
                ->when($segmentCode, fn($q) => $q->where('segment_code', $segmentCode))
                ->select('code', 'name')
                ->orderBy('name')
                ->pluck('name', 'code')
                ->toArray()
        );
    }

    public static function models(?string $segmentCode = null): array
    {
        $key = $segmentCode ? "org.models.{$segmentCode}" : 'org.models.all';

        return Cache::remember(
            $key,
            self::CACHE_TTL,
            fn() => VehicleModel::where('is_active', true)
                ->when($segmentCode, fn($q) => $q->where('segment_code', $segmentCode))
                ->select('code', 'name')
                ->orderBy('name')
                ->pluck('name', 'code')
                ->toArray()
        );
    }

    // public static function variants(?string $modelCode = null): array
    // {
    //     $key = $modelCode ? "org.variants.{$modelCode}" : 'org.variants.all';
    //     return Cache::remember(
    //         $key,
    //         self::CACHE_TTL,
    //         fn() =>
    //         Variant::where('is_active', true)
    //             ->when($modelCode, fn($q) => $q->where('model_code', $modelCode))
    //             ->select('code', 'name')
    //             ->orderBy('name')
    //             ->pluck('name', 'code')
    //             ->toArray()
    //     );
    // }
    // public static function variants(?string $modelCode = null): array
    // {
    //     $key = $modelCode
    //         ? "org.variants.{$modelCode}"
    //         : 'org.variants.all';

    //     return Cache::remember(
    //         $key,
    //         self::CACHE_TTL,
    //         function () use ($modelCode) {

    //             return Variant::where('is_active', true)

    //                 ->when(
    //                     $modelCode,
    //                     fn ($q) => $q->where('model_code', $modelCode)
    //                 )

    //                 ->orderBy('display_name')

    //                 ->get()

    //                 ->mapWithKeys(function ($variant) {

    //                     return [

    //                         $variant->code => $variant->display_name
    //                             ?: $variant->custom_name
    //                             ?: $variant->oem_name,
    //                             ?: $variant->fuel_type_id,
    //                             ?: $variant->seating_capacity,
    //                             ?: $variant->transmission,
    //                             ?: $variant->drivetrain,

    //                     ];
    //                 })

    //                 ->toArray();
    //         }
    //     );
    // }

    public static function variants(?string $modelCode = null): array
    {
        $key = $modelCode
            ? "org.variants.{$modelCode}"
            : 'org.variants.all';

        return Cache::remember($key, self::CACHE_TTL, function () use ($modelCode) {

            return Variant::where('is_active', true)
                ->when($modelCode, fn($q) => $q->where('model_code', $modelCode))
                ->orderBy('display_name')
                ->get()
                ->mapWithKeys(function ($variant) {

                    $transmission = self::getKeyValueById($variant->transmission);
                    $drivetrain = self::getKeyValueById($variant->drivetrain);
                    $seating = self::getKeyValueById($variant->seating_capacity);
                    $fuel = self::getKeyValueById($variant->fuel_type_id);

                    return [

                        $variant->code => [

                            'name' => $variant->display_name
                                ?: $variant->custom_name
                                ?: $variant->oem_name,

                            'fuel_type_id' => $variant->fuel_type_id,
                            'fuel_type' => $fuel?->value,

                            'transmission' => $variant->transmission,
                            'drivetrain' => $variant->drivetrain,
                            'seating' => $variant->seating_capacity,

                        ]

                    ];
                })
                ->toArray();
        });
    }

    public static function colors(?string $variantCode = null): array
    {
        $key = $variantCode
            ? "org.colors.{$variantCode}"
            : 'org.colors.all';

        return Cache::remember(
            $key,
            self::CACHE_TTL,
            function () use ($variantCode) {

                return Color::where('is_active', true)

                    ->when(
                        $variantCode,
                        fn($q) => $q->where('variant_code', $variantCode)
                    )

                    ->orderBy('name')

                    ->pluck('name', 'code')

                    ->toArray();
            }
        );
    }

    // ── User Query Helpers ───────────────────────────────────────────────
    public static function usersByPost(string $postCode, string $branchCode = 'ALL', string $locationCode = 'ALL'): array
    {
        return User::whereHas('posts', function ($q) use ($postCode) {
            $q->where('xlr8_iam_roles.post_code', $postCode);   // ← qualified
        })
            ->when($branchCode !== 'ALL', fn($q) => $q->whereHas('branches', fn($b) => $b->where('code', $branchCode)))
            ->when($locationCode !== 'ALL', fn($q) => $q->whereHas('locations', fn($l) => $l->where('code', $locationCode)))
            ->select('id', 'username', 'employee_code')
            ->get()
            ->toArray();
    }

    // ── Single lookups ───────────────────────────────────────────────────
    public static function branchName(string $code): string
    {
        return self::branches()[$code] ?? $code;
    }

    public static function locationName(string $code): string
    {
        return self::locations()[$code] ?? $code;
    }

    public static function departmentName(string $code): string
    {
        return self::departments()[$code] ?? $code;
    }

    public static function divisionName(string $code): string
    {
        return self::divisions()[$code] ?? $code;
    }

    public static function verticalName(string $code): string
    {
        return self::verticals()[$code] ?? $code;
    }

    public static function segmentName(string $code): string
    {
        return self::segments()[$code] ?? $code;
    }

    public static function subSegmentName(string $code): string
    {
        return self::subSegments()[$code] ?? $code;
    }
    // Add these methods to your current new OrgService.php

    /**
     * Master User Filter Function (Uses flexible User Scopes)
     *
     * Filters work on `xlr8_admin_user_scopes` table (not just primary employee columns)
     */
    public static function keywordValueByParentCode(
        string $keywordCode,
        string $parentValueCode,
        ?string $parentKeywordCode = null
    ): array {
        $keywordCode = strtoupper(trim($keywordCode));
        $parentValueCode = strtoupper(trim($parentValueCode));
        $parentKeywordCode = $parentKeywordCode ? strtoupper(trim($parentKeywordCode)) : null;

        $cacheKey = $parentKeywordCode
            ? "org.keyvalue.{$keywordCode}.parent.{$parentKeywordCode}.{$parentValueCode}"
            : "org.keyvalue.{$keywordCode}.parent.{$parentValueCode}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($keywordCode, $parentValueCode, $parentKeywordCode) {
            $parentId = Keyvalue::where('code', $parentValueCode)
                ->when($parentKeywordCode, fn($q) => $q->where('keyword_code', $parentKeywordCode))
                ->where('is_active', true)
                ->value('id');

            if (!$parentId) {
                return [];
            }

            return Keyvalue::where('keyword_code', $keywordCode)
                ->whereRaw('FIND_IN_SET(?, parent_id)', [$parentId])   // <-- ye change
                ->where('is_active', true)
                ->orderBy('value')
                ->select('code', 'value')
                ->get()
                ->toArray();
        });
    }

    public static function getUsers(
        string $branchCode = 'ALL',
        string $locCode = 'ALL',
        string $deptCode = 'ALL',
        string $divCode = 'ALL',
        string $desigCode = 'ALL',
        string $verticalCode = 'ALL',
        string $segmentCode = 'ALL',
        string $subSegmentCode = 'ALL'
    ): array {
        $query = User::with(['person', 'scopes', 'employee'])
            ->whereHas('employee');

        // Designation (from employee table)
        if ($desigCode !== 'ALL') {
            $query->whereHas('employee', function ($e) use ($desigCode) {
                $e->where('designation_code', $desigCode)
                    ->orWhere('desig_code', $desigCode);
            });
        }

        // Build scope filters
        $scopeMap = [
            'branch' => $branchCode,
            'location' => $locCode,
            'department' => $deptCode,
            'division' => $divCode,
            'vertical' => $verticalCode,
            'segment' => $segmentCode,
            'sub_segment' => $subSegmentCode,
        ];

        foreach ($scopeMap as $type => $code) {
            if ($code === 'ALL') {
                continue;
            }

            $query->where(function ($q) use ($type, $code) {
                // Match in primary employee columns
                $primaryColumn = match ($type) {
                    'branch' => 'primary_branch_code',
                    'location' => 'primary_loc_code',
                    'department' => 'primary_dept_code',
                    'division' => 'primary_div_code',
                    'vertical' => 'vertical_code',
                    'segment' => 'segment_code',
                    'sub_segment' => 'sub_segment_code',
                };

                $q->whereHas('employee', fn($e) => $e->where($primaryColumn, $code))
                    // OR match in flexible user_scopes
                    ->orWhereHas('scopes', function ($s) use ($type, $code) {
                        $s->where('scope_type', $type)
                            ->where('scope_code', $code)
                            ->where('is_active', true);
                    });
            });
        }

        $users = $query->get();

        return $users->map(function ($user) {
            $emp = $user->employee;

            return [
                'id' => $user->id,
                'employee_code' => $user->employee_code,
                'person_code' => $user->person_code,
                'display_name' => $user->display_name,
                'designation_code' => $emp?->designation_code ?? $emp?->desig_code,
                'reporting_manager_code' => $emp?->reporting_manager_code,
                'mile_id' => $emp?->mile_id,

                'primary_branch_code' => $emp?->primary_branch_code,
                'primary_loc_code' => $emp?->primary_loc_code,
                'primary_dept_code' => $emp?->primary_dept_code,
                'primary_div_code' => $emp?->primary_div_code,
                'vertical_code' => $emp?->vertical_code,
                'segment_code' => $emp?->segment_code,
                'sub_segment_code' => $emp?->sub_segment_code,

                // All assigned scopes from user_scopes
                'branches' => $user->scopes->where('scope_type', 'branch')->pluck('scope_code')->unique()->values()->toArray(),
                'locations' => $user->scopes->where('scope_type', 'location')->pluck('scope_code')->unique()->values()->toArray(),
                'departments' => $user->scopes->where('scope_type', 'department')->pluck('scope_code')->unique()->values()->toArray(),
                'divisions' => $user->scopes->where('scope_type', 'division')->pluck('scope_code')->unique()->values()->toArray(),
                'verticals' => $user->scopes->where('scope_type', 'vertical')->pluck('scope_code')->unique()->values()->toArray(),
                'segments' => $user->scopes->where('scope_type', 'segment')->pluck('scope_code')->unique()->values()->toArray(),
                'sub_segments' => $user->scopes->where('scope_type', 'sub_segment')->pluck('scope_code')->unique()->values()->toArray(),

                'primary_mobile' => $user->primary_mobile,
                'primary_email' => $user->primary_email,
                'profile_image' => $user->avatar ?? $user->person?->getFirstMediaUrl('profile_photos') ?? null,
            ];
        })->toArray();
    }

    public static function getCurrentUser(): ?array
    {
        $user = Auth::user();

        if (!$user) {
            return null;
        }

        $user->load(['person', 'scopes', 'employee']);

        $emp = $user->employee;

        return [
            'id' => $user->id,
            'employee_code' => $user->employee_code,
            'person_code' => $user->person_code,
            'display_name' => $user->display_name,
            'designation_code' => $emp?->designation_code ?? $emp?->desig_code,
            'reporting_manager_code' => $emp?->reporting_manager_code,
            'mile_id' => $emp?->mile_id,
            'primary_branch_code' => $emp?->primary_branch_code,
            'primary_loc_code' => $emp?->primary_loc_code,
            'primary_dept_code' => $emp?->primary_dept_code,
            'primary_div_code' => $emp?->primary_div_code,
            'vertical_code' => $emp?->vertical_code,
            'segment_code' => $emp?->segment_code,
            'sub_segment_code' => $emp?->sub_segment_code,
            'branches' => $user->scopes->where('scope_type', 'branch')->pluck('scope_code')->unique()->values()->toArray(),
            'locations' => $user->scopes->where('scope_type', 'location')->pluck('scope_code')->unique()->values()->toArray(),
            'departments' => $user->scopes->where('scope_type', 'department')->pluck('scope_code')->unique()->values()->toArray(),
            'divisions' => $user->scopes->where('scope_type', 'division')->pluck('scope_code')->unique()->values()->toArray(),
            'verticals' => $user->scopes->where('scope_type', 'vertical')->pluck('scope_code')->unique()->values()->toArray(),
            'segments' => $user->scopes->where('scope_type', 'segment')->pluck('scope_code')->unique()->values()->toArray(),
            'sub_segments' => $user->scopes->where('scope_type', 'sub_segment')->pluck('scope_code')->unique()->values()->toArray(),
            'primary_mobile' => $user->primary_mobile,
            'primary_email' => $user->primary_email,
            'profile_image' => $user->avatar
                ?? $user->person?->getFirstMediaUrl('profile_photos')
                ?? null,
        ];
    }

    // //////
    public static function usersByDesignation(string $desigCode, string $branchCode = 'ALL'): array
    {
        $users = User::with('person')                    // ← Eager load person (needed for display_name)
            ->whereHas('employee', function ($q) use ($desigCode) {
                $q->where('designation_code', $desigCode);   // ← Use new column (recommended)
                // $q->where('desig_code', $desigCode);      // ← Use this only if still using legacy column
            })
            ->when($branchCode !== 'ALL', function ($q) use ($branchCode) {
                $q->whereHas('branches', fn($b) => $b->where('code', $branchCode));
            })
            ->select('id', 'username', 'employee_code', 'person_code')
            ->get();

        // Now map and include display_name (accessor will work)
        return $users->map(function ($user) {
            return [
                'id' => $user->id,
                'username' => $user->username,
                'employee_code' => $user->employee_code,
                'person_code' => $user->person_code,
                'display_name' => $user->display_name,     // ← This now works
            ];
        })->toArray();
    }

    public static function usersByDepartment(string $deptCode, string $branchCode = 'ALL', string $divCode = 'ALL'): array
    {
        return self::formatUsers(
            self::userQuery([
                'dept_code' => $deptCode,
                'branch_code' => $branchCode,
                'div_code' => $divCode !== 'ALL' ? $divCode : null,
            ])
        );
    }

    public static function usersByDivision(string $divCode, string $branchCode = 'ALL'): array
    {
        return self::formatUsers(
            self::userQuery([
                'div_code' => $divCode,
                'branch_code' => $branchCode,
            ])
        );
    }

    public static function salesConsultants(string $branchCode = 'ALL'): array
    {
        return self::formatUsers(
            self::userQuery([
                'desig_code' => 'CNS',
                'dept_code' => 'SLS',
                'branch_code' => $branchCode,
            ])
        );
    }

    public static function salesTeamUsers(string $branchCode = 'ALL'): array
    {
        return self::formatUsers(
            self::userQuery([
                'dept_code' => 'SLS',
                'branch_code' => $branchCode,
            ])
        );
    }

    public static function getKeyValuesByCode(string $keywordCode): ?Collection
    {
        return KeywordMaster::where('code', strtoupper(trim($keywordCode)))
            ->first()?->keyvalues()->where('is_active', true)->get();
    }

    public static function getKeyValuesByColName(string $colName): ?Collection
    {
        return KeywordMaster::where('keyword', strtoupper(trim($colName)))
            ->first()?->keyvalues()->where('is_active', true)->get();
    }

    // public static function getKeyValueById(int $id): ?Keyvalue
    // {
    //     return Keyvalue::where('id', $id)
    //         ->where('is_active', true)
    //         ->first();
    // }

    public static function getKeyValueById(int|string|null $id): ?KeyValue
    {
        if (empty($id)) {
            return null;
        }

        return KeyValue::where('id', (int) $id)
            ->where('is_active', true)
            ->first();
    }

    public static function getKeyValueByCode(string $code): ?Keyvalue
    {
        return Keyvalue::where('code', strtoupper(trim($code)))
            ->where('is_active', true)
            ->first();
    }

    /**
     * Existing shorthand — keyword_code se code=>value array (Backpack dropdown ready).
     */
    public static function keywordValueByCode(string $keywordCode): array
    {
        return Keyvalue::where('keyword_code', strtoupper(trim($keywordCode)))
            ->where('is_active', true)
            ->orderBy('value')
            ->select('code', 'value')
            ->get()
            ->toArray();
    }

    public static function users(array $filters = []): array
    {
        return self::formatUsers(self::userQuery($filters));
    }

    public static function getUserNameByCode(?string $code, ?int $colType = null, string $default = 'N/A'): string
    {
        if (blank($code)) {
            return $default;
        }

        if ($colType === 3) {
            return XL_DSA_MASTER::find((int) $code)?->name ?? $default;
        }

        $user = User::with('person')
            ->where('person_code', $code)
            ->orWhere('employee_code', $code)
            ->first();

        return $user?->display_name ?? $default;
    }

    public static function checkReceiptX($rn)
    {
        $list = Bookingamount::where('reciept', $rn)->first();
        if ($list) {
            return 1;
        } else {
            return 0;
        }
    }

    public static function getReferenceUsers(string $type, string $mobile): array
    {
        switch ($type) {

            case 'Customer':

                return Person::whereHas('contacts', function ($q) use ($mobile) {
                    $q->where('data_type', 'Mobile')
                        ->where('contact_detail', $mobile);
                })
                    ->orderBy('display_name')
                    ->pluck('display_name', 'person_code')
                    ->toArray();

            case 'Team Member':

                return User::whereHas('person.contacts', function ($q) use ($mobile) {
                    $q->where('data_type', 'Mobile')
                        ->where('contact_detail', $mobile);
                })
                    ->get()
                    ->mapWithKeys(function ($user) {
                        return [
                            $user->person_code => $user->display_name . ' (' . $user->employee_code . ')'
                        ];
                    })
                    ->toArray();

            case 'Promoter':

                return XL_DSA_MASTER::where('mobile', $mobile)
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->toArray();

            default:

                return [];
        }
    }

    public static function getPostOfficesByPincode($pincode)
    {
        return PinCodes::where('level', 'POSTOFFICE')
            ->where('pincode', $pincode)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    public static function getLocationByPincode($pincode)
{
    $postOffice = PinCodes::with(
        'parentLocation.parentLocation.parentLocation'
    )
    ->where('level', 'POSTOFFICE')
    ->where('pincode', $pincode)
    ->first();

    if (!$postOffice) {
        return [];
    }

    $tehsil = $postOffice->parentLocation;
    $district = $tehsil?->parentLocation;
    $state = $district?->parentLocation;

    return [
        'tehsil'  => $tehsil?->name,
        'district'=> $district?->name,
        'city'    => $state?->name,
    ];
}
}
