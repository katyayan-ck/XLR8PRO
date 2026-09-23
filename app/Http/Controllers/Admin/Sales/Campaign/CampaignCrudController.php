<?php

namespace App\Http\Controllers\Admin\Sales\Campaign;

use App\Http\Requests\CampaignRequest;
use App\Models\CRM\Campaign;
use App\Services\OrgService;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Carbon\Carbon;

/**
 * Campaign is a Sales-module resource (marketing activities tied to
 * Enquiries), namespaced under Admin\Sales per the menu_items.blade.php
 * module grouping, same reasoning as LeadSource/Lead.
 *
 * NOTE: like VehicleModelCrudController, this uses no Backpack Operation
 * traits at all and is wired via fully manual routes in
 * routes/backpack/core.php (not Route::crud() — that call is a pre-existing
 * no-op left in place, since it never registered any routes without a trait).
 * Preserved that shape exactly.
 *
 * No "campaign.*" permission existed in xlr8_iam_permissions before this
 * change. Per the user's standing direction, minted 4 new permissions
 * (campaign.view/create/edit/delete) following the app's `resource.action`
 * convention, matching all pre-existing rows (module_code/process_code left
 * NULL).
 */
class CampaignCrudController extends CrudController
{
    public function setup()
    {
        $this->crud->setModel(Campaign::class);
        $this->crud->setRoute(backpack_url('sales/campaign'));
        $this->crud->setEntityNameStrings('Campaign', 'Campaigns');
    }

    public function index()
    {
        if (! backpack_user()->can('SLS_CMPN_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view campaigns.');
        }

        $this->crud->setListView('admin.sales.campaign.list');

        $campaigns = Campaign::with(['segment', 'model'])
            ->select([
                'id',
                'name',
                'segment_code',
                'model_code',
                'activity_code',
                'start_date',
                'end_date',
                'branch_code',
                'location_code',
            ])
            ->latest()->get();

        $gridData = $campaigns->map(function ($campaign, $index) {
            $mapped = $campaign->toArray();

            $mapped['serial_no'] = $index + 1;
            $mapped['segment_name'] = $campaign->segment?->name ?? '—';
            $mapped['model_name'] = $campaign->model?->name ?? '—';
            $mapped['activity_name'] = OrgService::getKeyValueByCode($campaign->activity_code)?->value ?? '—';
            $mapped['branch_name'] = OrgService::branchName($campaign->branch_code);
            $mapped['location_name'] = OrgService::locationName($campaign->location_code);
            $mapped['start_date'] = $campaign->start_date ? Carbon::parse($campaign->start_date)->format('d-m-Y') : '-';
            $mapped['end_date'] = $campaign->end_date ? Carbon::parse($campaign->end_date)->format('d-m-Y') : '-';

            $editUrl = backpack_url("sales/campaign/{$campaign->id}/edit");

            $mapped['action'] = '
            <div class="d-flex justify-content-center gap-2">
                <a href="'.$editUrl.'" class="btn btn-sm btn-primary">
                    Edit
                </a>
            </div>
        ';

            return $mapped;
        })->values();

        return view('admin.sales.campaign.list', [
            'title' => 'Campaign List',
            'gridConfig' => [
                'columns' => [
                    ['field' => 'serial_no',     'headerName' => 'S.No.'],
                    ['field' => 'name',          'headerName' => 'Activity Name'],
                    ['field' => 'segment_name',  'headerName' => 'Segment'],
                    ['field' => 'model_name',    'headerName' => 'Model'],
                    ['field' => 'activity_name', 'headerName' => 'Activity Type'],
                    ['field' => 'start_date',    'headerName' => 'Start Date'],
                    ['field' => 'end_date',      'headerName' => 'End Date'],
                    ['field' => 'action',        'headerName' => 'Action'],
                ],
                'data' => $gridData,
            ],
        ]);
    }

    public function create()
    {
        if (! backpack_user()->can('SLS_CMPN_CREATE')) {
            abort(403, 'Unauthorized. You do not have permission to create campaigns.');
        }

        $this->crud->setCreateView('admin.sales.campaign.create');

        return view('admin.sales.campaign.create', [
            'title' => 'Add Campaign',
            'segments' => OrgService::segments(),
            'models' => [],
            'branches' => OrgService::branches(),
            'locations' => [],
            'activity_types' => OrgService::keywordValueByCode('ACTIVITY_TYPE'),
        ]);
    }

    public function store(CampaignRequest $request)
    {
        if (! backpack_user()->can('SLS_CMPN_CREATE')) {
            abort(403, 'Unauthorized. You do not have permission to create campaigns.');
        }

        $campaign = new Campaign;
        $campaign->fill($request->validated());
        $campaign->forever = $request->input('forever', 0);
        $campaign->created_by = backpack_user()->id;
        $campaign->save();

        \Alert::success('Campaign created successfully.')->flash();

        return redirect()->route('campaign.index');
    }

    public function edit($id)
    {
        if (! backpack_user()->can('SLS_CMPN_EDIT')) {
            abort(403, 'Unauthorized. You do not have permission to edit campaigns.');
        }

        $this->crud->setEditView('admin.sales.campaign.create');

        $campaign = Campaign::findOrFail($id);

        return view('admin.sales.campaign.create', [
            'title' => 'Edit Campaign',
            'campaign' => $campaign,
            'segments' => OrgService::segments(),
            'models' => OrgService::models($campaign->segment_code),
            'branches' => OrgService::branches(),
            'locations' => OrgService::locations($campaign->branch_code),
            'activity_types' => OrgService::keywordValueByCode('ACTIVITY_TYPE'),
        ]);
    }

    public function update(CampaignRequest $request, $id)
    {
        if (! backpack_user()->can('SLS_CMPN_EDIT')) {
            abort(403, 'Unauthorized. You do not have permission to edit campaigns.');
        }

        $campaign = Campaign::findOrFail($id);
        $campaign->fill($request->validated());
        $campaign->forever = $request->input('forever', 0);
        $campaign->updated_by = backpack_user()->id;
        $campaign->save();

        \Alert::success('Campaign updated successfully.')->flash();

        return redirect()->route('campaign.index');
    }

    public function destroy($id)
    {
        if (! backpack_user()->can('SLS_CMPN_DELETE')) {
            abort(403, 'Unauthorized. You do not have permission to delete campaigns.');
        }

        $campaign = Campaign::findOrFail($id);
        $campaign->deleted_by = backpack_user()->id;
        $campaign->save();
        $campaign->delete();

        return response()->json([
            'success' => true,
            'message' => 'Campaign deleted successfully.',
        ]);
    }

    public function getModels($segmentCode)
    {
        return response()->json(OrgService::models($segmentCode));
    }

    public function getLocations($branchCode)
    {
        return response()->json(OrgService::locations($branchCode));
    }
}
