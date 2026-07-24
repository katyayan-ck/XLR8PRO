<?php

namespace App\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Illuminate\Http\Request;
use App\Models\Utilities\KeyValue\KeywordMaster;

class KeywordMasterCrudController extends CrudController
{
    use ListOperation;
    use CreateOperation;
    use UpdateOperation;


    public function setup()
    {
        CRUD::setModel(KeywordMaster::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/keyword-master');
        CRUD::setEntityNameStrings('keyword', 'keywords');
    }

    protected function setupListOperation()
    {
        $this->crud->setListView('admin.keyword_master.list');
    }

    public function index()
    {
        $this->crud->setListView('admin.keyword_master.list');

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
                backpack_url("keyword-master/{$item->id}/edit");

            $mapped['action'] = '
            <div class="d-flex justify-content-center gap-2">
                <a href="' . $editUrl . '"
                   class="btn btn-sm btn-primary py-1 px-2">
                    Edit
                </a>
            </div>';

            return $mapped;
        })->values();

        return view('admin.keyword_master.list', [
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
                    ['field' => 'action', 'headerName' => 'Action']
                ],
                'data' => $gridData
            ]
        ]);
    }

    public function create()
    {
        $this->crud->setCreateView(
            'admin.keyword_master.create'
        );

        return view(
            'admin.keyword_master.create',
            [
                'title' => 'Add Keyword'
            ]
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([

            'code' => [
                'required',
                'string',
                'min:3',
                'max:50',
                'unique:xlr8_utils_keyword_master,code'
            ],

            'keyword' => [
                'required',
                'string',
                'max:255',
                'unique:xlr8_utils_keyword_master,keyword'
            ],

            'description' => 'nullable|string|max:1000',

            'details' => 'nullable|string|max:5000',

            'extra_data' => 'nullable|array',

            'status' => [
                'required',
                'integer',
                'in:0,1'
            ],

            'is_recursive' => 'nullable|boolean',

            'is_active' => 'nullable|boolean',
        ]);

        KeywordMaster::create($validated);

        \Alert::success(
            'Keyword created successfully!'
        )->flash();

        return redirect(
            backpack_url('keyword-master')
        );
    }

    public function edit($id)
    {
        $this->crud->setEditView(
            'admin.keyword_master.edit'
        );

        $keyword =
            KeywordMaster::findOrFail($id);

        return view(
            'admin.keyword_master.edit',
            [
                'title' =>
                'Edit Keyword',

                'keywordMaster' =>
                $keyword
            ]
        );
    }

    public function update(
        Request $request,
        $id
    ) {
        $keyword =
            KeywordMaster::findOrFail($id);

        $validated =
            $request->validate([

                'code' => [
                    'required',
                    'string',
                    'min:3',
                    'max:50',
                    'unique:xlr8_utils_keyword_master,code,' . $id
                ],

                'keyword' => [
                    'required',
                    'string',
                    'max:255',
                    'unique:xlr8_utils_keyword_master,keyword,' . $id
                ],

                'description' => 'nullable|string|max:1000',

                'details' => 'nullable|string|max:5000',

                'extra_data' => 'nullable|array',

                'status' => [
                    'required',
                    'integer',
                    'in:0,1'
                ],

                'is_recursive' => 'nullable|boolean',

                'is_active' => 'nullable|boolean',
            ]);

        $keyword->update($validated);

        \Alert::success(
            'Keyword updated successfully!'
        )->flash();

        return redirect(
            backpack_url('keyword-master')
        );
    }
}
