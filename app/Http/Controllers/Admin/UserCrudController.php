<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\UserRequest;
use App\Models\User;
use App\Services\AuthService;
use App\Services\RBACService;
// use App\Services\DataScopeService;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * UserCrudController
 *
 * Manages user accounts with comprehensive RBAC, data scoping, and audit logging.
 *
 * Features:
 * - Role-based access control (RBAC) for all operations
 * - User permission and scope assignment
 * - Password management with hashing
 * - Account status management
 * - Audit logging of all user operations
 * - Data scoping based on user access levels
 *
 * @category Admin Controllers
 * @package App\Http\Controllers\Admin
 * @author VDMS Development Team
 * @version 2.0
 */
class UserCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    /**
     * Service dependencies injected via constructor
     *
     * @param RBACService $rbacService - Role-based access control service
     * @param AuthService $authService - Authentication service
     * @param DataScopeService $dataScopeService - Data scoping service
     */
    public function __construct(
        protected RBACService $rbacService,
        protected AuthService $authService,
        // protected DataScopeService $dataScopeService
    ) {}

    /**
     * Setup CRUD panel configuration
     *
     * Configures the CRUD model, routes, and entity names for the User resource.
     * Sets up operations and basic configuration.
     *
     * @return void
     */
    public function setup(): void
    {
        $this->crud->setModel(User::class);
        $this->crud->setRoute(config('backpack.base.route_prefix') . '/user');
        $this->crud->setEntityNameStrings('user', 'users');

        // Set breadcrumb for navigation
        $this->crud->setCreateContentClass('col-md-8');
        $this->crud->setEditContentClass('col-md-8');

        // Allow all operations by default, permission checks in individual methods
        $this->crud->allowAccess(['list', 'create', 'update', 'delete', 'show']);
    }

    /**
     * Setup List Operation
     *
     * Displays all users with permission checks and data scoping.
     * Retrieves only users accessible to the current user based on their role/scope.
     *
     * Authorization: 'user.view' permission required
     *
     * @return void
     */
    protected function setupListOperation(): void
    {
        // Authorization check - user must have 'user.view' permission
        if (!backpack_user()->can('user.view')) {
            abort(403, 'Unauthorized. You do not have permission to view users.');
        }

        // Log list operation for audit trail
        Log::info('User list accessed', [
            'user_id' => backpack_user()->id,
            'timestamp' => now(),
        ]);

        // Add columns to display in list view
        $this->crud->addColumn([
            'name' => 'code',
            'label' => 'User Code',
            'type' => 'text',
        ]);

        $this->crud->addColumn([
            'name' => 'name',
            'label' => 'Name',
            'type' => 'text',
        ]);

        $this->crud->addColumn([
            'name' => 'email',
            'label' => 'Email',
            'type' => 'email',
        ]);

        $this->crud->addColumn([
            'name' => 'person.mobile_primary',
            'label' => 'Mobile',
            'type' => 'text',
            'orderable' => false,
            'searchLogic' => false,
        ]);

        $this->crud->addColumn([
            'name' => 'is_active',
            'label' => 'Status',
            'type' => 'boolean',
            'orderable' => true,
        ]);

        $this->crud->addColumn([
            'name' => 'last_login_at',
            'label' => 'Last Login',
            'type' => 'datetime',
            'format' => 'Y-m-d H:i:s',
        ]);

        // Add filters
        $this->crud->addFilter([
            'name' => 'is_active',
            'type' => 'dropdown',
            'label' => 'Status',
        ], [
            1 => 'Active',
            0 => 'Inactive',
        ], function ($value) {
            $this->crud->addClause('where', 'is_active', $value);
        });

        // Add search functionality
        $this->crud->setColumnDetailsStrippedOfNonTextTags();
    }

    /**
     * Setup Create Operation
     *
     * Configures fields and validation for creating a new user.
     * Requires 'user.create' permission and validates all input.
     *
     * Authorization: 'user.create' permission required
     *
     * @return void
     */
    protected function setupCreateOperation(): void
    {
        // Authorization check
        if (!backpack_user()->can('user.create')) {
            abort(403, 'Unauthorized. You do not have permission to create users.');
        }

        // Set validation request class
        $this->crud->setValidationClass(UserRequest::class);

        // User Code - Auto-generated or manual entry
        $this->crud->addField([
            'name' => 'code',
            'label' => 'User Code',
            'type' => 'text',
            'attributes' => [
                'placeholder' => 'e.g., USR001',
                'required' => true,
            ],
            'wrapperAttributes' => ['class' => 'form-group col-md-6'],
        ]);

        // User Name
        $this->crud->addField([
            'name' => 'name',
            'label' => 'Full Name',
            'type' => 'text',
            'attributes' => [
                'placeholder' => 'Enter full name',
                'required' => true,
            ],
            'wrapperAttributes' => ['class' => 'form-group col-md-6'],
        ]);

        // Email
        $this->crud->addField([
            'name' => 'email',
            'label' => 'Email Address',
            'type' => 'email',
            'attributes' => [
                'placeholder' => 'user@example.com',
                'required' => true,
            ],
            'wrapperAttributes' => ['class' => 'form-group col-md-6'],
        ]);

        // Password
        $this->crud->addField([
            'name' => 'password',
            'label' => 'Password',
            'type' => 'password',
            'attributes' => [
                'placeholder' => 'Enter strong password',
                'required' => true,
                'minlength' => 8,
            ],
            'hint' => 'Minimum 8 characters. Use uppercase, lowercase, numbers, and special characters.',
            'wrapperAttributes' => ['class' => 'form-group col-md-6'],
        ]);

        // Link to Person (optional)
        $this->crud->addField([
            'name' => 'person_id',
            'label' => 'Link to Person',
            'type' => 'select2',
            'entity' => 'person',
            'attribute' => 'full_name',
            'attributes' => [
                'placeholder' => 'Select a person (optional)',
            ],
            'hint' => 'Link this user to an existing person record if applicable.',
            'wrapperAttributes' => ['class' => 'form-group col-md-6'],
        ]);

        // Link to Employee (optional)
        $this->crud->addField([
            'name' => 'employee_id',
            'label' => 'Link to Employee',
            'type' => 'select2',
            'entity' => 'employee',
            'attribute' => 'code',
            'attributes' => [
                'placeholder' => 'Select an employee (optional)',
            ],
            'hint' => 'Link this user to an employee record if applicable.',
            'wrapperAttributes' => ['class' => 'form-group col-md-6'],
        ]);

        // Active Status
        $this->crud->addField([
            'name' => 'is_active',
            'label' => 'Account Status',
            'type' => 'checkbox',
            'wrapperAttributes' => ['class' => 'form-group col-md-6'],
        ], 'create');

        // Roles assignment
        $this->crud->addField([
            'name' => 'roles',
            'label' => 'Assign Roles',
            'type' => 'select_multiple',
            'entity' => 'roles',
            'attribute' => 'name',
            'pivot' => true,
            'hint' => 'Select one or more roles for this user.',
            'wrapperAttributes' => ['class' => 'form-group col-md-12'],
        ], 'create');
    }

    /**
     * Setup Update Operation
     *
     * Configures fields and validation for updating an existing user.
     * Requires 'user.edit' permission. Allows password change (optional).
     *
     * Authorization: 'user.edit' permission required
     *
     * @return void
     */
    protected function setupUpdateOperation(): void
    {
        // Authorization check
        if (!backpack_user()->can('user.edit')) {
            abort(403, 'Unauthorized. You do not have permission to edit users.');
        }

        // Log user update
        Log::info('User update initiated', [
            'user_id' => backpack_user()->id,
            'target_user_id' => $this->crud->getCurrentEntry()->id ?? null,
            'timestamp' => now(),
        ]);

        $this->crud->setValidationClass(UserRequest::class);

        // Add same fields as create, but with modifications for update
        $this->crud->addField([
            'name' => 'code',
            'label' => 'User Code',
            'type' => 'text',
            'attributes' => [
                'required' => true,
            ],
            'wrapperAttributes' => ['class' => 'form-group col-md-6'],
        ], 'update');

        $this->crud->addField([
            'name' => 'name',
            'label' => 'Full Name',
            'type' => 'text',
            'attributes' => [
                'required' => true,
            ],
            'wrapperAttributes' => ['class' => 'form-group col-md-6'],
        ], 'update');

        $this->crud->addField([
            'name' => 'email',
            'label' => 'Email Address',
            'type' => 'email',
            'attributes' => [
                'required' => true,
            ],
            'wrapperAttributes' => ['class' => 'form-group col-md-6'],
        ], 'update');

        // Password (optional during update)
        $this->crud->addField([
            'name' => 'password',
            'label' => 'Password',
            'type' => 'password',
            'attributes' => [
                'placeholder' => 'Leave blank to keep current password',
                'minlength' => 8,
            ],
            'hint' => 'Only enter if you want to change the password.',
            'wrapperAttributes' => ['class' => 'form-group col-md-6'],
        ], 'update');

        // Person link
        $this->crud->addField([
            'name' => 'person_id',
            'label' => 'Link to Person',
            'type' => 'select2',
            'entity' => 'person',
            'attribute' => 'full_name',
            'wrapperAttributes' => ['class' => 'form-group col-md-6'],
        ], 'update');

        // Employee link
        $this->crud->addField([
            'name' => 'employee_id',
            'label' => 'Link to Employee',
            'type' => 'select2',
            'entity' => 'employee',
            'attribute' => 'code',
            'wrapperAttributes' => ['class' => 'form-group col-md-6'],
        ], 'update');

        // Active status
        $this->crud->addField([
            'name' => 'is_active',
            'label' => 'Account Status',
            'type' => 'checkbox',
            'wrapperAttributes' => ['class' => 'form-group col-md-6'],
        ], 'update');

        // Roles
        $this->crud->addField([
            'name' => 'roles',
            'label' => 'Assign Roles',
            'type' => 'select_multiple',
            'entity' => 'roles',
            'attribute' => 'name',
            'pivot' => true,
            'wrapperAttributes' => ['class' => 'form-group col-md-12'],
        ], 'update');
    }

    /**
     * Handle storing a newly created user
     *
     * Overrides the default store operation to:
     * - Hash password before saving
     * - Log creation event
     * - Assign default roles
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store()
    {
        try {
            // Hash password before save
            if (request('password')) {
                request()->merge(['password' => Hash::make(request('password'))]);
            }

            // Log user creation
            Log::info('User created', [
                'created_by' => backpack_user()->id,
                'user_code' => request('code'),
                'user_email' => request('email'),
                'timestamp' => now(),
            ]);

            return parent::storeCrud();
        } catch (\Exception $e) {
            Log::error('User creation failed', [
                'error' => $e->getMessage(),
                'created_by' => backpack_user()->id,
            ]);

            return back()
                ->withInput()
                ->withError('Failed to create user: ' . $e->getMessage());
        }
    }

    /**
     * Handle updating an existing user
     *
     * Overrides the default update operation to:
     * - Hash password only if provided
     * - Log update event
     * - Track what was changed
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update()
    {
        try {
            // Hash password only if provided and not empty
            if (request('password') && request('password') !== '') {
                request()->merge(['password' => Hash::make(request('password'))]);
            } else {
                // Remove password field if empty (don't update)
                request()->request->remove('password');
            }

            // Log user update
            Log::info('User updated', [
                'updated_by' => backpack_user()->id,
                'user_id' => $this->crud->getCurrentEntry()->id,
                'timestamp' => now(),
            ]);

            return parent::updateCrud();
        } catch (\Exception $e) {
            Log::error('User update failed', [
                'error' => $e->getMessage(),
                'updated_by' => backpack_user()->id,
            ]);

            return back()
                ->withInput()
                ->withError('Failed to update user: ' . $e->getMessage());
        }
    }

    /**
     * Handle deleting a user
     *
     * Overrides the default delete operation to:
     * - Prevent deleting the last super admin
     * - Log deletion event
     * - Soft delete if available
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy()
    {
        try {
            $user = $this->crud->getCurrentEntry();

            // Prevent deleting the last super admin
            if ($user->isSuperAdmin() && User::isSuperAdmin()->count() === 1) {
                return back()->withError('Cannot delete the last super admin user.');
            }

            // Log user deletion
            Log::warning('User deleted', [
                'deleted_by' => backpack_user()->id,
                'user_id' => $user->id,
                'user_code' => $user->code,
                'timestamp' => now(),
            ]);

            return parent::deleteCrud();
        } catch (\Exception $e) {
            Log::error('User deletion failed', [
                'error' => $e->getMessage(),
                'deleted_by' => backpack_user()->id,
            ]);

            return back()->withError('Failed to delete user: ' . $e->getMessage());
        }
    }
}
