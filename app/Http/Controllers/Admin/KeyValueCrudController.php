<?php

namespace App\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use App\Models\Utilities\KeyValue\KeywordMaster;
use App\Models\Utilities\KeyValue\Keyvalue;
use Illuminate\Http\Request;


class KeyValueCrudController extends CrudController
{
    use ListOperation;
    use CreateOperation;
    use UpdateOperation;

    public function setup()
    {
        CRUD::setModel(\App\Models\Utilities\KeyValue\Keyvalue::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/keyvalue');
        CRUD::setEntityNameStrings('key-value', 'key-values');
    }

    protected function setupListOperation()
    {
        $this->crud->setListView(
            'admin.keyvalue.list'
        );
    }

    public function index()
    {
        $this->crud->setListView(
            'admin.keyvalue.list'
        );

        $keyValues = Keyvalue::select([
            'id',
            'keyword_code',
            'code',
            'key',
            'value',
            'level',
            'status',
            'is_active'
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
                    "keyvalue/{$item->id}/edit"
                );

            $mapped['action'] = '
        <div class="d-flex justify-content-center gap-2">
            <a href="' . $editUrl . '"
               class="btn btn-sm btn-primary py-1 px-2">
                Edit
            </a>
        </div>';

            return $mapped;
        })->values();

        return view(
            'admin.keyvalue.list',
            [
                'title' => 'Key Values',

                'gridConfig' => [

                    'columns' => [

                        ['field' => 'serial_no', 'headerName' => 'S.No'],
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

                    'data' => $gridData
                ]
            ]
        );
    }

    public function create()
    {
        $this->crud->setCreateView(
            'admin.keyvalue.create'
        );

        $keywordMasters =
            KeywordMaster::orderBy('keyword')
            ->get([
                'code',
                'keyword'
            ]);

        return view(
            'admin.keyvalue.create',
            [
                'title' => 'Add Key Value',

                'keywordMasters' =>
                $keywordMasters
            ]
        );
    }
    public function store(Request $request)
    {
        $validated =
            $request->validate([

                'keyword_code' => [
                    'required',
                    'exists:xlr8_utils_keyword_master,code'
                ],

                'code' => [
                    'required',
                    'string',
                    'max:150',
                    'unique:xlr8_utils_keyvalue,code'
                ],

                'key' => [
                    'nullable',
                    'string',
                    'max:255'
                ],

                'value' => [
                    'required',
                    'string'
                ],

                'details' => [
                    'nullable',
                    'string',
                    'max:5000'
                ],

                'parent_id' => [
                    'nullable',
                    'integer'
                ],

                'level' => [
                    'required',
                    'integer',
                    'min:0'
                ],

                'path' => [
                    'nullable',
                    'string'
                ],

                'extra_data' => [
                    'nullable',
                    'array'
                ],

                'status' => [
                    'required',
                    'integer',
                    'in:0,1'
                ],

                'is_active' => [
                    'nullable',
                    'boolean'
                ]
            ]);

        Keyvalue::create($validated);

        \Alert::success(
            'Key Value created successfully!'
        )->flash();

        return redirect(
            backpack_url('keyvalue')
        );
    }

    public function edit($id)
    {
        $this->crud->setEditView(
            'admin.keyvalue.edit'
        );

        $keyValue =
            Keyvalue::findOrFail($id);

        $keywordMasters =
            KeywordMaster::orderBy('keyword')
            ->get([
                'code',
                'keyword'
            ]);

        return view(
            'admin.keyvalue.edit',
            [
                'title' => 'Edit Key Value',

                'keyValue' => $keyValue,

                'keywordMasters' =>
                $keywordMasters
            ]
        );
    }

    public function update(
        Request $request,
        $id
    ) {
        $keyValue =
            Keyvalue::findOrFail($id);

        $validated =
            $request->validate([

                'keyword_code' => [
                    'required',
                    'exists:xlr8_utils_keyword_master,code'
                ],

                'code' => [
                    'required',
                    'string',
                    'max:150',
                    'unique:xlr8_utils_keyvalue,code,' . $id
                ],

                'key' => [
                    'nullable',
                    'string',
                    'max:255'
                ],

                'value' => [
                    'required',
                    'string'
                ],

                'details' => [
                    'nullable',
                    'string',
                    'max:5000'
                ],

                'parent_id' => [
                    'nullable',
                    'integer'
                ],

                'level' => [
                    'required',
                    'integer',
                    'min:0'
                ],

                'path' => [
                    'nullable',
                    'string'
                ],

                'extra_data' => [
                    'nullable',
                    'array'
                ],

                'status' => [
                    'required',
                    'integer',
                    'in:0,1'
                ],

                'is_active' => [
                    'nullable',
                    'boolean'
                ]
            ]);

        $keyValue->update(
            $validated
        );

        \Alert::success(
            'Key Value updated successfully!'
        )->flash();

        return redirect(
            backpack_url('keyvalue')
        );
    }
}
