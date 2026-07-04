<?php

// database/seeders/ProductionRBACSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\Core\{Person, Employee,  Branch, Department, Designation, Post};
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

/**
 * Production RBAC Seeder
 * 
 * Creates:
 * 1. Three roles: super_admin, foundation_manager, user_manager, vehicle_manager
 * 2. Granular permissions grouped by module
 * 3. Three test users with proper Person/Employee data
 * 4. Super admin with wildcard permissions
 */
class ProductionRBACSeeder extends Seeder
{
    public function run(): void
    {
        echo "🔐 Starting RBAC Seeding...\n";

        // === STEP 1: Create Roles ===
        $this->createRoles();

        // === STEP 2: Create Permissions ===
        $this->createPermissions();

        // === STEP 3: Assign Permissions to Roles ===
        $this->assignPermissionsToRoles();

        // === STEP 4: Create Test Users ===
        $this->createTestUsers();

        echo "✅ RBAC Seeding Complete!\n";
    }

    /**
     * Create roles
     */
    private function createRoles(): void
    {
        echo "\n📋 Creating Roles...\n";

        $roles = [
            [
                'name' => 'super_admin',
                'display_name' => 'Super Administrator',
                'description' => 'Full system access with wildcard permissions'
            ],
            [
                'name' => 'foundation_manager',
                'display_name' => 'Foundation Manager',
                'description' => 'Manages organizational structure (branches, departments, divisions, etc.)'
            ],
            [
                'name' => 'user_manager',
                'display_name' => 'User Manager',
                'description' => 'Manages users, employees, and basic access control'
            ],
            [
                'name' => 'vehicle_manager',
                'display_name' => 'Vehicle Manager',
                'description' => 'Manages vehicle inventory, brands, models, variants, colors'
            ],
        ];

        foreach ($roles as $role) {
            $created = Role::updateOrCreate(
                ['name' => $role['name']],
                [
                    'guard_name' => 'web',

                ]
            );
            echo "  ✓ Role: {$created->display_name}\n";
        }
    }

