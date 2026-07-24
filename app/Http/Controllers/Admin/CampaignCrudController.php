<?php

namespace App\Http\Controllers\Admin;

use Carbon\Carbon;
use App\Models\CRM\Campaign;
use App\Services\OrgService;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CampaignCrudController extends CrudController
{
    public function setup()
    {
        $this->crud->setModel(Campaign::class);

        $this->crud->setRoute(backpack_url('campaigns'));

        $this->crud->setEntityNameStrings(
            'Campaign',
            'Campaigns'
        );
    }

    public function index()
    {
        $this->crud->setListView('admin.campaign.list');

        $campaigns = Campaign::with([
            'segment',
            'model',
        ])
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
            ->latest()
            ->get();

        $gridData = $campaigns->map(function ($campaign, $index) {

            $mapped = $campaign->toArray();

            $mapped['serial_no'] = $index + 1;

            $mapped['segment_name'] =
                $campaign->segment?->name ?? '—';

            $mapped['model_name'] =
                $campaign->model?->name ?? '—';

            $mapped['activity_name'] =
                OrgService::getKeyValueByCode($campaign->activity_code)?->value ?? '—';

            $mapped['branch_name'] =
                OrgService::branchName($campaign->branch_code);

            $mapped['location_name'] =
                OrgService::locationName($campaign->location_code);

            $mapped['start_date'] = $campaign->start_date
                ? Carbon::parse($campaign->start_date)->format('d-m-Y')
                : '-';

            $mapped['end_date'] = $campaign->end_date
                ? Carbon::parse($campaign->end_date)->format('d-m-Y')
                : '-';

            $editUrl = backpack_url("campaign/{$campaign->id}/edit");

            $mapped['action'] = '
            <div class="d-flex justify-content-center gap-2">
                <a href="' . $editUrl . '" class="btn btn-sm btn-primary">
                    Edit
                </a>
            </div>
        ';

            return $mapped;
        })->values();

        return view('admin.campaign.list', [

            'title' => 'Campaign List',

            'gridConfig' => [

                'columns' => [

                    [
                        'field' => 'serial_no',
                        'headerName' => 'S.No.'
                    ],

                    [
                        'field' => 'name',
                        'headerName' => 'Activity Name'
                    ],

                    [
                        'field' => 'segment_name',
                        'headerName' => 'Segment'
                    ],

                    [
                        'field' => 'model_name',
                        'headerName' => 'Model'
                    ],

                    [
                        'field' => 'activity_name',
                        'headerName' => 'Activity Type'
                    ],

                    [
                        'field' => 'start_date',
                        'headerName' => 'Start Date'
                    ],

                    [
                        'field' => 'end_date',
                        'headerName' => 'End Date'
                    ],

                    [
                        'field' => 'branch_name',
                        'headerName' => 'Dealer Branch'
                    ],

                    [
                        'field' => 'location_name',
                        'headerName' => 'Dealer Location'
                    ],

                    [
                        'field' => 'action',
                        'headerName' => 'Action'
                    ],

                ],

                'data' => $gridData,

            ],

        ]);
    }

    public function create()
    {
        $this->crud->setCreateView('admin.campaign.create');

        $data = [];

        $data['title'] = 'Add Campaign';

        $data['segments'] = OrgService::segments();

        $data['models'] = [];

        $data['branches'] = OrgService::branches();

        $data['locations'] = [];

        $data['activity_types'] =
            OrgService::keywordValueByCode('ACTIVITY_TYPE');

        return view(
            'admin.campaign.create',
            $data
        );
    }

    public function store(Request $request)
    {
        $campaign = new Campaign();

        $request->validate([

            'name' => 'required',

            'segment_code' => 'required',

            'model_code' => 'required',

            'activity_code' => 'required',

            'start_date' => 'required|date',

            'end_date' => 'required|date',

            'branch_code' => 'required',

            'location_code' => 'required',

        ]);

        $campaign->fill($request->all());

        $campaign->created_by = backpack_user()->id;

        $campaign->save();

        \Alert::success('Campaign created successfully.')->flash();

        return redirect()->route('campaign.index');
    }

    public function update(Request $request, $id)
    {
        $campaign = Campaign::findOrFail($id);

        $request->validate([

            'name' => 'required',

            'segment_code' => 'required',

            'model_code' => 'required',

            'activity_code' => 'required',

            'start_date' => 'required|date',

            'end_date' => 'required|date',

            'branch_code' => 'required',

            'location_code' => 'required',

        ]);

        $campaign->fill($request->all());

        $campaign->updated_by = backpack_user()->id;

        $campaign->save();

        \Alert::success('Campaign updated successfully.')->flash();

        return redirect()->route('campaign.index');
    }

    public function edit($id)
    {
        $this->crud->setEditView('admin.campaign.create');

        $campaign = Campaign::findOrFail($id);

        return view('admin.campaign.create', [

            'title' => 'Edit Campaign',

            'campaign' => $campaign,

            'segments' => OrgService::segments(),

            'models' => OrgService::models($campaign->segment_code),

            'branches' => OrgService::branches(),

            'locations' => OrgService::locations(
                $campaign->branch_code
            ),

            'activity_types' => OrgService::keywordValueByCode('ACTIVITY_TYPE'),

        ]);
    }

    public function destroy($id)
    {
        $campaign = Campaign::findOrFail($id);

        $campaign->deleted_by = backpack_user()->id;

        $campaign->save();

        $campaign->delete();

        return response()->json([
            'success' => true,
            'message' => 'Campaign deleted successfully.'
        ]);
    }
    public function getModels($segmentCode)
    {
        return response()->json(
            OrgService::models($segmentCode)
        );
    }

    public function getLocations($branchCode)
    {
        return response()->json(
            OrgService::locations($branchCode)
        );
    }

}