<?php

namespace Tests\Feature\Org;

use App\Models\Admin\Branch;
use App\Models\Admin\Department;
use App\Models\Admin\Designation;
use App\Models\Admin\Employee;
use App\Models\Admin\Vertical;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Org → Users → Bulk edit (DEC-089 Phase C, W11): the screen, its rows and the save endpoint, which goes through the same
 * UserRowService as the workbook and returns per-row results in the order sent; ORG_USER_IMPORT guards all three.
 */
class UserBulkEditTest extends TestCase
{
    use DatabaseTransactions;

    private function superadmin(): User
    {
        return User::whereHas('roles', fn ($q) => $q->where('name', 'superadmin'))->firstOrFail();
    }

    public function test_the_screen_and_its_rows_load_for_a_permitted_user(): void
    {
        $this->actingAs($this->superadmin(), 'backpack');

        $this->get('/admin/org/user/bulk')->assertOk()->assertSee('Bulk edit users')->assertSee('xl-user-bulk.js');
        $rows = $this->getJson('/admin/org/user/bulk/data')->assertOk()->json('rows');
        $this->assertNotEmpty($rows);
        $this->assertArrayHasKey('addon_location', $rows[0]);
    }

    public function test_save_creates_a_new_user_and_reports_a_bad_row_in_order(): void
    {
        $this->actingAs($this->superadmin(), 'backpack');
        do {
            $code = 'BMPL-9'.random_int(100, 999);
        } while (Employee::withTrashed()->where('code', $code)->exists());
        $good = [
            'emp_code' => $code, 'name' => 'Grid Tester', 'personal_mobile' => '97'.random_int(10000000, 99999999),
            'primary_branch' => Branch::query()->toBase()->where('is_active', 1)->value('code'),
            'primary_department' => Department::query()->toBase()->where('is_active', 1)->value('code'),
            'designation' => Designation::withTrashed()->toBase()->where('is_active', 1)->where('name', '!=', 'superadmin')->value('code'),
            'vertical' => Vertical::query()->toBase()->where('is_active', 1)->value('code'),
        ];

        $body = $this->postJson('/admin/org/user/bulk', ['rows' => [['emp_code' => $code.'X'], $good]])->assertOk()->json();

        $this->assertSame(['failed', 'created'], array_column($body['rows'], 'status'));
        $this->assertSame([0, 1], array_column($body['rows'], 'row'));
        $this->assertTrue(User::where('employee_code', $code)->exists());
    }

    public function test_the_screen_needs_the_import_permission(): void
    {
        $user = User::where('is_active', 1)->get()->first(fn (User $u) => ! $u->isSuperAdmin() && ! $u->can('ORG_USER_IMPORT'));
        $this->actingAs($user, 'backpack');

        $this->get('/admin/org/user/bulk')->assertForbidden();
        $this->postJson('/admin/org/user/bulk', ['rows' => [['emp_code' => 'BMPL-0001']]])->assertForbidden();
    }
}