    /**
     * Create granular permissions organized by module
     */
    private function createPermissions(): void
    {
        echo "\n🔑 Creating Permissions...\n";

        $permissions = [
            // ===== FOUNDATION PERMISSIONS =====
            'foundation' => [
                ['name' => 'foundation.view', 'description' => 'View foundation module'],
                ['name' => 'foundation.create', 'description' => 'Create foundation entities'],
                ['name' => 'foundation.edit', 'description' => 'Edit foundation entities'],
                ['name' => 'foundation.delete', 'description' => 'Delete foundation entities'],

                // Sub-module: Branches
                ['name' => 'branch.view', 'description' => 'View branches'],
                ['name' => 'branch.create', 'description' => 'Create branch'],
                ['name' => 'branch.edit', 'description' => 'Edit branch'],
                ['name' => 'branch.delete', 'description' => 'Delete branch'],

                // Sub-module: Locations
                ['name' => 'location.view', 'description' => 'View locations'],
                ['name' => 'location.create', 'description' => 'Create location'],
                ['name' => 'location.edit', 'description' => 'Edit location'],
                ['name' => 'location.delete', 'description' => 'Delete location'],

                // Sub-module: Departments
                ['name' => 'department.view', 'description' => 'View departments'],
                ['name' => 'department.create', 'description' => 'Create department'],
                ['name' => 'department.edit', 'description' => 'Edit department'],
                ['name' => 'department.delete', 'description' => 'Delete department'],

                // Sub-module: Divisions
                ['name' => 'division.view', 'description' => 'View divisions'],
                ['name' => 'division.create', 'description' => 'Create division'],
                ['name' => 'division.edit', 'description' => 'Edit division'],
                ['name' => 'division.delete', 'description' => 'Delete division'],

                // Sub-module: Designations
                ['name' => 'designation.view', 'description' => 'View designations'],
                ['name' => 'designation.create', 'description' => 'Create designation'],
                ['name' => 'designation.edit', 'description' => 'Edit designation'],
                ['name' => 'designation.delete', 'description' => 'Delete designation'],

                // Sub-module: Posts
                ['name' => 'post.view', 'description' => 'View posts'],
                ['name' => 'post.create', 'description' => 'Create post'],
                ['name' => 'post.edit', 'description' => 'Edit post'],
                ['name' => 'post.delete', 'description' => 'Delete post'],
            ],

            // ===== USER MANAGEMENT PERMISSIONS =====
            'users' => [
                ['name' => 'users.view', 'description' => 'View users'],
                ['name' => 'users.create', 'description' => 'Create user'],
                ['name' => 'users.edit', 'description' => 'Edit user'],
                ['name' => 'users.delete', 'description' => 'Delete user'],

                // Sub-module: Persons
                ['name' => 'person.view', 'description' => 'View persons'],
                ['name' => 'person.create', 'description' => 'Create person'],
                ['name' => 'person.edit', 'description' => 'Edit person'],
                ['name' => 'person.delete', 'description' => 'Delete person'],

                // Sub-module: Employees
                ['name' => 'employee.view', 'description' => 'View employees'],
                ['name' => 'employee.create', 'description' => 'Create employee'],
                ['name' => 'employee.edit', 'description' => 'Edit employee'],
                ['name' => 'employee.delete', 'description' => 'Delete employee'],

                // Sub-module: User Types
                ['name' => 'user_type.view', 'description' => 'View user types'],
                ['name' => 'user_type.create', 'description' => 'Create user type'],
                ['name' => 'user_type.edit', 'description' => 'Edit user type'],
                ['name' => 'user_type.delete', 'description' => 'Delete user type'],
            ],

            // ===== VEHICLE MANAGEMENT PERMISSIONS =====
            'vehicles' => [
                ['name' => 'vehicles.view', 'description' => 'View vehicle module'],
                ['name' => 'vehicles.create', 'description' => 'Create vehicle entities'],
                ['name' => 'vehicles.edit', 'description' => 'Edit vehicle entities'],
                ['name' => 'vehicles.delete', 'description' => 'Delete vehicle entities'],

                // Sub-module: Brands
                ['name' => 'brand.view', 'description' => 'View brands'],
                ['name' => 'brand.create', 'description' => 'Create brand'],
                ['name' => 'brand.edit', 'description' => 'Edit brand'],
                ['name' => 'brand.delete', 'description' => 'Delete brand'],

                // Sub-module: Segments
                ['name' => 'segment.view', 'description' => 'View segments'],
                ['name' => 'segment.create', 'description' => 'Create segment'],
                ['name' => 'segment.edit', 'description' => 'Edit segment'],
                ['name' => 'segment.delete', 'description' => 'Delete segment'],

                // Sub-module: Models
                ['name' => 'model.view', 'description' => 'View models'],
                ['name' => 'model.create', 'description' => 'Create model'],
                ['name' => 'model.edit', 'description' => 'Edit model'],
                ['name' => 'model.delete', 'description' => 'Delete model'],

                // Sub-module: Variants
                ['name' => 'variant.view', 'description' => 'View variants'],
                ['name' => 'variant.create', 'description' => 'Create variant'],
                ['name' => 'variant.edit', 'description' => 'Edit variant'],
                ['name' => 'variant.delete', 'description' => 'Delete variant'],

                // Sub-module: Colors
                ['name' => 'color.view', 'description' => 'View colors'],
                ['name' => 'color.create', 'description' => 'Create color'],
                ['name' => 'color.edit', 'description' => 'Edit color'],
                ['name' => 'color.delete', 'description' => 'Delete color'],
            ],

            // ===== ADMIN PERMISSIONS =====
            'admin' => [
                ['name' => 'admin.manage', 'description' => 'Manage admin functions'],
                ['name' => 'admin.dashboard', 'description' => 'Access admin dashboard'],
                ['name' => 'rbac.view', 'description' => 'View RBAC configuration'],
                ['name' => 'rbac.manage', 'description' => 'Manage roles and permissions'],
                ['name' => 'audit.view', 'description' => 'View audit logs'],
                ['name' => 'settings.view', 'description' => 'View settings'],
                ['name' => 'settings.manage', 'description' => 'Manage settings'],
            ],

            // ===== WILDCARD PERMISSION (for Super Admin) =====
            'wildcard' => [
                ['name' => '*', 'description' => 'All permissions - Super Admin only'],
            ],
        ];

        foreach ($permissions as $module => $perms) {
            foreach ($perms as $perm) {
                $created = Permission::updateOrCreate(
                    ['name' => $perm['name']],
                    [
                        'guard_name' => 'web',
                        //'description' => $perm['description'] ?? null,
                    ]
                );
                echo "  ✓ Permission: {$created->name}\n";
            }
        }
    }

