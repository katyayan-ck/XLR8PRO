<?php

namespace App\Imports\Concerns;

use Illuminate\Support\Facades\DB;

trait MasterDataSeeder
{
    use CodeGenerator;

    private array $_locationCache = [];
    private array $_postCache     = [];
    private array $_desigCache    = [];
    private array $_segmentCache  = [];
    private array $_subSegCache   = [];

    // ────────────────────────────────────────────────────────────
    // BRAND
    // ────────────────────────────────────────────────────────────

    public function upsertBrand(string $name, string $code): object
    {
        DB::table('xlr8_vehicle_brand')->updateOrInsert(
            ['code' => $code],                        // MHD = 3 chars ✓
            ['name' => $name, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()]
        );
        return DB::table('xlr8_vehicle_brand')->where('code', $code)->first();
    }

    // ────────────────────────────────────────────────────────────
    // BRANCH
    // ────────────────────────────────────────────────────────────

    public function upsertBranch(string $name): object
    {
        $code = $this->branchCode($name);             // BKN, CHR = 3 chars ✓
        DB::table('xlr8_admin_branch')->updateOrInsert(
            ['code' => $code],
            ['name' => $name, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()]
        );
        return DB::table('xlr8_admin_branch')->where('code', $code)->first();
    }

    public function allBranchCodes(): array
    {
        return DB::table('xlr8_admin_branch')
            ->where('is_active', 1)
            ->pluck('code')
            ->toArray();
    }

    // ────────────────────────────────────────────────────────────
    // LOCATION
    // ────────────────────────────────────────────────────────────

