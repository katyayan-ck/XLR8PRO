<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SpareOrderingreportController extends Controller
{
    public function index()
    {
        return view('admin.spare-request.orderingreport');
    }

    public function data()
    {
        $records = $this->getOrderingReportData();

        $gridData = $records->map(function ($item, $index) {
            $physical   = (int)($item->physical_stock_qty ?? 0);
            $transit    = (int)($item->mat_in_transit_qty ?? 0);
            $backOrder  = (int)($item->back_order_qty ?? 0);
            $totalStock = $physical + $transit + $backOrder;

            $consumption = (int)($item->total_consumption ?? 0);
            $pendingReq  = (int)($item->total_required_qty ?? 0);

            // Old logic jaisa calculation
            $netRequirement = max(0, $pendingReq + $consumption - $totalStock);
            $toOrderSuggested = $netRequirement;   // agar MOQ chahiye to baad mein add kar denge

            $orderValue = $toOrderSuggested * (float)($item->order_price ?? 0);

            return [
                'serial_no'           => $index + 1,
                'part_number'         => $item->part_no ?? '—',
                'part_description'    => $item->part_name ?? '—',
                'mrp'                 => number_format((float)($item->mrp ?? 0), 2),
                'ndp'                 => number_format((float)($item->order_price ?? 0), 2),
                'total_consumption'   => $consumption,
                'total_required_qty'  => $pendingReq,
                'physical_stock_qty'  => $physical,
                'mat_in_transit_qty'  => $transit,
                'back_order_qty'      => $backOrder,
                'total_stock_qty'     => $totalStock,
                'net_requirement'     => $netRequirement,
                'to_order_suggested'  => $toOrderSuggested,
                'order_value'         => number_format($orderValue, 2),
                'status'              => $this->getStatusHtml($netRequirement),
                'action'              => '
                    <div class="d-flex gap-2 justify-content-center">
                        <a href="#" class="btn btn-sm btn-primary py-1 px-2">View Details</a>
                    </div>'
            ];
        });

        return response()->json($gridData);
    }

    private function getOrderingReportData()
    {
        return DB::table('xlr8_spare_master as master')
            ->select([
                'master.id as part_id',
                'master.part_no',
                'master.name as part_name',
                'master.mrp',
                'master.order_price',

                DB::raw('COALESCE(consumption.total_consumption, 0) as total_consumption'),
                DB::raw('COALESCE(req.total_required_qty, 0) as total_required_qty'),

                DB::raw('COALESCE(stock.total_cls_qnty, 0) as physical_stock_qty'),
                DB::raw('COALESCE(transit.total_quantity, 0) as mat_in_transit_qty'),
                DB::raw('COALESCE(order_tbl.total_confirm_quan, 0) as back_order_qty'),
            ])
            ->leftJoinSub(
                DB::table('xlr8_spare_consumption')
                    ->select('part_id', DB::raw('SUM(iss_quan) as total_consumption'))
                    ->groupBy('part_id'),
                'consumption',
                'master.id',
                '=',
                'consumption.part_id'
            )
            ->leftJoinSub(
                DB::table('xlr8_spare_req_details as details')
                    ->join('xlr8_spare_request as req', 'details.spare_req_id', '=', 'req.id')
                    ->select('details.part_id', DB::raw('SUM(details.req_quan) as total_required_qty'))
                    ->whereNull('details.deleted_at')
                    ->where('req.status', '!=', 2)
                    ->where('details.status', '!=', 2)
                    ->groupBy('details.part_id'),
                'req',
                'master.id',
                '=',
                'req.part_id'
            )
            ->leftJoinSub(
                DB::table('xlr8_spare_stock')
                    ->select('part_id', DB::raw('SUM(cls_qnty) as total_cls_qnty'))
                    ->groupBy('part_id'),
                'stock',
                'master.id',
                '=',
                'stock.part_id'
            )
            ->leftJoinSub(
                DB::table('xlr8_spare_transit')
                    ->select('part_id', DB::raw('SUM(quantity) as total_quantity'))
                    ->groupBy('part_id'),
                'transit',
                'master.id',
                '=',
                'transit.part_id'
            )
            ->leftJoinSub(
                DB::table('xlr8_spare_order')
                    ->select('part_id', DB::raw('SUM(confirm_quan) as total_confirm_quan'))
                    ->groupBy('part_id'),
                'order_tbl',
                'master.id',
                '=',
                'order_tbl.part_id'
            )
            ->where('master.status', 1)
            ->whereNull('master.deleted_at')
            ->orderBy('master.part_no')
            ->get();
    }

    private function getStatusHtml($netRequirement)
    {
        if ($netRequirement > 0) {
            return 'Need Order';
        }
        return 'OK';
    }
}
