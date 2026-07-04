<?php

namespace App\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Http\Request;
use App\Models\Core\GraphNode;

class GraphNodeCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;

    public function setup()
    {
        CRUD::setModel(GraphNode::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/graph-node');
        CRUD::setEntityNameStrings('graph node', 'graph nodes');
    }

    protected function setupListOperation()
    {
        $this->crud->setListView('admin.graph-node.list');
    }

    public function index()
    {
        $this->crud->setListView('admin.graph-node.list');

        $nodes = GraphNode::with('user')
            ->withoutGlobalScopes()
            ->latest()
            ->get();

        $gridData = $nodes->map(function ($node, $index) {
            $postDisplay = $node->user?->name ?? '—';
            $attrs = is_string($node->attributes)
                ? $node->attributes
                : json_encode($node->attributes, JSON_UNESCAPED_UNICODE);

            return [
                'serial_no'    => $index + 1,
                'user_name'    => $postDisplay,
                'role'         => $node->role,
                'attributes'   => strlen($attrs) > 100 ? substr($attrs, 0, 100) . '...' : $attrs,
                'created_at'   => $node->created_at?->format('d-m-Y H:i'),
                'action' => '
                    <div class="d-flex gap-2 justify-content-center">
                        <a href="' . backpack_url("graph-node/{$node->id}/edit") . '"
                           class="btn btn-sm btn-primary py-1 px-2" title="Edit">Edit</a>
                    </div>
                '
            ];
        })->values();

        return view('admin.graph-node.list', [
            'title' => 'All Graph Nodes',
            'gridConfig' => [
                'columns' => [
                    ['field' => 'serial_no',  'headerName' => 'S.No'],
                    ['field' => 'user_name',  'headerName' => 'User'],
                    ['field' => 'role',       'headerName' => 'Role'],
                    ['field' => 'attributes', 'headerName' => 'Attributes'],
                    ['field' => 'created_at', 'headerName' => 'Created At'],
                    ['field' => 'action',     'headerName' => 'Actions']
                ],
                'data' => $gridData
            ]
        ]);
    }

    public function create()
    {
        $this->crud->setCreateView('admin.graph-node.create');
        return view('admin.graph-node.create', ['title' => 'Add New Graph Node']);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id'    => 'required|exists:users,id',
            'role'       => 'required|in:person,role,department',
            'attributes' => 'nullable|string',
        ]);

        // Convert JSON string to array/object if valid
        $attributes = !empty($validated['attributes'])
            ? json_decode($validated['attributes'], true)
            : (object)[];

        GraphNode::create([
            'user_id'    => $validated['user_id'],
            'role'       => $validated['role'],
            'attributes' => $attributes,
        ]);

        \Alert::success('Graph Node created successfully!')->flash();
        return redirect(backpack_url('graph-node'));
    }

    public function edit($id)
    {
        $this->crud->setEditView('admin.graph-node.edit');

        $node = GraphNode::with('user')->withoutGlobalScopes()->findOrFail($id);

        return view('admin.graph-node.edit', [
            'title' => 'Edit Graph Node',
            'node'  => $node
        ]);
    }

    public function update(Request $request, $id)
    {
        $node = GraphNode::withoutGlobalScopes()->findOrFail($id);

        $validated = $request->validate([
            'user_id'    => 'required|exists:users,id',
            'role'       => 'required|in:person,role,department',
            'attributes' => 'nullable|string',
        ]);

        $attributes = !empty($validated['attributes'])
            ? json_decode($validated['attributes'], true)
            : (object)[];

        $node->update([
            'user_id'    => $validated['user_id'],
            'role'       => $validated['role'],
            'attributes' => $attributes,
        ]);

        \Alert::success('Graph Node updated successfully!')->flash();
        return redirect(backpack_url('graph-node'));
    }
}
