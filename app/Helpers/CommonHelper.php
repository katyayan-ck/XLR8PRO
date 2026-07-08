<?php

namespace App\Helpers;

use App\Models\X_Branch;
use App\Models\Module\Finance\XlFinancier;
use App\Models\Module\Booking\Xl_DSA_Master;
use App\Models\Vehicle\Segment;
use App\Models\Vehicle\VehicleModel;
use App\Models\Vehicle\Variant;
use App\Models\Vehicle\Color;       
use App\Models\Admin\Branch;
use App\Models\Admin\Location;

class CommonHelper
{
  
    public static function getBranches()
    {
        static $cache = null;

        if ($cache === null) {
            $cache = Branch::select('id', 'name', 'code', 'short_name')
                ->where('is_active', 1)
                ->orderBy('name')
                ->get()
                ->keyBy('code')           
                ->toArray();
        }

        return $cache;
    }

    
    public static function getBranchName($code)
    {
        if (empty($code)) return 'N/A';
        $branches = self::getBranches();
        return $branches[$code]['name'] ?? 'N/A';
    }

   
    
    public static function getLocations($branchCode = null)
    {
        $query = Location::where('is_active', 1);

        if ($branchCode) {
            $query->where('branch_code', $branchCode);
        }

        return $query->orderBy('name')->get()->toArray();
    }

    
    public static function getFinanciers()
    {
        return collect(XlFinancier::select('id', 'name', 'short_name')
            ->where('status', 1)
            ->orderBy('name')
            ->get()
            ->toArray())
            ->map(fn($f) => (object) $f);
    }

   
    public static function getDSAs()
    {
        return collect(Xl_DSA_Master::select('id', 'name', 'mobile', 'email', 'dlocation')
            ->where('status', 1)        
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

  
    public static function getVehicleModels($segmentCode)
    {
        return VehicleModel::select('code', 'name')
            ->where('is_active', 1)
            ->where('segment_code', strtoupper(trim($segmentCode)))
            ->orderBy('name')
            ->get();
    }

    
    public static function getVehicleVariants($modelCode)
    {
        return Variant::select('id', 'code', 'custom_name as name', 'seating_capacity')
            ->where('is_active', 1)
            ->where('model_code', strtoupper(trim($modelCode)))
            ->orderBy('custom_name')
            ->get();
    }

    public static function getVehicleColors($variantCode)
    {
        return Color::select('code', 'name', 'hex_code', 'variant_code')
            ->where('is_active', 1)
            ->where('variant_code', strtoupper(trim($variantCode)))
            ->orderBy('name')
            ->get();
    }


}
