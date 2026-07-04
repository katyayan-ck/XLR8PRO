<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SparePartwiseController extends Controller
{
    public function index()
    {
        return view('admin.spare-request.partwise');
    }

    public function data()
    {
        $records = $this->getSparePartsData();

        $gridData = $records->map(function ($item, $index) {
            $balance = (int)($item->balance_qty ?? 0);
            $physical = (int)($item->physical_stock_qty ?? 0);
            $transit = (int)($item->mat_in_transit_qty ?? 0);

            return [
                'serial_no'             => $index + 1,
                'ro_age'                => $item->earliest_ro_date
                    ? Carbon::parse($item->earliest_ro_date)->diffInDays(now())
                    : 0,
                'part_number'           => $item->part_no ?? '—',
                'part_description'      => $item->part_name ?? '—',
                'total_required_qty'    => (int)($item->total_required_qty ?? 0),
                'total_ro_count'        => (int)($item->total_ro_count ?? 0),
                'physical_stock_qty'    => $physical,
                'mat_in_transit_qty'    => $transit,
                'back_order_qty'        => (int)($item->back_order_qty ?? 0),
                'total_stock_qty'       => (int)($item->total_stock_qty ?? 0),
                'allotted_qty'          => (int)($item->allotted_qty ?? 0),
                'issued_qty'            => (int)($item->issued_qty ?? 0),
                'returned_qty'          => (int)($item->returned_qty ?? 0),
                'balance_qty'           => $balance,
                'status'                => $this->getStatusHtml($balance, $physical, $transit),
                'action'                => '
                    <div class="d-flex gap-2 justify-content-center">
                        <a href="' . backpack_url("spare/partwise-allotment/{$item->part_id}") . '"
                           class="btn btn-sm btn-primary py-1 px-2">Allot</a>
                    </div>'
            ];
        });

        return response()->json($gridData);
    }

    private function getSparePartsData()
    {
        return DB::table('xlr8_spare_req_details as details')
            ->select([
                'details.part_id',
                'details.part_no',
                'details.part_name',

                DB::raw('SUM(details.req_quan) as total_required_qty'),
                DB::raw('COUNT(DISTINCT details.spare_req_id) as total_ro_count'),
                DB::raw('MIN(details.created_at) as earliest_ro_date'),

                // Workshop Breakup
                DB::raw('SUM(CASE WHEN req.workshop_type_id = 1 THEN details.req_quan ELSE 0 END) as workshop_req_qty'),
                DB::raw('COUNT(DISTINCT CASE WHEN req.workshop_type_id = 1 THEN details.spare_req_id END) as workshop_ro_count'),

                // Bodyshop Breakup
                DB::raw('SUM(CASE WHEN req.workshop_type_id = 2 THEN details.req_quan ELSE 0 END) as bodyshop_req_qty'),
                DB::raw('COUNT(DISTINCT CASE WHEN req.workshop_type_id = 2 THEN details.spare_req_id END) as bodyshop_ro_count'),

                // CS Count (agar alag ho to adjust kar sakte hain)
                DB::raw('COUNT(DISTINCT CASE WHEN req.workshop_type_id NOT IN (1,2) THEN details.spare_req_id END) as total_cs_count'),

                DB::raw('COALESCE(stock.total_cls_qnty, 0) as physical_stock_qty'),
                DB::raw('COALESCE(transit.total_quantity, 0) as mat_in_transit_qty'),
                DB::raw('COALESCE(order_tbl.total_confirm_quan, 0) as back_order_qty'),
                DB::raw('COALESCE(stock.total_cls_qnty, 0) + COALESCE(transit.total_quantity, 0) + COALESCE(order_tbl.total_confirm_quan, 0) as total_stock_qty'),
                DB::raw('COALESCE(master.allot_qnty, 0) as allotted_qty'),
                DB::raw('COALESCE(master.iss_qnty, 0) as issued_qty'),
                DB::raw('COALESCE(master.return_qnty, 0) as returned_qty'),
                DB::raw('COALESCE(stock.total_cls_qnty, 0) - COALESCE(master.allot_qnty, 0) as balance_qty')
            ])
            ->leftJoin('xlr8_spare_request as req', 'details.spare_req_id', '=', 'req.id')
            ->leftJoinSub(DB::table('xlr8_spare_stock')->select('part_id', DB::raw('SUM(cls_qnty) as total_cls_qnty'))->groupBy('part_id'), 'stock', 'details.part_id', '=', 'stock.part_id')
            ->leftJoinSub(DB::table('xlr8_spare_transit')->select('part_id', DB::raw('SUM(quantity) as total_quantity'))->groupBy('part_id'), 'transit', 'details.part_id', '=', 'transit.part_id')
            ->leftJoinSub(DB::table('xlr8_spare_order')->select('part_id', DB::raw('SUM(confirm_quan) as total_confirm_quan'))->groupBy('part_id'), 'order_tbl', 'details.part_id', '=', 'order_tbl.part_id')
            ->leftJoin('xlr8_spare_master as master', 'details.part_id', '=', 'master.id')
            ->whereNull('details.deleted_at')
            ->where('req.status', '!=', 2)
            ->where('details.status', '!=', 2)
            ->groupBy('details.part_id', 'details.part_no', 'details.part_name')
            ->orderByRaw('MIN(details.created_at) ASC')
            ->get();
    }

    private function getStatusHtml($balance, $physical, $transit)
    {
        if ($balance < 0) return '<span class="badge bg-danger">Critical</span>';
        if ($physical >= $balance) return '<span class="badge bg-success">Available</span>';
        if ($physical + $transit >= $balance) return '<span class="badge bg-warning">Partial</span>';
        return '<span class="badge bg-danger">Short</span>';
    }
}
