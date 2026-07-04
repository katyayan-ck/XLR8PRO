<?php

namespace App\Models\Module\Booking;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use App\Models\BaseModel;

class XVehicleStock extends BaseModel
{
	use SoftDeletes;

	protected $table = 'xlr8_booking_stock_master';
	protected $fillable = [];
	protected $guarded = ['id'];

	public function vehicle()
	{
		return $this->belongsTo(XVehicleMaster::class, 'model_code', 'code');
	}

	public function branch()
	{
		return $this->belongsTo(Branches::class, 'branch_id', 'id');
	}

	public function location()
	{
		return $this->belongsTo(Location::class, 'location_id', 'id');
	}

	public static function getDynamicStockCounts($vinYear = null, $status = null)
	{
		$query = self::query()->whereNull('deleted_at')
			->whereHas('vehicle', function ($q) {
				$q->whereNull('deleted_at')->where('status', 1);
			})
			->where('damage', '!=', 1); // Exclude damaged vehicles

		if ($vinYear === '2024') {
			$query->where('chassis_no', 'LIKE', 'R%');
		} elseif ($vinYear === '2025') {
			$query->where('chassis_no', 'LIKE', 'S%');
		}

		if ($status) {
			if ($status === 'damage') {
				$query->where('damage', 1);
			} else {
				$query->where('v_status', $status)->where('damage', '!=', 1);
			}
		}

		$branches = Branches::whereNull('deleted_at')->pluck('abbr', 'id')->toArray();
		$locations = Location::whereNull('deleted_at')->pluck('abbr', 'id')->toArray();

		$branchColumns = [];
		foreach ($branches as $id => $abbr) {
			$sanitizedAbbr = str_replace([' ', '-'], '_', strtolower($abbr));
			$branchColumns["stock_branch_$sanitizedAbbr"] = DB::raw("COUNT(DISTINCT CASE WHEN xlr8_booking_stock_master.branch_id = $id THEN xlr8_booking_stock_master.id END)");
		}

		$locationColumns = [];
		foreach ($locations as $id => $abbr) {
			$sanitizedAbbr = str_replace([' ', '-'], '_', strtolower($abbr));
			$locationColumns["stock_location_$sanitizedAbbr"] = DB::raw("COUNT(DISTINCT CASE WHEN xlr8_booking_stock_master.location_id = $id THEN xlr8_booking_stock_master.id END)");
		}

		return $query->select([
			DB::raw('(SELECT value FROM bmpl_enum_master WHERE id = xcelr8_vehicle_master.segment_id) as segment'),
			'xcelr8_vehicle_master.custom_model as model',
			'xcelr8_vehicle_master.custom_variant as variant',
			'xcelr8_vehicle_master.color',
			DB::raw('COUNT(DISTINCT xlr8_booking_stock_master.id) as stock_total'),
			DB::raw('COUNT(DISTINCT CASE WHEN xlr8_booking_stock_master.location_id IS NULL OR xlr8_booking_stock_master.location_id = 0 THEN xlr8_booking_stock_master.id END) as stock_other'),
			DB::raw('MAX(DATEDIFF(CURDATE(), xlr8_booking_stock_master.oem_invoice_date)) as stock_max_age'),
			DB::raw('COUNT(DISTINCT CASE WHEN DATEDIFF(CURDATE(), xlr8_booking_stock_master.oem_invoice_date) > 60 THEN xlr8_booking_stock_master.id END) as stock_older_than_60_days'),
			...$branchColumns,
			...$locationColumns
		])
			->join('xcelr8_vehicle_master', 'xlr8_booking_stock_master.model_code', '=', 'xcelr8_vehicle_master.code')
			->groupBy('segment', 'xcelr8_vehicle_master.custom_model', 'xcelr8_vehicle_master.custom_variant', 'xcelr8_vehicle_master.color')
			->get();
	}

	public static function getStockDamageCounts($vinYear = null)
	{
		$query = self::query()->whereNull('deleted_at')
			->whereHas('vehicle', function ($q) {
				$q->whereNull('deleted_at')->where('status', 1);
			})
			->where('damage', 1);

		if ($vinYear === '2024') {
			$query->where('chassis_no', 'LIKE', 'R%');
		} elseif ($vinYear === '2025') {
			$query->where('chassis_no', 'LIKE', 'S%');
		}

		$branches = Branches::whereNull('deleted_at')->pluck('abbr', 'id')->toArray();
		$locations = Location::whereNull('deleted_at')->pluck('abbr', 'id')->toArray();

		$branchColumns = [];
		foreach ($branches as $id => $abbr) {
			$sanitizedAbbr = str_replace([' ', '-'], '_', strtolower($abbr));
			$branchColumns["stock_branch_damage_$sanitizedAbbr"] = DB::raw("COUNT(DISTINCT CASE WHEN xlr8_booking_stock_master.branch_id = $id THEN xlr8_booking_stock_master.id END)");
		}

		$locationColumns = [];
		foreach ($locations as $id => $abbr) {
			$sanitizedAbbr = str_replace([' ', '-'], '_', strtolower($abbr));
			$locationColumns["stock_location_damage_$sanitizedAbbr"] = DB::raw("COUNT(DISTINCT CASE WHEN xlr8_booking_stock_master.location_id = $id THEN xlr8_booking_stock_master.id END)");
		}

		return $query->select([
			DB::raw('(SELECT value FROM bmpl_enum_master WHERE id = xcelr8_vehicle_master.segment_id) as segment'),
			'xcelr8_vehicle_master.custom_model as model',
			'xcelr8_vehicle_master.custom_variant as variant',
			'xcelr8_vehicle_master.color',
			DB::raw('COUNT(DISTINCT xlr8_booking_stock_master.id) as stock_damage_total'),
			DB::raw('COUNT(DISTINCT CASE WHEN xlr8_booking_stock_master.location_id IS NULL OR xlr8_booking_stock_master.location_id = 0 THEN xlr8_booking_stock_master.id END) as stock_damage_other'),
			...$branchColumns,
			...$locationColumns
		])
			->join('xcelr8_vehicle_master', 'xlr8_booking_stock_master.model_code', '=', 'xcelr8_vehicle_master.code')
			->groupBy('segment', 'xcelr8_vehicle_master.custom_model', 'xcelr8_vehicle_master.custom_variant', 'xcelr8_vehicle_master.color')
			->get();
	}
}
