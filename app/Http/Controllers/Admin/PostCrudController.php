<?php

namespace App\Http\Controllers\Admin;

use App\Models\IAM\Post;
use App\Models\IAM\PostOrgScope;
use App\Models\IAM\PostVehicleScope;
use App\Models\Admin\Branch;
use App\Models\Admin\Department;
use App\Models\Admin\Division;
use App\Models\Admin\Designation;
use App\Models\Admin\Location;
use App\Services\IAM\PostService;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PostCrudController extends CrudController
{
    protected PostService $postService;

    public function __construct(PostService $postService)
    {
        parent::__construct();
        $this->postService = $postService;
    }

    public function setup(): void
    {
        CRUD::setModel(Post::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/post');
        CRUD::setEntityNameStrings('post', 'posts');
    }

    protected function setupListOperation(): void
    {
        $this->crud->setListView('admin.post.list');
    }

    public function index()
    {
        $this->crud->hasAccessOrFail('list');

        $posts = Post::withoutGlobalScopes()
            ->with(['branch', 'department', 'designation', 'division', 'location'])
            ->withCount('currentEmployees')
            ->orderBy('id', 'desc')
            ->get();

        $gridData = $posts->map(function ($post, $index) {
            return [
                'id'               => $post->id,
                'serial_no'        => $index + 1,
                'post_code'        => $post->post_code,
                'desig_code'       => $post->desig_code,
                'designation'      => $post->designation?->name ?? '—',
                'branch'           => $post->branch?->name ?? '—',
                'department'       => $post->department?->name ?? '—',
                'division'         => $post->division?->name ?? '—',
                'location'         => $post->location?->name ?? '—',
                'max_occupants'    => $post->max_occupants,
                'current_occupants'=> $post->current_employees_count,
                'is_vacant'        => $post->is_vacant,
                'is_active'        => $post->is_active,
                'created_at'       => $post->created_at?->format('d-M-Y'),
            ];
        })->values();

        return view('admin.post.list', compact('gridData'));
    }

    public function create()
    {
        $this->crud->hasAccessOrFail('create');
        $this->crud->setCreateView('admin.post.create');

        $branches     = Branch::orderBy('name')->get();
        $departments  = Department::orderBy('name')->get();
        $divisions    = Division::orderBy('name')->get();
        $designations = Designation::active()->orderBy('name')->get();
        $locations    = Location::orderBy('name')->get();

        return view('admin.post.create', compact(
            'branches', 'departments', 'divisions', 'designations', 'locations'
        ));
    }

    public function store(Request $request)
    {
        $this->crud->hasAccessOrFail('create');

        $validated = $request->validate([
            'desig_code'    => 'required|string|exists:xlr8_admin_designation,code',
            'branch_code'   => 'nullable|string|exists:xlr8_admin_branch,code',
            'dept_code'     => 'nullable|string|exists:xlr8_admin_department,code',
            'div_code'      => 'nullable|string|exists:xlr8_admin_division,code',
            'loc_code'      => 'nullable|string|exists:xlr8_admin_location,code',
            'max_occupants' => 'integer|min:1|max:10',
            'is_active'     => 'boolean',
            // Org scopes
            'org_scopes'         => 'nullable|array',
            'org_scopes.*.type'  => 'required_with:org_scopes|in:' . implode(',', PostOrgScope::TYPES),
            'org_scopes.*.value' => 'nullable|string',
            // Vehicle scopes
            'vehicle_scopes'         => 'nullable|array',
            'vehicle_scopes.*.type'  => 'required_with:vehicle_scopes|in:' . implode(',', PostVehicleScope::TYPES),
            'vehicle_scopes.*.value' => 'nullable|string',
        ]);

        DB::transaction(function () use ($validated, $request) {
            $post = $this->postService->createPost(
                desigCode:   $validated['desig_code'],
                branchCode:  $validated['branch_code']  ?? null,
                deptCode:    $validated['dept_code']    ?? null,
                divCode:     $validated['div_code']     ?? null,
                locCode:     $validated['loc_code']     ?? null,
                maxOccupants:$validated['max_occupants'] ?? 1,
            );

            // Save org scopes
            foreach ($request->input('org_scopes', []) as $scope) {
                PostOrgScope::create([
                    'post_code'   => $post->post_code,
                    'scope_type'  => $scope['type'],
                    'scope_value' => $scope['value'] ?: null,
                    'created_by'  => backpack_auth()->id(),
                ]);
            }

            // Save vehicle scopes
            foreach ($request->input('vehicle_scopes', []) as $scope) {
                PostVehicleScope::create([
                    'post_code'   => $post->post_code,
                    'scope_type'  => $scope['type'],
                    'scope_value' => $scope['value'] ?: null,
                    'created_by'  => backpack_auth()->id(),
                ]);
            }
        });

        flash('Post created successfully!')->success();
        return redirect(backpack_url('post'));
    }

    public function edit($id)
    {
        $this->crud->hasAccessOrFail('update');
        $this->crud->setEditView('admin.post.edit');

        $post         = Post::withoutGlobalScopes()
                            ->with(['orgScopes', 'vehicleScopes'])
                            ->findOrFail($id);
        $branches     = Branch::orderBy('name')->get();
        $departments  = Department::orderBy('name')->get();
        $divisions    = Division::orderBy('name')->get();
        $designations = Designation::active()->orderBy('name')->get();
        $locations    = Location::orderBy('name')->get();
        $orgScopeTypes     = PostOrgScope::TYPES;
        $vehicleScopeTypes = PostVehicleScope::TYPES;

        return view('admin.post.edit', compact(
            'post', 'branches', 'departments', 'divisions',
            'designations', 'locations', 'orgScopeTypes', 'vehicleScopeTypes'
        ));
    }

    public function update(Request $request, $id)
    {
        $this->crud->hasAccessOrFail('update');

        $post = Post::withoutGlobalScopes()->findOrFail($id);

        $validated = $request->validate([
            'max_occupants'      => 'integer|min:1|max:10',
            'is_active'          => 'boolean',
            'org_scopes'         => 'nullable|array',
            'org_scopes.*.type'  => 'required_with:org_scopes|in:' . implode(',', PostOrgScope::TYPES),
            'org_scopes.*.value' => 'nullable|string',
            'vehicle_scopes'         => 'nullable|array',
            'vehicle_scopes.*.type'  => 'required_with:vehicle_scopes|in:' . implode(',', PostVehicleScope::TYPES),
            'vehicle_scopes.*.value' => 'nullable|string',
        ]);

        DB::transaction(function () use ($post, $validated, $request) {
            $post->update([
                'max_occupants' => $validated['max_occupants'] ?? $post->max_occupants,
                'is_active'     => $validated['is_active']     ?? $post->is_active,
            ]);

            // Replace org scopes
            PostOrgScope::where('post_code', $post->post_code)->delete();
            foreach ($request->input('org_scopes', []) as $scope) {
                PostOrgScope::create([
                    'post_code'   => $post->post_code,
                    'scope_type'  => $scope['type'],
                    'scope_value' => $scope['value'] ?: null,
                    'updated_by'  => backpack_auth()->id(),
                ]);
            }

            // Replace vehicle scopes
            PostVehicleScope::where('post_code', $post->post_code)->delete();
            foreach ($request->input('vehicle_scopes', []) as $scope) {
                PostVehicleScope::create([
                    'post_code'   => $post->post_code,
                    'scope_type'  => $scope['type'],
                    'scope_value' => $scope['value'] ?: null,
                    'updated_by'  => backpack_auth()->id(),
                ]);
            }
        });

        flash('Post updated successfully!')->success();
        return redirect(backpack_url('post'));
    }
}
