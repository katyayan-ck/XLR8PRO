<?php

namespace App\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use App\Helpers\XCommonHelper;
use App\Helpers\XpricingHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SpareRequestCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;

    public function setup()
    {
        CRUD::setRoute(config('backpack.base.route_prefix') . '/spare-request');
        CRUD::setEntityNameStrings('Spare Request', 'Spare Requests');
    }

    protected function setupListOperation()
    {
        $this->crud->setListView('admin.spare-request.list');
    }

    protected function setupCreateOperation()
    {
        $this->crud->setCreateView('admin.spare-request.create');
        $this->data['branch'] = XCommonHelper::getServiceBranch();
        $this->data['models'] = XpricingHelper::getModelsX();
    }

    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();
    }

    // Fetch Parts for Autocomplete
    public function fetchParts(Request $request)
    {
        $query = $request->get('query');
        $type  = $request->get('type');

        if (empty($query) || !in_array($type, ['part_no', 'name'])) {
            return response()->json([]);
        }

        $parts = DB::table('xlr8_spare_master')
            ->where($type, 'LIKE', "%{$query}%")
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->select('id', 'part_no', 'name')
            ->limit(15)
            ->get();

        return response()->json($parts);
    }
    // Add this method in SpareRequestCrudController
    public function data()
    {
        $records = DB::table('xlr8_spare_request as req')
            ->select([
                'req.id',
                'req.created_at as posting_date',
                'branch.name as branch_name',
                'req.srv_vh_cat_id as service_category',
                'req.workshop_type_id as workshop_type',
                'req.model',
                'req.variant',
                'req.cust_name',
                'req.cust_mobile',
                'req.regn_no',
                'req.ro_number',
                'req.ro_date',
                DB::raw('DATEDIFF(CURRENT_DATE, req.ro_date) as ro_age'),
                DB::raw('COUNT(details.id) as parts_count'),
                DB::raw('SUM(details.req_quan) as parts_qty'),
                'req.remark'
            ])
            ->leftJoin('xlr8_spare_req_details as details', 'req.id', '=', 'details.spare_req_id')
            ->leftJoin('branches as branch', 'req.srv_brnch_id', '=', 'branch.id')
            // ❌ REMOVE THIS if column not exists
            // ->whereNull('req.deleted_at')
            ->groupBy(
                'req.id',
                'req.created_at',
                'branch.name',
                'req.srv_vh_cat_id',
                'req.workshop_type_id',
                'req.model',
                'req.variant',
                'req.cust_name',
                'req.cust_mobile',
                'req.regn_no',
                'req.ro_number',
                'req.ro_date',
                'req.remark'
            )
            ->orderBy('req.created_at', 'desc')
            ->get();

        $gridData = $records->map(function ($item, $index) {
            return [
                'serial_no'        => $index + 1,
                'posting_date'     => $item->posting_date ? \Carbon\Carbon::parse($item->posting_date)->format('d-m-Y') : '',
                'req_no'           => 'SR' . str_pad($item->id, 6, '0', STR_PAD_LEFT),
                'branch_name'      => $item->branch_name ?? '—',
                'service_category' => $item->service_category,
                'workshop_type'    => $item->workshop_type,
                'model'            => $item->model,
                'variant'          => $item->variant,
                'cust_name'        => $item->cust_name,
                'cust_mobile'      => $item->cust_mobile,
                'regn_no'          => $item->regn_no,
                'ro_number'        => $item->ro_number,
                'ro_date'          => $item->ro_date,
                'ro_age'           => $item->ro_age,
                'parts_count'      => $item->parts_count,
                'parts_qty'        => $item->parts_qty,
                'remark'           => $item->remark ?? '—',
                'action'           => '
                <div class="d-flex gap-2">
                    <a href="' . backpack_url('spare-request/' . $item->id) . '" class="btn btn-sm btn-info">View</a>
                    <a href="' . backpack_url('spare-request/' . $item->id . '/edit') . '" class="btn btn-sm btn-primary">Edit</a>
                </div>'
            ];
        });

        return response()->json($gridData);
    }
}
