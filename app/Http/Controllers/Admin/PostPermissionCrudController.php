<?php

namespace App\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Http\Request;
use App\Models\IAM\PostPermission;

class PostPermissionCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;

    public function setup()
    {
        CRUD::setModel(PostPermission::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/post-permission');
        CRUD::setEntityNameStrings('post permission', 'post permissions');
    }

    protected function setupListOperation()
    {
        $this->crud->setListView('admin.post-permission.list');
    }

    public function index()
    {
        $this->crud->setListView('admin.post-permission.list');

        $postPermissions = PostPermission::with(['post', 'permission'])
            ->select('id', 'post_id', 'permission_id', 'created_at')
            ->withoutGlobalScopes()
            ->latest()
            ->get();

        $gridData = $postPermissions->map(function ($item, $index) {
            $postDisplay = $item->post?->title
                ?? $item->post?->name
                ?? $item->post?->post_name
                ?? 'Post #' . $item->post_id;

            return [
                'serial_no'       => $index + 1,
                'post_name'       => $postDisplay,
                'permission_name' => $item->permission?->name ?? '—',
                'created_at'      => $item->created_at?->format('d-m-Y H:i'),
                'action' => '
                <div class="d-flex gap-2 justify-content-center">
                    <a href="' . backpack_url("post-permission/{$item->id}/edit") . '"
                       class="btn btn-sm btn-primary py-1 px-2" title="Edit">Edit</a>
                </div>
            '
            ];
        })->values();

        return view('admin.post-permission.list', [
            'title' => 'All Post Permissions',
            'gridConfig' => [
                'columns' => [
                    ['field' => 'serial_no',       'headerName' => 'S.No'],
                    ['field' => 'post_name',       'headerName' => 'Post'],
                    ['field' => 'permission_name', 'headerName' => 'Permission'],
                    ['field' => 'created_at',      'headerName' => 'Created At'],
                    ['field' => 'action',          'headerName' => 'Actions']
                ],
                'data' => $gridData
            ]
        ]);
    }

    public function create()
    {
        $this->crud->setCreateView('admin.post-permission.create');

        return view('admin.post-permission.create', [
            'title'       => 'Add New Post Permission',
            'posts' => \App\Models\IAM\Post::withoutGlobalScopes()->orderBy('id')->get(),          // Changed to 'id'
            'permissions' => \App\Models\IAM\Permission::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'post_id'       => 'required|exists:xlr8_iam_post,id',
            'permission_id' => 'required|exists:xlr8_iam_permissions,id',
        ]);

        PostPermission::create($validated);

        \Alert::success('Post Permission assigned successfully!')->flash();
        return redirect(backpack_url('post-permission'));
    }

    public function edit($id)
    {
        $this->crud->setEditView('admin.post-permission.edit');

        $postPermission = PostPermission::with(['post', 'permission'])
            ->withoutGlobalScopes()
            ->findOrFail($id);

        return view('admin.post-permission.edit', [
            'title'          => 'Edit Post Permission',
            'postPermission' => $postPermission,
            'posts'          => \App\Models\IAM\Post::orderBy('id')->get(),      // Changed to 'id'
            'permissions'    => \App\Models\IAM\Permission::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, $id)
    {
        $postPermission = PostPermission::withoutGlobalScopes()->findOrFail($id);

        $validated = $request->validate([
            'post_id'       => 'required|exists:xlr8_iam_post,id',
            'permission_id' => 'required|exists:xlr8_iam_permissions,id',
        ]);

        $postPermission->update($validated);

        \Alert::success('Post Permission updated successfully!')->flash();
        return redirect(backpack_url('post-permission'));
    }
}
