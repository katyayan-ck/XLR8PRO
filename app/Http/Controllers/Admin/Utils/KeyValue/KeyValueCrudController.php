<?php

namespace App\Http\Controllers\Admin\Utils\KeyValue;

use App\Http\Requests\KeyvalueRequest;
use App\Models\Utilities\KeyValue\Keyvalue;
use App\Models\Utilities\KeyValue\KeywordMaster;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

class KeyValueCrudController extends CrudController
{
    use CreateOperation;
    use ListOperation;
    use UpdateOperation;

    /**
     * No dedicated "keyvalue.*" permission exists in xlr8_iam_permissions —
     * reuses "settings.*", the closest sibling system-utility concept, same
     * pairing as SystemSettingCrudController.
     */
    public function setup()
    {
        CRUD::setModel(Keyvalue::class);
        CRUD::setRoute(config('backpack.base.route_prefix').'/utils/key-value');
        CRUD::setEntityNameStrings('key-value', 'key-values');
    }

    protected function setupListOperation()
    {
        if (! backpack_user()->can('UTL_SETTINGS_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view key values.');
        }

        $this->crud->setListView(
            'admin.utils.keyvalue.list'
        );
    }

    public function index()
    {
        if (! backpack_user()->can('UTL_SETTINGS_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view key values.');
        }

        $this->crud->setListView(
            'admin.utils.keyvalue.list'
        );

        $keyValues = Keyvalue::select([
            'id',
            'keyword_code',
            'code',
            'key',
            'value',
            'level',
            'status',
            'is_active',
        ])
            ->orderBy('id', 'desc')
            ->get();

        $gridData = $keyValues->map(function ($item, $index) {

            $mapped = $item->toArray();

            $mapped['serial_no'] = $index + 1;

            $mapped['status'] =
                $item->status == 1
                ? 'Active'
                : 'Inactive';

            $mapped['is_active'] =
                $item->is_active
                ? 'Active'
                : 'Inactive';

            $editUrl =
                backpack_url(
                    "utils/key-value/{$item->id}/edit"
                );

            $mapped['action'] = '
        <div class="d-flex justify-content-center gap-2">
            <a href="'.$editUrl.'"
               class="btn btn-sm btn-primary py-1 px-2">
                Edit
            </a>
        </div>';

            return $mapped;
        })->values();

        return view(
            'admin.utils.keyvalue.list',
            [
                'title' => 'Key Values',

                'gridConfig' => [

                    'columns' => [

                        ['field' => 'serial_no', 'headerName' => 'S.No.'],
                        ['field' => 'keyword_code', 'headerName' => 'Keyword Code'],
                        ['field' => 'code', 'headerName' => 'Code'],
                        ['field' => 'key', 'headerName' => 'Key'],
                        ['field' => 'value', 'headerName' => 'Value'],
                        ['field' => 'details', 'headerName' => 'Details'],
                        ['field' => 'level', 'headerName' => 'Level'],
                        ['field' => 'status', 'headerName' => 'Status'],
                        ['field' => 'is_active', 'headerName' => 'Active'],
                        ['field' => 'action', 'headerName' => 'Action'],
                    ],

                    'data' => $gridData,
                ],
            ]
        );
    }

    public function create()
    {
        if (! backpack_user()->can('UTL_SETTINGS_MANAGE')) {
            abort(403, 'Unauthorized. You do not have permission to create key values.');
        }

        $this->crud->setCreateView(
            'admin.utils.keyvalue.create'
        );

        $keywordMasters =
            KeywordMaster::orderBy('keyword')
                ->get([
                    'code',
                    'keyword',
                ]);

        return view(
            'admin.utils.keyvalue.create',
            [
                'title' => 'Add Key Value',

                'keywordMasters' => $keywordMasters,
            ]
        );
    }

    public function store(KeyvalueRequest $request)
    {
        if (! backpack_user()->can('UTL_SETTINGS_MANAGE')) {
            abort(403, 'Unauthorized. You do not have permission to create key values.');
        }

        $validated = $request->validated();

        Keyvalue::create($validated);

        \Alert::success(
            'Key Value created successfully!'
        )->flash();

        return redirect(
            backpack_url('utils/key-value')
        );
    }

    public function edit($id)
    {
        if (! backpack_user()->can('UTL_SETTINGS_MANAGE')) {
            abort(403, 'Unauthorized. You do not have permission to edit key values.');
        }

        $this->crud->setEditView(
            'admin.utils.keyvalue.edit'
        );

        $keyValue =
            Keyvalue::findOrFail($id);

        $keywordMasters =
            KeywordMaster::orderBy('keyword')
                ->get([
                    'code',
                    'keyword',
                ]);

        return view(
            'admin.utils.keyvalue.edit',
            [
                'title' => 'Edit Key Value',

                'keyValue' => $keyValue,

                'keywordMasters' => $keywordMasters,
            ]
        );
    }

    public function update(
        KeyvalueRequest $request,
        $id
    ) {
        if (! backpack_user()->can('UTL_SETTINGS_MANAGE')) {
            abort(403, 'Unauthorized. You do not have permission to edit key values.');
        }

        $keyValue =
            Keyvalue::findOrFail($id);

        $validated = $request->validated();

        $keyValue->update(
            $validated
        );

        \Alert::success(
            'Key Value updated successfully!'
        )->flash();

        return redirect(
            backpack_url('utils/key-value')
        );
    }
}
