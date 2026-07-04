<?php

namespace App\Http\Controllers\Admin;

use App\Models\Admin\{Branch, Location, Department, Employee};
use App\Models\User;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DashboardController extends CrudController
{
    // public function index()
    // {
    //     $user = backpack_user();

    //     try {
    //         Log::info('Dashboard accessed', ['user_id' => $user->id, 'username' => $user->username]);

    //         $details = $this->getCurrentUserDetails($user);

    //         if ($user->isSuperAdmin()) {
    //             return $this->getSuperAdminDashboard($user, $details);
    //         }

    //         return $this->getScopedUserDashboard($user, $details);
    //     } catch (\Exception $e) {
    //         Log::error('Dashboard error', ['error' => $e->getMessage()]);
    //         return view('vendor.backpack.ui.dashboard', ['error' => 'Failed to load dashboard data.']);
    //     }
    // }
    // public function index()
    // {
    //     $user = backpack_user();

    //     // ==================== DEBUG OUTPUT ====================
    //     echo "<h3 style='color:blue;'>Dashboard Debug Information</h3>";

    //     print_r("<br><b>User array :</b> ");
    //     print_r($user->toArray());

    //     print_r("<br><b>Primary Branch Code :</b> ");
    //     print_r($user->primaryBranchCode());

    //     print_r("<br><b>Primary Location Code :</b> ");
    //     print_r($user->primaryLocationCode());

    //     print_r("<br><b>Primary Department Code :</b> ");
    //     print_r($user->primaryDepartmentCode());

    //     print_r("<br><b>Primary Division Code :</b> ");
    //     print_r($user->primaryDivisionCode());

    //     print_r("<br><b>Primary Post :</b> ");
    //     print_r($user->primaryPost());

    //     print_r("<br><b>All Branches :</b> ");
    //     print_r($user->branches()->pluck('name', 'code')->toArray());

    //     print_r("<br><b>All Locations :</b> ");
    //     print_r($user->locations()->pluck('name', 'code')->toArray());

    //     print_r("<br><b>All Departments :</b> ");
    //     print_r($user->departments()->pluck('name', 'code')->toArray());

    //     // print_r("<br><b>All Divisions :</b> ");
    //     // print_r($user->divisions()->pluck('name', 'code')->toArray());

    //     print_r("<br><b>Designation :</b> ");
    //     print_r($user->employee->desig_code ?? '');

    //     print_r("<br><b>Vertical :</b> ");
    //     print_r($user->employee->vertical_code ?? '');

    //     print_r("<br><b>Segment :</b> ");
    //     print_r($user->employee->segment_code ?? '');

    //     print_r("<br><b>Sub Segment :</b> ");
    //     print_r($user->employee->sub_segment_code ?? '');

    //     print_r("<br><b>Primary Mobile :</b> ");
    //     print_r($user->primary_mobile);

    //     print_r("<br><b>Primary Email :</b> ");
    //     print_r($user->primary_email);

    //     print_r("<br><b>All Mobiles :</b> ");
    //     print_r($user->all_mobiles->toArray());

    //     print_r("<br><b>All Emails :</b> ");
    //     print_r($user->all_emails->toArray());

    //     print_r("<br><b>Primary Address :</b> ");
    //     print_r($user->person->primary_address->full_address ?? '');

    //     print_r("<br><b>All Addresses :</b> ");
    //     print_r($user->person->all_addresses->pluck('full_address')->toArray());

    //     print_r("<br><b>Primary Banking :</b> ");
    //     print_r($user->person->primary_bank->masked_account ?? '');

    //     print_r("<br><b>All Banking :</b> ");
    //     print_r($user->person->all_banking->pluck('masked_account')->toArray());

    //     print_r("<br><b>Roles :</b> ");
    //     print_r($user->getRoleNames()->toArray());

    //     print_r("<br><b>Posts :</b> ");
    //     print_r($user->posts()->pluck('post_code')->toArray());

    //     print_r("<br><b>Assigned Permissions :</b> ");
    //     print_r($user->getAllPermissions()->pluck('name')->toArray());

    //     // ==================== FINAL DUMP (Recommended) ====================
    //     dd([
    //         'user' => $user->toArray(),
    //         'current_user_details' => $this->getCurrentUserDetails($user),
    //         'is_super_admin' => $user->isSuperAdmin(),
    //         'roles' => $user->getRoleNames()->toArray(),
    //         'permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
    //     ]);
    // }

    // private function getCurrentUserDetails(User $user): array
    // {
    //     $employee = $user->employee;
    //     $person   = $user->person;

    //     return [
    //         'name'                => $user->display_name ?? $user->username ?? 'N/A',
    //         'username'            => $user->username ?? '—',
    //         'user_type'           => $user->user_type ?? 'Emp',
    //         'designation'         => $employee?->desig_code ?? '—',
    //         'mile_id'             => $employee?->mile_id ?? '—',
    //         'vertical'            => $employee?->vertical_code ?? '—',
    //         'segment'             => $employee?->segment_code ?? '—',
    //         'sub_segment'         => $employee?->sub_segment_code ?? '—',

    //         'primary_branch'      => $user->primaryBranchCode() ?? '—',
    //         'primary_location'    => $user->primaryLocationCode() ?? '—',
    //         'primary_department'  => $user->primaryDepartmentCode() ?? '—',
    //         'primary_division'    => $user->primaryDivisionCode() ?? '—',
    //         'primary_post'        => $user->primaryPost() ?? '—',

    //         'all_branches'        => $user->branches()->pluck('name', 'code')->toArray(),
    //         'all_locations'       => $user->locations()->pluck('name', 'code')->toArray(),
    //         'all_departments'     => $user->departments()->pluck('name', 'code')->toArray(),
    //         'all_divisions'       => $user->divisions()->pluck('name', 'code')->toArray(),

    //         'primary_mobile'      => $user->primary_mobile ?? '—',
    //         'primary_email'       => $user->primary_email ?? '—',
    //         'all_mobiles'         => $user->all_mobiles->toArray(),
    //         'all_emails'          => $user->all_emails->toArray(),

    //         'primary_address'     => $person?->primary_address?->full_address ?? '—',
    //         'all_addresses'       => $person?->all_addresses->pluck('full_address')->toArray(),
    //         'primary_banking'     => $person?->primary_bank?->masked_account ?? '—',
    //         'all_banking'         => $person?->all_banking->pluck('masked_account')->toArray(),

    //         'roles'               => $user->getRoleNames()->toArray(),
    //         'posts'               => $user->posts()->pluck('post_code')->toArray(),
    //     ];
    // }

public function index()
    {
        $user = backpack_user(); // or auth()->user()

        if (!$user) {
            return redirect()->route('backpack.auth.login');
        }

        // This is the key line that was missing
        $current_user_details = $this->getCurrentUserDetails($user);

        return view('vendor.backpack.ui.dashboard', [
            'current_user_details' => $current_user_details,
            // Add any other variables your view needs
        ]);
    }

    /**
     * Build rich user profile + access data for dashboard
     */
    private function getCurrentUserDetails(User $user): array
    {
        $employee = $user->employee;   // Make sure relation exists in User model
        $person   = $user->person;     // Make sure relation exists in User model

        return [
            'name'                => $user->display_name ?? $user->username,
            'username'            => $user->username,
            'avatar_initials'     => $user->avatar_initials ?? 'U',
            'designation'         => $user->primary_designation ?? ($employee?->designation?->name ?? '—'),
            'mile_id'             => $employee?->mile_id ?? '—',

            // Primary
            'primary_branch'      => $employee?->primary_branch_code ?? '—',
            'primary_location'    => $employee?->primary_loc_code ?? '—',
            'primary_department'  => $employee?->primary_dept_code ?? '—',
            'primary_division'    => $employee?->primary_div_code ?? '—',
            'primary_vertical'    => $employee?->vertical_code ?? '—',
            'primary_segment'     => $employee?->segment_code ?? '—',
            'primary_sub_segment' => $employee?->sub_segment_code ?? '—',

            // Contact
            'primary_mobile'      => $person?->primary_mobile ?? '—',
            'primary_email'       => $person?->primary_email ?? '—',
            'primary_address'     => $person?->primary_address?->full_address ?? '—',

            // All Access (from xlr8_admin_user_scopes)
            'all_scopes'          => $user->all_access_scopes ?? [],
        ];
    }

    private function getSuperAdminDashboard(User $user, array $details)
    {
        $stats = Cache::remember('dashboard.superadmin.stats', 3600, fn() => [
            'total_branches'   => Branch::count(),
            'total_locations'  => Location::count(),
            'total_departments' => Department::count(),
            'total_employees'  => Employee::count(),
            'active_users'     => User::where('is_active', true)->count(),
        ]);

        return view('vendor.backpack.ui.dashboard', [
            'user'                 => $user,
            'user_access_label'    => '🔑 Full Access (SuperAdmin)',
            'current_user_details' => $details,
            'total_branches'       => $stats['total_branches'] ?? 0,
            'total_locations'      => $stats['total_locations'] ?? 0,
            'total_departments'    => $stats['total_departments'] ?? 0,
            'total_employees'      => $stats['total_employees'] ?? 0,
            'active_users'         => $stats['active_users'] ?? 0,
        ]);
    }

    private function getScopedUserDashboard(User $user, array $details)
    {
        $stats = Cache::remember('dashboard.scoped.' . $user->id, 900, fn() => [
            'total_branches'   => $user->branches()->count(),
            'total_locations'  => $user->locations()->count(),
            'total_departments' => $user->departments()->count(),
            'total_employees'  => Employee::count(),
            'active_users'     => User::where('is_active', true)->count(),
        ]);

        return view('vendor.backpack.ui.dashboard', [
            'user'                 => $user,
            'user_access_label'    => '👁️ Scoped Access',
            'current_user_details' => $details,
            'total_branches'       => $stats['total_branches'] ?? 0,
            'total_locations'      => $stats['total_locations'] ?? 0,
            'total_departments'    => $stats['total_departments'] ?? 0,
            'total_employees'      => $stats['total_employees'] ?? 0,
            'active_users'         => $stats['active_users'] ?? 0,
        ]);
    }
}
