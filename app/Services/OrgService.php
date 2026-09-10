<?php

namespace App\Services;


use App\Models\Module\Booking\Bookingamount;
use App\Models\Module\Booking\XL_DSA_MASTER;
use App\Models\User;
use App\Models\Admin\{Branch, Location, Department, Division, Vertical};
use App\Models\Vehicle\{Segment, SubSegment, VehicleModel, Variant, Color};
use App\Models\Utilities\KeyValue\{Keyvalue, KeywordMaster};
use App\Models\Admin\Person;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use App\Models\Admin\PinCodes;
use Illuminate\Support\Facades\Log;

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

    // public static function getUsers(
    //     string $branchCode = 'ALL',
    //     string $locCode = 'ALL',
    //     string $deptCode = 'ALL',
    //     string $divCode = 'ALL',
    //     string $desigCode = 'ALL',
    //     string $verticalCode = 'ALL',
    //     string $segmentCode = 'ALL',
    //     string $subSegmentCode = 'ALL'
    // ): array {
    //     $query = User::with(['person', 'scopes', 'employee'])
    //         ->whereHas('employee');

    //     // Designation (from employee table)
    //     if ($desigCode !== 'ALL') {
    //         $query->whereHas('employee', function ($e) use ($desigCode) {
    //             $e->where('designation_code', $desigCode)
    //                 ->orWhere('desig_code', $desigCode);
    //         });
    //     }

    //     // Build scope filters
    //     $scopeMap = [
    //         'branch' => $branchCode,
    //         'location' => $locCode,
    //         'department' => $deptCode,
    //         'division' => $divCode,
    //         'vertical' => $verticalCode,
    //         'segment' => $segmentCode,
    //         'sub_segment' => $subSegmentCode,
    //     ];

    //     foreach ($scopeMap as $type => $code) {
    //         if ($code === 'ALL') {
    //             continue;
    //         }

    //         $query->where(function ($q) use ($type, $code) {
    //             // Match in primary employee columns
    //             $primaryColumn = match ($type) {
    //                 'branch' => 'primary_branch_code',
    //                 'location' => 'primary_loc_code',
    //                 'department' => 'primary_dept_code',
    //                 'division' => 'primary_div_code',
    //                 'vertical' => 'vertical_code',
    //                 'segment' => 'segment_code',
    //                 'sub_segment' => 'sub_segment_code',
    //             };

    //             $q->whereHas('employee', fn($e) => $e->where($primaryColumn, $code))
    //                 // OR match in flexible user_scopes
    //                 ->orWhereHas('scopes', function ($s) use ($type, $code) {
    //                     $s->where('scope_type', $type)
    //                         ->where('scope_code', $code)
    //                         ->where('is_active', true);
    //                 });
    //         });
    //     }

    //     $users = $query->get();

    //     return $users->map(function ($user) {
    //         $emp = $user->employee;

    //         return [
    //             'id' => $user->id,
    //             'employee_code' => $user->employee_code,
    //             'person_code' => $user->person_code,
    //             'display_name' => $user->display_name,
    //             'designation_code' => $emp?->designation_code ?? $emp?->desig_code,
    //             'reporting_manager_code' => $emp?->reporting_manager_code,
    //             'mile_id' => $emp?->mile_id,

    //             'primary_branch_code' => $emp?->primary_branch_code,
    //             'primary_loc_code' => $emp?->primary_loc_code,
    //             'primary_dept_code' => $emp?->primary_dept_code,
    //             'primary_div_code' => $emp?->primary_div_code,
    //             'vertical_code' => $emp?->vertical_code,
    //             'segment_code' => $emp?->segment_code,
    //             'sub_segment_code' => $emp?->sub_segment_code,

    //             // All assigned scopes from user_scopes
    //             'branches' => $user->scopes->where('scope_type', 'branch')->pluck('scope_code')->unique()->values()->toArray(),
    //             'locations' => $user->scopes->where('scope_type', 'location')->pluck('scope_code')->unique()->values()->toArray(),
    //             'departments' => $user->scopes->where('scope_type', 'department')->pluck('scope_code')->unique()->values()->toArray(),
    //             'divisions' => $user->scopes->where('scope_type', 'division')->pluck('scope_code')->unique()->values()->toArray(),
    //             'verticals' => $user->scopes->where('scope_type', 'vertical')->pluck('scope_code')->unique()->values()->toArray(),
    //             'segments' => $user->scopes->where('scope_type', 'segment')->pluck('scope_code')->unique()->values()->toArray(),
    //             'sub_segments' => $user->scopes->where('scope_type', 'sub_segment')->pluck('scope_code')->unique()->values()->toArray(),

    //             'primary_mobile' => $user->primary_mobile,
    //             'primary_email' => $user->primary_email,
    //             'profile_image' => $user->avatar ?? $user->person?->getFirstMediaUrl('profile_photos') ?? null,
    //         ];
    //     })->toArray();
    // }

    /**
     * Master User Filter Function (Uses flexible User Scopes)
     *
     * Filters work on `xlr8_admin_user_scopes` table AND primary employee columns.
     *
     * Supports:
     *  - Designation, Branch, Location, Department, Division, Vertical,
     *    Segment, SubSegment, Model, Variant filters.
     *  - Primary-only flag (if true, only primary columns are used; addon scopes ignored).
     *  - User type filter (optional).
     *
     * Returns a normalized array of user+employee+scope data (codes).
     */
    /**
     * Master User Filter Function (Uses flexible User Scopes)
     *
     * Filters work on `xlr8_admin_user_scopes` table AND primary employee columns.
     *
     * Supports:
     *  - Designation, Branch, Location, Department, Division, Vertical,
     *    Segment, SubSegment, Model, Variant filters.
     *  - Primary-only flag (if true, only primary columns are used; addon scopes ignored).
     *  - User type filter (optional).
     *
     * Returns a normalized array of user+employee+scope data (codes).
     */
    public static function getUsers(
        string $branchCode = 'ALL',
        string $locCode = 'ALL',
        string $deptCode = 'ALL',
        string $divCode = 'ALL',
        string $desigCode = 'ALL',
        string $verticalCode = 'ALL',
        string $segmentCode = 'ALL',
        string $subSegmentCode = 'ALL',
        string $modelCode = 'ALL',
        string $variantCode = 'ALL',
        ?string $userType = null,
        bool $primaryOnly = false
    ): array {
        // Base query with relations
        $query = User::with(['person', 'scopes', 'employee'])
            ->whereHas('employee');

        // Designation filter (employee table)
        if ($desigCode !== 'ALL') {
            $query->whereHas('employee', function ($e) use ($desigCode) {
                $e->where('designation_code', $desigCode)
                    ->orWhere('desig_code', $desigCode);
            });
        }

        // User type filter (optional)
        if ($userType !== null) {
            $query->where('user_type', $userType);
        }

        // Scope filters (org + vehicle + model + variant)
        $scopeMap = [
            'branch' => $branchCode,
            'location' => $locCode,
            'department' => $deptCode,
            'division' => $divCode,
            'vertical' => $verticalCode,
            'segment' => $segmentCode,
            'sub_segment' => $subSegmentCode,
            'model' => $modelCode,
            'variant' => $variantCode,
        ];

        foreach ($scopeMap as $type => $code) {
            if ($code === 'ALL') {
                continue;
            }

            $query->where(function ($q) use ($type, $code, $primaryOnly) {
                // Map type → employee primary column
                $primaryColumn = match ($type) {
                    'branch' => 'primary_branch_code',
                    'location' => 'primary_loc_code',
                    'department' => 'primary_dept_code',
                    'division' => 'primary_div_code',
                    'vertical' => 'vertical_code',
                    'segment' => 'segment_code',
                    'sub_segment' => 'sub_segment_code',
                    'model' => null, // no primary model on employee
                    'variant' => null, // no primary variant on employee
                    default => null,
                };

                // Always apply primary column filter when available
                if ($primaryColumn) {
                    $q->whereHas('employee', function ($e) use ($primaryColumn, $code) {
                        $e->where($primaryColumn, $code);
                    });
                }

                // If NOT primary-only, also match flexible user_scopes
                if (!$primaryOnly) {
                    $q->orWhereHas('scopes', function ($s) use ($type, $code) {
                        $s->where('scope_type', $type)
                            ->where('scope_code', $code)
                            ->where('is_active', true);
                    });
                }

                // For model & variant filters in primaryOnly mode, rely purely on scopes
                if ($primaryOnly && in_array($type, ['model', 'variant'], true)) {
                    $q->whereHas('scopes', function ($s) use ($type, $code) {
                        $s->where('scope_type', $type)
                            ->where('scope_code', $code)
                            ->where('is_active', true);
                    });
                }
            });
        }

        $users = $query->get();

        return $users->map(function ($user) {
            $emp = $user->employee;
            $person = $user->person;

            // Normalize avatar and person photo: treat empty string as null
            $avatarRaw = $user->avatar ?? null;
            $avatar = $avatarRaw !== '' ? $avatarRaw : null;

            $avatarInitials = $user->avatar_initials ?? 'U';

            $personPhotoRaw = $person?->getFirstMediaUrl('profile_photos') ?: null;
            $personPhotoUrl = $personPhotoRaw !== '' ? $personPhotoRaw : null;

            // Final profile_image: prefer avatar, else person photo, else null
            $profileImage = $avatar ?: $personPhotoUrl ?: null;

            return [
                // Identity
                'id' => $user->id,
                'username' => $user->username,
                'user_type' => $user->user_type ?? null,
                'employee_code' => $user->employee_code,
                'person_code' => $user->person_code,
                'display_name' => $user->display_name,

                // Designation + reporting manager
                'designation_code' => $emp?->designation_code ?? $emp?->desig_code,
                'reporting_manager_code' => $emp?->reporting_manager_code,

                // Employee misc
                'mile_id' => $emp?->mile_id,

                // Primary org & vehicle hierarchy
                'primary_branch_code' => $emp?->primary_branch_code,
                'primary_loc_code' => $emp?->primary_loc_code,
                'primary_dept_code' => $emp?->primary_dept_code,
                'primary_div_code' => $emp?->primary_div_code,
                'vertical_code' => $emp?->vertical_code,
                'segment_code' => $emp?->segment_code,
                'sub_segment_code' => $emp?->sub_segment_code,

                // All scopes (your existing arrays)
                'branches' => $user->scopes->where('scope_type', 'branch')->pluck('scope_code')->unique()->values()->toArray(),
                'locations' => $user->scopes->where('scope_type', 'location')->pluck('scope_code')->unique()->values()->toArray(),
                'departments' => $user->scopes->where('scope_type', 'department')->pluck('scope_code')->unique()->values()->toArray(),
                'divisions' => $user->scopes->where('scope_type', 'division')->pluck('scope_code')->unique()->values()->toArray(),
                'verticals' => $user->scopes->where('scope_type', 'vertical')->pluck('scope_code')->unique()->values()->toArray(),
                'segments' => $user->scopes->where('scope_type', 'segment')->pluck('scope_code')->unique()->values()->toArray(),
                'sub_segments' => $user->scopes->where('scope_type', 'sub_segment')->pluck('scope_code')->unique()->values()->toArray(),
                'models' => $user->scopes->where('scope_type', 'model')->pluck('scope_code')->unique()->values()->toArray(),
                'variants' => $user->scopes->where('scope_type', 'variant')->pluck('scope_code')->unique()->values()->toArray(),

                // Contact + avatar
                'primary_mobile' => $user->primary_mobile,
                'primary_email' => $user->primary_email,
                'avatar' => $avatar,
                'avatar_initials' => $avatarInitials,
                'profile_image' => $profileImage,
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
            'tehsil' => $tehsil?->name,
            'district' => $district?->name,
            'city' => $state?->name,
        ];
    }

    // ── HIERARCHY: UPLINE / DOWNLINE (with status & bypass controls) ─────

    /**
     * @param string $username
     * @param int    $maxDepth
     * @param string $status  'active' | 'inactive' | 'all' — filters employees by is_active/separation status
     * @param bool   $excludeBypassUsers  if true, skips users flagged bypass_data_scoping=1
     */
    public static function getUpline(
        string $username,
        int $maxDepth = 50,
        string $status = 'active',
        bool $excludeBypassUsers = false
    ): array {
        $current = self::findUserByCode($username, $status);

        if (!$current) {
            Log::warning('OrgService::getUpline — root user not found', ['username' => $username, 'status' => $status]);
            return [];
        }

        $upline = [];
        $visited = [strtoupper($current->employee_code ?? '')];
        $depth = 0;

        while ($current?->employee?->reporting_manager_code && $depth < $maxDepth) {
            $managerCode = strtoupper(trim($current->employee->reporting_manager_code));

            if (in_array($managerCode, $visited)) {
                Log::warning('OrgService::getUpline — cycle detected', [
                    'root_username' => $username,
                    'cycle_at' => $managerCode,
                    'chain' => $visited,
                ]);
                break;
            }

            $manager = self::findUserByCode($managerCode, $status);

            if (!$manager) {
                Log::warning('OrgService::getUpline — broken chain or manager filtered out by status', [
                    'root_username' => $username,
                    'broken_at_employee' => $current->employee_code,
                    'missing_manager_code' => $managerCode,
                    'status_filter' => $status,
                ]);
                break;
            }

            if ($excludeBypassUsers && $manager->bypass_data_scoping) {
                Log::info('OrgService::getUpline — skipped bypass_data_scoping user', ['employee_code' => $managerCode]);
                $current = $manager; // keep walking past them, just don't add to result
                $visited[] = $managerCode;
                $depth++;
                continue;
            }

            $upline[] = self::formatHierarchyNode($manager, ++$depth);
            $visited[] = $managerCode;
            $current = $manager;
        }

        if ($depth >= $maxDepth) {
            Log::warning('OrgService::getUpline — maxDepth reached', ['root_username' => $username]);
        }

        return $upline;
    }

    /**
     * @param string $status  'active' | 'inactive' | 'all'
     * @param bool   $excludeBypassUsers
     */
    public static function getDownline(
        string $username,
        bool $flat = false,
        string $status = 'active',
        bool $excludeBypassUsers = false
    ): array {
        $root = self::findUserByCode($username, 'all'); // root itself always resolved regardless of status
        if (!$root) {
            Log::warning('OrgService::getDownline — root user not found', ['username' => $username]);
            return [];
        }

        $rootCode = strtoupper($root->employee_code ?? '');
        $flatList = [];
        $tree = self::buildDownlineTree($rootCode, $flatList, 1, [$rootCode], $status, $excludeBypassUsers);

        return $flat ? $flatList : $tree;
    }

    public static function getDownlineCount(
        string $username,
        string $status = 'active',
        bool $excludeBypassUsers = false
    ): int {
        return count(self::getDownline($username, true, $status, $excludeBypassUsers));
    }

    public static function getDirectReports(
        string $username,
        string $status = 'active',
        bool $excludeBypassUsers = false
    ): array {
        $root = self::findUserByCode($username, 'all');
        if (!$root)
            return [];

        $rootCode = strtoupper($root->employee_code ?? '');

        return self::applyStatusFilter(
            User::with(['employee', 'person'])
                ->whereHas('employee', fn($e) => $e->whereRaw('UPPER(reporting_manager_code) = ?', [$rootCode])),
            $status
        )
            ->when($excludeBypassUsers, fn($q) => $q->where('bypass_data_scoping', false))
            ->get()
            ->map(fn($u) => self::formatHierarchyNode($u, 1))
            ->values()
            ->toArray();
    }

    // ── Private hierarchy helpers ─────────────────────────────────────────

    private static function findUserByCode(string $code, string $status = 'active'): ?User
    {
        $normalized = strtolower(trim($code));
        $query = User::with(['employee', 'person'])
            ->whereRaw('LOWER(username) = ?', [$normalized]);

        return self::applyStatusFilter($query, $status)->first();
    }

    /**
     * Applies active/inactive/all filter to a query builder.
     * 'active'   → users.is_active = 1 AND employee not separated
     * 'inactive' → users.is_active = 0 OR employee separated
     * 'all'      → no filter
     */
    private static function applyStatusFilter($query, string $status)
    {
        $status = strtolower($status);

        return match ($status) {
            'active' => $query->where('is_active', true)
                ->whereHas('employee', fn($e) => $e->whereNull('separation_date')),
            'inactive' => $query->where(function ($q) {
                    $q->where('is_active', false)
                    ->orWhereHas('employee', fn($e) => $e->whereNotNull('separation_date'));
                }),
            default => $query, // 'all'
        };
    }

    private static function formatHierarchyNode(User $user, int $depth = 0): array
    {
        $emp = $user->employee;
        return [
            'username' => $user->username,
            'employee_code' => $user->employee_code,
            'display_name' => $user->display_name,
            'designation_code' => $emp?->designation_code ?? $emp?->desig_code,
            'reporting_manager_code' => $emp?->reporting_manager_code,
            'primary_branch_code' => $emp?->primary_branch_code,
            'primary_loc_code' => $emp?->primary_loc_code,
            'primary_dept_code' => $emp?->primary_dept_code,
            'primary_div_code' => $emp?->primary_div_code,
            'is_active' => (bool) $user->is_active,
            'bypass_data_scoping' => (bool) $user->bypass_data_scoping,
            'separation_date' => $emp?->separation_date,
            'depth' => $depth,
        ];
    }

    private static function buildDownlineTree(
        string $managerCode,
        array &$flatList,
        int $depth,
        array $visited,
        string $status,
        bool $excludeBypassUsers
    ): array {
        $query = User::with(['employee', 'person'])
            ->whereHas('employee', fn($e) => $e->whereRaw('UPPER(reporting_manager_code) = ?', [$managerCode]));

        $reports = self::applyStatusFilter($query, $status)
            ->when($excludeBypassUsers, fn($q) => $q->where('bypass_data_scoping', false))
            ->get();

        $nodes = [];

        foreach ($reports as $user) {
            $empCode = strtoupper($user->employee_code ?? '');

            if (in_array($empCode, $visited)) {
                Log::warning('OrgService::buildDownlineTree — cycle detected', [
                    'manager_code' => $managerCode,
                    'repeated_code' => $empCode,
                    'chain' => $visited,
                ]);
                continue;
            }

            $node = self::formatHierarchyNode($user, $depth);
            $flatList[] = $node;

            // Note: children traversal continues WITHOUT status filter re-applied to $managerCode itself,
            // but each recursive level re-applies $status/$excludeBypassUsers to its own children query.
            $children = self::buildDownlineTree($empCode, $flatList, $depth + 1, [...$visited, $empCode], $status, $excludeBypassUsers);
            $node['children'] = $children;
            $nodes[] = $node;
        }

        return $nodes;
    }

    /**
     * Format a single code using a name resolver and a format option.
     *
     * $format: 'code' | 'name' | 'code_name'
     */
    public static function formatCodeWithName(
        ?string $code,
        callable $nameResolver,
        string $format = 'code'
    ): ?string {
        if (!$code) {
            return null;
        }

        $code = strtoupper(trim($code));
        $name = $nameResolver($code);

        return match ($format) {
            'name' => $name ?? $code,
            'code_name' => $name ? sprintf('[%s] %s', $code, $name) : $code,
            default => $code,
        };
    }

    /**
     * Format list of codes into a comma-separated string.
     */
    public static function formatCodeList(
        array $codes,
        callable $nameResolver,
        string $format = 'code'
    ): string {
        $codes = array_values(array_unique(array_filter($codes)));

        $parts = collect($codes)->map(function ($code) use ($nameResolver, $format) {
            return self::formatCodeWithName($code, $nameResolver, $format);
        })->filter()->values();

        return $parts->implode(', ');
    }

    /**
     * Vehicle helpers (using existing maps).
     */
    public static function modelName(string $code): string
    {
        $code = strtoupper(trim($code));
        $models = self::models(); // [code => name]

        return $models[$code] ?? $code;
    }

    public static function variantName(string $code): string
    {
        $code = strtoupper(trim($code));
        $variants = self::variants(null); // [code => display_name]

        return $variants[$code] ?? $code;
    }

    /**
     * Reporting manager: code/name/"[code] Name" using getUserNameByCode()
     */
    public static function formatReportingManager(
        ?string $code,
        string $format = 'code'
    ): ?string {
        return self::formatCodeWithName(
            $code,
            fn($c) => self::getUserNameByCode($c, null, 'N/A'),
            $format
        );
    }

    /**
     * Convenience wrapper for listing/grid:
     * - Calls getUsers(...) for codes
     * - Applies display format for all entities
     *
     * $orgFormat:     'code' | 'name' | 'code_name'  (Branch/Location/Dept/Division/Vertical)
     * $vehFormat:     'code' | 'name' | 'code_name'  (Segment/SubSegment/Model/Variant)
     * $managerFormat: 'code' | 'name' | 'code_name'  (Reporting manager)
     */
    public static function getUsersForListing(
        string $branchCode = 'ALL',
        string $locCode = 'ALL',
        string $deptCode = 'ALL',
        string $divCode = 'ALL',
        string $desigCode = 'ALL',
        string $verticalCode = 'ALL',
        string $segmentCode = 'ALL',
        string $subSegmentCode = 'ALL',
        string $modelCode = 'ALL',
        string $variantCode = 'ALL',
        ?string $userType = null,
        bool $primaryOnly = false,
        string $orgFormat = 'code',
        string $vehFormat = 'code',
        string $managerFormat = 'code'
    ): array {
        $rows = self::getUsers(
            $branchCode,
            $locCode,
            $deptCode,
            $divCode,
            $desigCode,
            $verticalCode,
            $segmentCode,
            $subSegmentCode,
            $modelCode,
            $variantCode,
            $userType,
            $primaryOnly
        );

        return collect($rows)->map(function ($row) use ($orgFormat, $vehFormat, $managerFormat) {
            // Primary org
            $primaryBranch = self::formatCodeWithName(
                $row['primary_branch_code'],
                fn($c) => self::branchName($c),
                $orgFormat
            );

            $primaryLocation = self::formatCodeWithName(
                $row['primary_loc_code'],
                fn($c) => self::locationName($c),
                $orgFormat
            );

            $primaryDept = self::formatCodeWithName(
                $row['primary_dept_code'],
                fn($c) => self::departmentName($c),
                $orgFormat
            );

            $primaryDiv = self::formatCodeWithName(
                $row['primary_div_code'],
                fn($c) => self::divisionName($c),
                $orgFormat
            );

            $primaryVertical = self::formatCodeWithName(
                $row['vertical_code'],
                fn($c) => self::verticalName($c),
                $orgFormat
            );

            $primarySegment = self::formatCodeWithName(
                $row['segment_code'],
                fn($c) => self::segmentName($c),
                $vehFormat
            );

            $primarySubSegment = self::formatCodeWithName(
                $row['sub_segment_code'],
                fn($c) => self::subSegmentName($c),
                $vehFormat
            );

            // Addon org scopes
            $addonBranches = self::formatCodeList(
                $row['branches'],
                fn($c) => self::branchName($c),
                $orgFormat
            );

            $addonLocations = self::formatCodeList(
                $row['locations'],
                fn($c) => self::locationName($c),
                $orgFormat
            );

            $addonDepts = self::formatCodeList(
                $row['departments'],
                fn($c) => self::departmentName($c),
                $orgFormat
            );

            $addonDivs = self::formatCodeList(
                $row['divisions'],
                fn($c) => self::divisionName($c),
                $orgFormat
            );

            $addonVerticals = self::formatCodeList(
                $row['verticals'],
                fn($c) => self::verticalName($c),
                $orgFormat
            );

            $addonSegments = self::formatCodeList(
                $row['segments'],
                fn($c) => self::segmentName($c),
                $vehFormat
            );

            $addonSubSegments = self::formatCodeList(
                $row['sub_segments'],
                fn($c) => self::subSegmentName($c),
                $vehFormat
            );

            // Models and variants in scope
            $modelsFormatted = self::formatCodeList(
                $row['models'],
                fn($c) => self::modelName($c),
                $vehFormat
            );

            $variantsFormatted = self::formatCodeList(
                $row['variants'],
                fn($c) => self::variantName($c),
                $vehFormat
            );

            // Reporting manager
            $managerFormatted = self::formatReportingManager(
                $row['reporting_manager_code'],
                $managerFormat
            );

            // Merge with original basic fields
            return [
                'id' => $row['id'],
                'username' => $row['username'],
                'user_type' => $row['user_type'],
                'employee_code' => $row['employee_code'],
                'person_code' => $row['person_code'],
                'display_name' => $row['display_name'],
                'designation_code' => $row['designation_code'],

                'reporting_manager' => $managerFormatted,

                'primary_branch' => $primaryBranch,
                'primary_location' => $primaryLocation,
                'primary_department' => $primaryDept,
                'primary_division' => $primaryDiv,
                'primary_vertical' => $primaryVertical,
                'primary_segment' => $primarySegment,
                'primary_sub_segment' => $primarySubSegment,

                'addon_branches' => $addonBranches,
                'addon_locations' => $addonLocations,
                'addon_departments' => $addonDepts,
                'addon_divisions' => $addonDivs,
                'addon_verticals' => $addonVerticals,
                'addon_segments' => $addonSegments,
                'addon_sub_segments' => $addonSubSegments,

                'models' => $modelsFormatted,
                'variants' => $variantsFormatted,

                'primary_mobile' => $row['primary_mobile'],
                'primary_email' => $row['primary_email'],
                'profile_image' => $row['profile_image'],
            ];
        })->toArray();
    }

}
// changing the demo document