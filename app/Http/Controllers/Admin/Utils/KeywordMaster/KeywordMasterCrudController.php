<?php

namespace App\Http\Controllers\Admin\Utils\KeywordMaster;

use App\Http\Requests\KeywordMasterRequest;
use App\Models\Utilities\KeyValue\KeywordMaster;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

class KeywordMasterCrudController extends CrudController
{
    use CreateOperation;
    use ListOperation;
    use UpdateOperation;

    /**
     * No dedicated "keyword_master.*" permission exists — reuses
     * "settings.*", same pairing as KeyValueCrudController.
     */
    public function setup()
    {
        CRUD::setModel(KeywordMaster::class);
        CRUD::setRoute(config('backpack.base.route_prefix').'/utils/keyword-master');
        CRUD::setEntityNameStrings('keyword', 'keywords');
    }

    protected function setupListOperation()
    {
        if (! backpack_user()->can('UTL_SETTINGS_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view keywords.');
        }

        $this->crud->setListView('admin.utils.keyword-master.list');
    }

    public function index()
    {
        if (! backpack_user()->can('UTL_SETTINGS_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view keywords.');
        }

        $this->crud->setListView('admin.utils.keyword-master.list');

        $keywords = KeywordMaster::select([
            'id',
            'code',
            'keyword',
            'description',
            'details',
            'status',
            'is_recursive',
            'is_active',
        ])
            ->orderBy('id', 'desc')
            ->get();

        $gridData = $keywords->map(function ($item, $index) {

            $mapped = $item->toArray();

            $mapped['serial_no'] = $index + 1;

            $mapped['is_active'] =
                $item->is_active ? 'Active' : 'Inactive';

            $mapped['is_recursive'] =
                $item->is_recursive ? 'Yes' : 'No';

            $mapped['status'] =
                $item->status == 1
                ? 'Active'
                : 'Inactive';

            $editUrl =
                backpack_url("utils/keyword-master/{$item->id}/edit");

            $mapped['action'] = '
            <div class="d-flex justify-content-center gap-2">
                <a href="'.$editUrl.'"
                   class="btn btn-sm btn-primary py-1 px-2">
                    Edit
                </a>
            </div>';

            return $mapped;
        })->values();

        return view('admin.utils.keyword-master.list', [
            'title' => 'Keyword Master',
            'gridConfig' => [
                'columns' => [
                    ['field' => 'serial_no', 'headerName' => 'S.No.'],
                    ['field' => 'code', 'headerName' => 'Code'],
                    ['field' => 'keyword', 'headerName' => 'Keyword'],
                    ['field' => 'description', 'headerName' => 'Description'],
                    ['field' => 'details', 'headerName' => 'Details'],
                    ['field' => 'status', 'headerName' => 'Status'],
                    ['field' => 'is_recursive', 'headerName' => 'Recursive'],
                    ['field' => 'is_active', 'headerName' => 'Active'],
                    ['field' => 'action', 'headerName' => 'Action'],
                ],
                'data' => $gridData,
            ],
        ]);
    }

    public function create()
    {
        if (! backpack_user()->can('UTL_SETTINGS_MANAGE')) {
            abort(403, 'Unauthorized. You do not have permission to create keywords.');
        }

        $this->crud->setCreateView(
            'admin.utils.keyword-master.create'
        );

        return view(
            'admin.utils.keyword-master.create',
            [
                'title' => 'Add Keyword',
            ]
        );
    }

    public function store(KeywordMasterRequest $request)
    {
        if (! backpack_user()->can('UTL_SETTINGS_MANAGE')) {
            abort(403, 'Unauthorized. You do not have permission to create keywords.');
        }

        $validated = $request->validated();

        KeywordMaster::create($validated);

        \Alert::success(
            'Keyword created successfully!'
        )->flash();

        return redirect(
            backpack_url('utils/keyword-master')
        );
    }

    public function edit($id)
    {
        if (! backpack_user()->can('UTL_SETTINGS_MANAGE')) {
            abort(403, 'Unauthorized. You do not have permission to edit keywords.');
        }

        $this->crud->setEditView(
            'admin.utils.keyword-master.edit'
        );

        $keyword =
            KeywordMaster::findOrFail($id);

        return view(
            'admin.utils.keyword-master.edit',
            [
                'title' => 'Edit Keyword',

                'keywordMaster' => $keyword,
            ]
        );
    }

    public function update(
        KeywordMasterRequest $request,
        $id
    ) {
        if (! backpack_user()->can('UTL_SETTINGS_MANAGE')) {
            abort(403, 'Unauthorized. You do not have permission to edit keywords.');
        }

        $keyword =
            KeywordMaster::findOrFail($id);

        $validated = $request->validated();

        $keyword->update($validated);

        \Alert::success(
            'Keyword updated successfully!'
        )->flash();

        return redirect(
            backpack_url('utils/keyword-master')
        );
    }
}