    /**
     * Assign permissions to roles
     */
    private function assignPermissionsToRoles(): void
    {
        echo "\n🔗 Assigning Permissions to Roles...\n";

        // === SUPER ADMIN: Gets all permissions (wildcard) ===
        $superAdmin = Role::findByName('super_admin');
        $allPermissions = Permission::all()->pluck('name')->toArray();
        $superAdmin->syncPermissions($allPermissions);
        echo "  ✓ super_admin: " . count($allPermissions) . " permissions\n";

        // === FOUNDATION MANAGER: Foundation module only ===
        $foundationMgr = Role::findByName('foundation_manager');
        $foundationPerms = Permission::where('name', 'like', 'foundation.%')
            ->orWhere('name', 'like', 'branch.%')
            ->orWhere('name', 'like', 'location.%')
            ->orWhere('name', 'like', 'department.%')
            ->orWhere('name', 'like', 'division.%')
            ->orWhere('name', 'like', 'designation.%')
            ->orWhere('name', 'like', 'post.%')
            ->get();
        $foundationMgr->syncPermissions($foundationPerms);
        echo "  ✓ foundation_manager: {$foundationPerms->count()} permissions\n";

        // === USER MANAGER: User management only ===
        $userMgr = Role::findByName('user_manager');
        $userPerms = Permission::where('name', 'like', 'users.%')
            ->orWhere('name', 'like', 'person.%')
            ->orWhere('name', 'like', 'employee.%')
            ->orWhere('name', 'like', 'user_type.%')
            ->get();
        $userMgr->syncPermissions($userPerms);
        echo "  ✓ user_manager: {$userPerms->count()} permissions\n";

        // === VEHICLE MANAGER: Vehicle management only ===
        $vehicleMgr = Role::findByName('vehicle_manager');
        $vehiclePerms = Permission::where('name', 'like', 'vehicles.%')
            ->orWhere('name', 'like', 'brand.%')
            ->orWhere('name', 'like', 'segment.%')
            ->orWhere('name', 'like', 'model.%')
            ->orWhere('name', 'like', 'variant.%')
            ->orWhere('name', 'like', 'color.%')
            ->get();
        $vehicleMgr->syncPermissions($vehiclePerms);
        echo "  ✓ vehicle_manager: {$vehiclePerms->count()} permissions\n";
    }

