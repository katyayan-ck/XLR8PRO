<?php

namespace App\Helpers;

use App\Models\X_Branch;
use App\Models\Module\Finance\XlFinancier;
use App\Models\Module\Booking\Xl_DSA_Master;
use App\Models\Vehicle\Segment;
use App\Models\Vehicle\VehicleModel;
use App\Models\Vehicle\Variant;
use App\Models\Vehicle\Color;       // ← Added this
use App\Models\Admin\Branch;
use App\Models\Admin\Location;

class CommonHelper
{
    /**
     * Get All Active Branches
     */
    public static function getBranches()
    {
        static $cache = null;

        if ($cache === null) {
            $cache = Branch::select('id', 'name', 'code', 'short_name')
                ->where('is_active', 1)
                ->orderBy('name')
                ->get()
                ->keyBy('code')           // Important for fast lookup
                ->toArray();
        }

        return $cache;
    }

    /**
     * Get Branch Name by Code
     */
    public static function getBranchName($code)
    {
        if (empty($code)) return 'N/A';
        $branches = self::getBranches();
        return $branches[$code]['name'] ?? 'N/A';
    }

    /**
     * Get Locations by Branch Code
     */
    public static function getLocations($branchCode = null)
    {
        $query = Location::where('is_active', 1);

        if ($branchCode) {
            $query->where('branch_code', $branchCode);
        }

        return $query->orderBy('name')->get()->toArray();
    }

    /**
     * Get All Active Financiers
     */
    public static function getFinanciers()
    {
        return collect(XlFinancier::select('id', 'name', 'short_name')
            ->where('status', 1)
            ->orderBy('name')
            ->get()
            ->toArray())
            ->map(fn($f) => (object) $f);
    }

    /**
     * Get All DSA Details
     */
    public static function getDSAs()
    {
        return collect(Xl_DSA_Master::select('id', 'name', 'mobile', 'email', 'dlocation')
            ->where('status', 1)        // Recommended to add this
            ->orderBy('name')
            ->get()
            ->toArray())
            ->map(function ($dsa) {
                return (object) [
                    'id'       => $dsa['id'],
                    'name'     => $dsa['name'],
                    'mobile'   => $dsa['mobile'],
                    'email'    => $dsa['email'],
                    'location' => $dsa['dlocation'],
                ];
            });
    }
    public static function getVehicleSegments()
    {
        return Segment::select('code', 'name')
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();
    }

    /**
     * Get Models by Segment Code
     */
    public static function getVehicleModels($segmentCode)
    {
        return VehicleModel::select('code', 'name')
            ->where('is_active', 1)
            ->where('segment_code', strtoupper(trim($segmentCode)))
            ->orderBy('name')
            ->get();
    }

    /**
     * Get Variants by Model Code
     */
    public static function getVehicleVariants($modelCode)
    {
        return Variant::select('id', 'code', 'custom_name as name', 'seating_capacity')
            ->where('is_active', 1)
            ->where('model_code', strtoupper(trim($modelCode)))
            ->orderBy('custom_name')
            ->get();
    }

    /**
     * Get Colors by Variant Code
     */
    public static function getVehicleColors($variantCode)
    {
        return Color::select('code', 'name', 'hex_code', 'variant_code') // ← add this
            ->where('is_active', 1)
            ->where('variant_code', strtoupper(trim($variantCode)))
            ->orderBy('name')
            ->get();
    }
}
