<?php

namespace App\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Http\Request;
use App\Models\Core\GraphEdge;

class GraphEdgeCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;

    public function setup()
    {
        CRUD::setModel(GraphEdge::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/graph-edge');
        CRUD::setEntityNameStrings('graph edge', 'graph edges');
    }

    protected function setupListOperation()
    {
        $this->crud->setListView('admin.graph-edge.list');
    }

    public function index()
    {
        $this->crud->setListView('admin.graph-edge.list');

        $edges = GraphEdge::with(['fromNode.user', 'toNode.user'])
            ->withoutGlobalScopes()
            ->latest()
            ->get();

        $gridData = $edges->map(function ($edge, $index) {
            return [
                'serial_no'     => $index + 1,
                'from_node'     => $edge->fromNode?->user?->name ?? 'Node #' . $edge->from_node_id,
                'to_node'       => $edge->toNode?->user?->name ?? 'Node #' . $edge->to_node_id,
                'type'          => $edge->type,
                'level'         => $edge->level ?? '—',
                'powers'        => $edge->powers ? substr($edge->powers, 0, 80) . '...' : '—',
                'created_at'    => $edge->created_at?->format('d-m-Y H:i'),
                'action' => '
                    <div class="d-flex gap-2 justify-content-center">
                        <a href="' . backpack_url("graph-edge/{$edge->id}/edit") . '"
                           class="btn btn-sm btn-primary py-1 px-2" title="Edit">Edit</a>
                    </div>
                '
            ];
        })->values();

        return view('admin.graph-edge.list', [
            'title' => 'All Graph Edges',
            'gridConfig' => [
                'columns' => [
                    ['field' => 'serial_no',   'headerName' => 'S.No'],
                    ['field' => 'from_node',   'headerName' => 'From Node'],
                    ['field' => 'to_node',     'headerName' => 'To Node'],
                    ['field' => 'type',        'headerName' => 'Type'],
                    ['field' => 'level',       'headerName' => 'Level'],
                    ['field' => 'powers',      'headerName' => 'Powers'],
                    ['field' => 'created_at',  'headerName' => 'Created At'],
                    ['field' => 'action',      'headerName' => 'Actions']
                ],
                'data' => $gridData
            ]
        ]);
    }

    public function create()
    {
        $this->crud->setCreateView('admin.graph-edge.create');
        return view('admin.graph-edge.create', ['title' => 'Add New Graph Edge']);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'from_node_id' => 'required|exists:graph_nodes,id',
            'to_node_id'   => 'required|exists:graph_nodes,id',
            'type'         => 'required|in:reports_to,approves',
            'level'        => 'nullable|integer',
            'powers'       => 'nullable|string',
        ]);

        GraphEdge::create($validated);

        \Alert::success('Graph Edge created successfully!')->flash();
        return redirect(backpack_url('graph-edge'));
    }

    public function edit($id)
    {
        $this->crud->setEditView('admin.graph-edge.edit');

        $edge = GraphEdge::with(['fromNode.user', 'toNode.user'])
            ->withoutGlobalScopes()
            ->findOrFail($id);

        return view('admin.graph-edge.edit', [
            'title' => 'Edit Graph Edge',
            'edge'  => $edge
        ]);
    }

    public function update(Request $request, $id)
    {
        $edge = GraphEdge::withoutGlobalScopes()->findOrFail($id);

        $validated = $request->validate([
            'from_node_id' => 'required|exists:graph_nodes,id',
            'to_node_id'   => 'required|exists:graph_nodes,id',
            'type'         => 'required|in:reports_to,approves',
            'level'        => 'nullable|integer',
            'powers'       => 'nullable|string',
        ]);

        $edge->update($validated);

        \Alert::success('Graph Edge updated successfully!')->flash();
        return redirect(backpack_url('graph-edge'));
    }
}