    /**
     * Create test users with proper Person/Employee data
     */
    private function createTestUsers(): void
    {
        echo "\n👥 Creating Test Users with Person & Employee Data...\n";

        // Get or create default branch
        $branch = Branch::firstOrCreate(
            ['code' => 'HQ'],
            [
                'name' => 'Head Quarter',
                'address' => 'Main Office',
                'city' => 'Bikaner',
                'state' => 'Rajasthan',
                'pincode' => '110001',
                'country' => 'India',
                'is_active' => true,
                //'created_by' => 1,
            ]
        );

        // Get or create department
        $department = Department::firstOrCreate(
            ['code' => 'ADMIN', 'branch_id' => $branch->id],
            [
                'name' => 'Administration',
                'description' => 'Core administration department',
                'is_active' => true,
                //'created_by' => 1,
            ]
        );

        // Get or create designations
        $siteManagerDesig = Designation::firstOrCreate(
            ['code' => 'SM'],
            [
                'name' => 'Site Manager',
                'description' => 'Manages site operations',
                'is_active' => true,
                //'created_by' => 1,
            ]
        );

        $userManagerDesig = Designation::firstOrCreate(
            ['code' => 'UM'],
            [
                'name' => 'User Manager',
                'description' => 'Manages user access',
                'is_active' => true,
                //'created_by' => 1,
            ]
        );

        $vehicleManagerDesig = Designation::firstOrCreate(
            ['code' => 'VM'],
            [
                'name' => 'Vehicle Manager',
                'description' => 'Manages vehicle inventory',
                'is_active' => true,
                //'created_by' => 1,
            ]
        );

        // === USER 1: Chanderkant - Foundation Manager ===
        echo "\n  Creating User 1: Chanderkant (Foundation Manager)...\n";

        $person1 = Person::updateOrCreate(
            ['email_primary' => 'chanderkant@bmpl.com'],
            [
                'first_name' => 'Chander',
                'middle_name' => 'Kant',
                'last_name' => 'Katyayan',
                'display_name' => 'Chander kant Katyayan',
                'dob' => Carbon::parse('1985-06-15'),
                'gender' => 'male',
                // 'blood_group' => 'O+',
                'mobile_primary' => '9310260721',
                'email_primary' => 'chanderkant@bmpl.com',
                'code' => 'CKK001',
                //'is_active' => true,
                //'created_by' => 1,
            ]
        );

        $employee1 = Employee::updateOrCreate(
            ['person_id' => $person1->id],
            [
                'code' => 'EMP001',
                'designation_id' => $siteManagerDesig->id,
                'primary_branch_id' => $branch->id,
                'joining_date' => Carbon::now()->subYears(5),
                'primary_department_id' => 1,
                //'reporting_to' => null,
                'is_active' => true,
                //'created_by' => 1,
            ]
        );

        // Assign to branch and department
        $employee1->branches()->syncWithoutDetaching([
            $branch->id => ['is_current' => true, 'from_date' => Carbon::now()]
        ]);
        $employee1->departments()->syncWithoutDetaching([
            $department->id => ['is_current' => true, 'from_date' => Carbon::now()]
        ]);

        $user1 = User::updateOrCreate(
            ['email' => 'chanderkant@bmpl.com'],
            [
                'name' => 'Chander Kant Katyayan',
                'email' => 'chanderkant@bmpl.com',
                'user_type_id' => 1,
                'person_id' => $person1->id,
                'employee_id' => $employee1->id,
                'password' => Hash::make('manage1234'),
                'code' => 'CKK001',
                //'primary_department_id' => 1,
                'is_active' => true,
                'email_verified_at' => Carbon::now(),
                //'created_by' => 1,
            ]
        );
        $user1->assignRole('foundation_manager');
        echo "    ✓ User created and assigned 'foundation_manager' role\n";

        // === USER 2: KrishanKant - User Manager ===
        echo "\n  Creating User 2: KrishanKant (User Manager)...\n";

        $person2 = Person::updateOrCreate(
            ['email_primary' => 'krishankant@bmpl.com'],
            [
                'first_name' => 'Krishan',
                'middle_name' => 'Kant',
                'last_name' => 'Katyayan',
                'display_name' => 'Krishan Kant Katyayan',
                'dob' => Carbon::parse('1988-03-22'),
                'gender' => 'male',
                // 'blood_group' => 'B+',
                'mobile_primary' => '9310360721',
                'email_primary' => 'krishankant@bmpl.com',
                'code' => 'KKK001',
                //'is_active' => true,
                //'created_by' => 1,
            ]
        );

        $employee2 = Employee::updateOrCreate(
            ['person_id' => $person2->id],
            [
                'code' => 'EMP002',
                'designation_id' => $userManagerDesig->id,
                'primary_branch_id' => $branch->id,
                'joining_date' => Carbon::now()->subYears(3),
                'primary_department_id' => 1,
                //'reporting_to' => $employee1->id,
                'is_active' => true,
                //'created_by' => 1,
            ]
        );

        // Assign to branch and department
        $employee2->branches()->syncWithoutDetaching([
            $branch->id => ['is_current' => true, 'from_date' => Carbon::now()]
        ]);
        $employee2->departments()->syncWithoutDetaching([
            $department->id => ['is_current' => true, 'from_date' => Carbon::now()]
        ]);

        $user2 = User::updateOrCreate(
            ['email' => 'krishankant@bmpl.com'],
            [
                'name' => 'Krishan Kant Katyayan',
                'email' => 'krishankant@bmpl.com',
                'user_type_id' => 1,
                'person_id' => $person2->id,
                'employee_id' => $employee2->id,
                'password' => Hash::make('manage1234'),
                'code' => 'KKK001',
                //'primary_department_id' => 1,
                'is_active' => true,
                'email_verified_at' => Carbon::now(),
                //'created_by' => 1,
            ]
        );
        $user2->assignRole('user_manager');
        echo "    ✓ User created and assigned 'user_manager' role\n";

        // === USER 3: Keshav - Vehicle Manager ===
        echo "\n  Creating User 3: Keshav (Vehicle Manager)...\n";

        $person3 = Person::updateOrCreate(
            ['email_primary' => 'keshav@bmpl.com'],
            [
                'first_name' => 'Keshav',
                'middle_name' => '',
                'last_name' => 'Kumar',
                'display_name' => 'Keshav Kumar',
                'dob' => Carbon::parse('1990-09-10'),
                'gender' => 'male',
                // 'blood_group' => 'AB+',
                'mobile_primary' => '9876543212',
                'email_primary' => 'keshav@bmpl.com',
                'code' => 'KES001',
                //'is_active' => true,
                //'created_by' => 1,
            ]
        );

        $employee3 = Employee::updateOrCreate(
            ['person_id' => $person3->id],
            [
                'code' => 'EMP003',
                'designation_id' => $vehicleManagerDesig->id,
                'primary_branch_id' => $branch->id,
                'joining_date' => Carbon::now()->subYears(2),
                'primary_department_id' => 1,
                //'reporting_to' => $employee1->id,
                'is_active' => true,
                //'created_by' => 1,
            ]
        );

        // Assign to branch and department
        $employee3->branches()->syncWithoutDetaching([
            $branch->id => ['is_current' => true, 'from_date' => Carbon::now()]
        ]);
        $employee3->departments()->syncWithoutDetaching([
            $department->id => ['is_current' => true, 'from_date' => Carbon::now()]
        ]);

        $user3 = User::updateOrCreate(
            ['email' => 'keshav@bmpl.com'],
            [
                'name' => 'Keshav Kumar',
                'email' => 'keshav@bmpl.com',
                'user_type_id' => 1,
                'person_id' => $person3->id,
                'employee_id' => $employee3->id,
                'password' => Hash::make('manage1234'),
                'code' => 'KES001',
                //'primary_department_id' => 1,
                'is_active' => true,
                'email_verified_at' => Carbon::now(),
                //'created_by' => 1,
            ]
        );
        $user3->assignRole('vehicle_manager');
        echo "    ✓ User created and assigned 'vehicle_manager' role\n";

        // === SUPER ADMIN USER (if doesn't exist) ===
        if (!User::where('email', 'super.admin@bmpl.com')->exists()) {
            echo "\n  Creating Super Admin User...\n";

            $personAdmin = Person::updateOrCreate(
                ['email_primary' => 'super.admin@bmpl.com'],
                [
                    'first_name' => 'Super',
                    'middle_name' => '',
                    'last_name' => 'Admin',
                    'display_name' => 'Super Admin',
                    'dob' => Carbon::parse('1980-01-01'),
                    'gender' => 'male',
                    'mobile_primary' => '9999999999',
                    'email_primary' => 'super.admin@bmpl.com',
                    'code' => 'SUP001',
                    //'is_active' => true,
                    // 'created_by' => 1,
                ]
            );

            $employeeAdmin = Employee::updateOrCreate(
                ['person_id' => $personAdmin->id],
                [
                    'code' => 'EMP000',
                    'designation_id' => $siteManagerDesig->id,
                    'primary_branch_id' => $branch->id,
                    'joining_date' => Carbon::now()->subYears(10),
                    'primary_department_id' => 1,
                    //'reporting_to' => null,
                    'is_active' => true,
                    // 'created_by' => 1,
                ]
            );

            $userAdmin = User::create([
                'name' => 'Super Admin',
                'email' => 'super.admin@bmpl.com',
                'user_type_id' => 1,
                'person_id' => $personAdmin->id,
                'employee_id' => $employeeAdmin->id,
                'password' => Hash::make('admin1234'),
                'code' => 'SUP001',
                'is_active' => true,
                'email_verified_at' => Carbon::now(),
                //'created_by' => 1,
            ]);
            $userAdmin->assignRole('super_admin');
            echo "    ✓ Super Admin user created with wildcard permissions\n";
        }

        echo "\n  📝 Test Credentials:\n";
        echo "    - Foundation Manager: chanderkant@bmpl.com / manage1234\n";
        echo "    - User Manager: krishankant@bmpl.com / manage1234\n";
        echo "    - Vehicle Manager: keshav@bmpl.com / manage1234\n";
        echo "    - Super Admin: super.admin@bmpl.com / admin1234\n";
    }
}