    public function upsertLocation(string $name, string $branchCode): object
{
    $cacheKey = strtolower($name) . ':' . $branchCode;
    if (isset($this->_locationCache[$cacheKey])) {
        return $this->_locationCache[$cacheKey];
    }

    $existing = \Illuminate\Support\Facades\DB::table('xlr8_admin_location')
        ->whereRaw('LOWER(name) = ?', [strtolower($name)])
        ->first();

    if ($existing) {
        // Backfill branch_code if it was null from Rules sheet seeding
        if (empty($existing->branch_code)) {
            \Illuminate\Support\Facades\DB::table('xlr8_admin_location')
                ->where('code', $existing->code)
                ->update(['branch_code' => $branchCode, 'updated_at' => now()]);

            $existing = \Illuminate\Support\Facades\DB::table('xlr8_admin_location')
                ->where('code', $existing->code)
                ->first();
        }
        $this->_locationCache[$cacheKey] = $existing;
        return $existing;
    }

    // New location not in Rules sheet — create with branch context
    $code = $this->nextWorkLocationCode();
    \Illuminate\Support\Facades\DB::table('xlr8_admin_location')->insert([
        'code'        => $code,
        'name'        => $name,
        'branch_code' => $branchCode,
        'is_active'   => 1,
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);

    $loc = \Illuminate\Support\Facades\DB::table('xlr8_admin_location')
        ->where('code', $code)
        ->first();

    $this->_locationCache[$cacheKey] = $loc;
    return $loc;
}

    // ────────────────────────────────────────────────────────────
    // DEPARTMENT
    // ────────────────────────────────────────────────────────────

    public function upsertDepartment(string $name): object
    {
        $code = $this->deptCode($name);              // ACCT/ADMN/HR/INSR/SALE/SRVC = max 4 chars ✓
        DB::table('xlr8_admin_department')->updateOrInsert(
            ['code' => $code],
            ['name' => $name, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()]
        );
        return DB::table('xlr8_admin_department')->where('code', $code)->first();
    }

    // ────────────────────────────────────────────────────────────
    // DIVISION
    // ────────────────────────────────────────────────────────────

    public function upsertDivision(string $deptCode, string $divName): object
    {
        $code = $this->divisionCode($deptCode, $divName);  // SRVC-MECH = 9 chars ✓ (varchar 15)
        DB::table('xlr8_admin_division')->updateOrInsert(
            ['code' => $code],
            [
                'name'       => $divName,
                'dept_code'  => $deptCode,
                'is_active'  => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        return DB::table('xlr8_admin_division')->where('code', $code)->first();
    }

    public function divisionCodeSafe(string $deptCode, ?string $divName): ?string
    {
        if (!$divName) return null;
        return $this->divisionCode($deptCode, $divName);
    }

    // ────────────────────────────────────────────────────────────
    // VERTICAL
    // ────────────────────────────────────────────────────────────

    public function upsertVertical(string $name): object
    {
        $code = $this->verticalCode($name);          // VC-NC, VC-UC = 5 chars ✓
        DB::table('xlr8_admin_vertical')->updateOrInsert(
            ['code' => $code],
            ['name' => $name, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()]
        );
        return DB::table('xlr8_admin_vertical')->where('code', $code)->first();
    }

    public function allVerticalCodes(): array
    {
        return DB::table('xlr8_admin_vertical')->pluck('code')->toArray();
    }

    // ────────────────────────────────────────────────────────────
    // SEGMENT
    // Vehicle data already imported — LOOK UP FIRST by name.
    // Only insert as fallback using short codes (max 5 chars).
    // ────────────────────────────────────────────────────────────

    public function upsertSegment(string $name, string $brandCode = 'MHD'): object
    {
        $nameLower = strtolower(trim($name));

        if (isset($this->_segmentCache[$nameLower])) {
            return $this->_segmentCache[$nameLower];
        }

        // ① Find existing record by name (vehicle data already imported)
        $existing = DB::table('xlr8_vehicle_segment')
            ->whereRaw('LOWER(name) = ?', [$nameLower])
            ->first();

        if ($existing) {
            $this->_segmentCache[$nameLower] = $existing;
            return $existing;
        }

        // ② Fallback: insert with SHORT code (no SEG- prefix — max 5 chars)
        $code = $this->segmentCodeShort($name);      // BEV, COM, LMM, PER = 3-4 chars ✓

        // Ensure no collision on code either
        $byCode = DB::table('xlr8_vehicle_segment')->where('code', $code)->first();
        if ($byCode) {
            $this->_segmentCache[$nameLower] = $byCode;
            return $byCode;
        }

        DB::table('xlr8_vehicle_segment')->insert([
            'code'       => $code,
            'name'       => $name,
            'brand_code' => $brandCode,
            'is_active'  => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $rec = DB::table('xlr8_vehicle_segment')->where('code', $code)->first();
        $this->_segmentCache[$nameLower] = $rec;
        return $rec;
    }

    public function allSegmentCodes(): array
    {
        return DB::table('xlr8_vehicle_segment')->pluck('code')->toArray();
    }

    // ────────────────────────────────────────────────────────────
    // SUB-SEGMENT
    // Same strategy: look up existing first.
    // ────────────────────────────────────────────────────────────

    public function upsertSubSegment(string $name, string $segmentCode): object
    {
        $nameLower = strtolower(trim($name));

        if (isset($this->_subSegCache[$nameLower])) {
            return $this->_subSegCache[$nameLower];
        }

        // ① Find existing by name
        $existing = DB::table('xlr8_vehicle_subsegment')
            ->whereRaw('LOWER(name) = ?', [$nameLower])
            ->first();

        if ($existing) {
            $this->_subSegCache[$nameLower] = $existing;
            return $existing;
        }

        // ② Fallback: short code (max 5 chars)
        $code = $this->subSegmentCodeShort($name);   // XUV, NXUV = 3-4 chars ✓

        $byCode = DB::table('xlr8_vehicle_subsegment')->where('code', $code)->first();
        if ($byCode) {
            $this->_subSegCache[$nameLower] = $byCode;
            return $byCode;
        }

        DB::table('xlr8_vehicle_subsegment')->insert([
            'code'         => $code,
            'name'         => $name,
            'segment_code' => $segmentCode,
            'is_active'    => 1,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $rec = DB::table('xlr8_vehicle_subsegment')->where('code', $code)->first();
        $this->_subSegCache[$nameLower] = $rec;
        return $rec;
    }

    public function allSubSegmentCodes(): array
    {
        return DB::table('xlr8_vehicle_subsegment')->pluck('code')->toArray();
    }

    // ────────────────────────────────────────────────────────────
    // DESIGNATION
    // ────────────────────────────────────────────────────────────

    public function upsertDesignation(string $name): object
    {
        $code = $this->designationCode($name);        // max 8 chars ✓ (varchar 10)

        if (isset($this->_desigCache[$code])) {
            return $this->_desigCache[$code];
        }

        DB::table('xlr8_admin_designation')->updateOrInsert(
            ['code' => $code],
            ['name' => $name, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()]
        );

        $rec = DB::table('xlr8_admin_designation')->where('code', $code)->first();
        $this->_desigCache[$code] = $rec;
        return $rec;
    }

    // ────────────────────────────────────────────────────────────
    // POST
    // ────────────────────────────────────────────────────────────

    public function upsertPost(
        string  $desigCode,
        string  $branchCode,
        string  $deptCode,
        ?string $divCode
    ): object {
        $cacheKey = "{$desigCode}:{$branchCode}:{$deptCode}:{$divCode}";
        if (isset($this->_postCache[$cacheKey])) {
            return $this->_postCache[$cacheKey];
        }

        $existing = DB::table('xlr8_iam_post')
            ->where('desig_code',    $desigCode)
            ->where('branch_code',   $branchCode)
            ->where('dept_code',     $deptCode)
            ->where('division_code', $divCode)
            ->whereNull('deleted_at')
            ->first();

        if ($existing) {
            $this->_postCache[$cacheKey] = $existing;
            return $existing;
        }

        // SM-BKN-SRVC = 11 chars ✓ (varchar 30)
        $baseCode = $this->postCode($desigCode, $branchCode, $deptCode, $divCode);
        $seq      = 1;
        $code     = $baseCode;

        while (DB::table('xlr8_iam_post')->where('code', $code)->exists()) {
            $seq++;
            $code = $baseCode . '-' . $seq;
        }

        DB::table('xlr8_iam_post')->insert([
            'code'          => $code,
            'name'          => "{$desigCode} {$branchCode}",
            'desig_code'    => $desigCode,
            'branch_code'   => $branchCode,
            'dept_code'     => $deptCode,
            'division_code' => $divCode,
            'max_occupants' => 1,
            'is_active'     => 1,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $post = DB::table('xlr8_iam_post')->where('code', $code)->first();
        $this->_postCache[$cacheKey] = $post;
        return $post;
    }
}
