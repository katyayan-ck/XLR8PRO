<?php

namespace App\Http\Controllers\Admin;

use App\Models\Admin\{Branch, Location, Department, Employee};
use App\Models\User;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DashboardController extends CrudController
{
   

public function index()
    {
        $user = backpack_user(); 

        if (!$user) {
            return redirect()->route('backpack.auth.login');
        }

     
        $current_user_details = $this->getCurrentUserDetails($user);

        return view('vendor.backpack.ui.dashboard', [
            'current_user_details' => $current_user_details,
        
        ]);
    }

    private function getCurrentUserDetails(User $user): array
    {
        $employee = $user->employee;   
        $person   = $user->person;     

        return [
            'name'                => $user->display_name ?? $user->username,
            'username'            => $user->username,
            'avatar_initials'     => $user->avatar_initials ?? 'U',
            'designation'         => $user->primary_designation ?? ($employee?->designation?->name ?? '—'),
            'mile_id'             => $employee?->mile_id ?? '—',

           
            'primary_branch'      => $employee?->primary_branch_code ?? '—',
            'primary_location'    => $employee?->primary_loc_code ?? '—',
            'primary_department'  => $employee?->primary_dept_code ?? '—',
            'primary_division'    => $employee?->primary_div_code ?? '—',
            'primary_vertical'    => $employee?->vertical_code ?? '—',
            'primary_segment'     => $employee?->segment_code ?? '—',
            'primary_sub_segment' => $employee?->sub_segment_code ?? '—',

          
            'primary_mobile'      => $person?->primary_mobile ?? '—',
            'primary_email'       => $person?->primary_email ?? '—',
            'primary_address'     => $person?->primary_address?->full_address ?? '—',

            
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
