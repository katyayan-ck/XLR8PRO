<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use Backpack\CRUD\app\Http\Controllers\CrudController;

class DashboardController extends CrudController
{
    public function index()
    {
        $user = backpack_user();

        if (! $user) {
            return redirect()->route('backpack.auth.login');
        }

        if (! $user->can('admin.dashboard')) {
            abort(403, 'Unauthorized. You do not have permission to view the dashboard.');
        }

        $current_user_details = $this->getCurrentUserDetails($user);

        return view('vendor.backpack.ui.dashboard', [
            'current_user_details' => $current_user_details,

        ]);
    }

    private function getCurrentUserDetails(User $user): array
    {
        $employee = $user->employee;
        $person = $user->person;

        return [
            'name' => $user->display_name ?? $user->username,
            'username' => $user->username,
            'avatar_initials' => $user->avatar_initials ?? 'U',
            'designation' => $user->primary_designation ?? ($employee?->designation?->name ?? '—'),
            'mile_id' => $employee?->mile_id ?? '—',

            'primary_branch' => $employee?->primary_branch_code ?? '—',
            'primary_location' => $employee?->primary_loc_code ?? '—',
            'primary_department' => $employee?->primary_dept_code ?? '—',
            'primary_division' => $employee?->primary_div_code ?? '—',
            'primary_vertical' => $employee?->vertical_code ?? '—',
            'primary_segment' => $employee?->segment_code ?? '—',
            'primary_sub_segment' => $employee?->sub_segment_code ?? '—',

            'primary_mobile' => $person?->primary_mobile ?? '—',
            'primary_email' => $person?->primary_email ?? '—',
            'primary_address' => $person?->primary_address?->full_address ?? '—',

            'all_scopes' => $user->all_access_scopes ?? [],
        ];
    }
}
