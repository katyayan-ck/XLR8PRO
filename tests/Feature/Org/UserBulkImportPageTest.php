<?php

namespace Tests\Feature\Org;

use App\Models\Admin\Branch;
use App\Models\Admin\Department;
use App\Models\Admin\Designation;
use App\Models\Admin\Location;
use App\Models\Admin\Vertical;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Web bulk user import (DEC-036): form, template download, real upload, permission gate.
 */
class UserBulkImportPageTest extends TestCase
{
    use DatabaseTransactions;

    private function superadmin(): User
    {
        return User::whereHas('roles', fn ($q) => $q->where('name', 'superadmin'))->firstOrFail();
    }

    private function workbook(): UploadedFile
    {
        $branch = Branch::withTrashed()->toBase()->where('is_active', 1)->value('code');
        $location = Location::withTrashed()->toBase()->where('branch_code', $branch)->value('code');
        $department = Department::withTrashed()->toBase()->value('code');
        $vertical = Vertical::query()->toBase()->value('code'); // DEC-089: required
        $designation = Designation::withTrashed()->toBase()->where('name', '!=', 'superadmin')->value('name');

        $book = new Spreadsheet;
        $book->getActiveSheet()->setTitle('Users_Import')->fromArray([
            ['Emp Code*', 'Employee Name*', 'Personal Contact Number*', 'Official Contact Number*', 'Designation*', 'Primary Department*', 'Primary Branch*', 'Primary Location*', 'Vertical'],
            ['BMPL-9911', 'Web Import Tester', '9876500011', '9876500011', $designation, $department, $branch, $location, $vertical],
        ]);
        $path = tempnam(sys_get_temp_dir(), 'wb').'.xlsx';
        (new Xlsx($book))->save($path);

        return new UploadedFile($path, 'users.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    public function test_form_and_template_are_available_to_permitted_users(): void
    {
        $this->actingAs($this->superadmin(), 'backpack');

        $this->get('/admin/org/user/import')->assertOk()->assertSee('Bulk import users');
        $this->get('/admin/org/user/import/template')->assertOk()->assertDownload('users-template.xlsx');
    }

    public function test_upload_imports_rows_and_shows_the_summary(): void
    {
        $this->actingAs($this->superadmin(), 'backpack');

        $this->post('/admin/org/user/import', ['file' => $this->workbook()])
            ->assertOk()
            ->assertSee('Result')
            ->assertSee('All rows imported without issues.');

        $this->assertDatabaseHas('xlr8_admin_employee', ['code' => 'BMPL-9911']);
        $this->assertDatabaseHas('users', ['username' => 'bmpl-9911']);
    }

    public function test_users_without_the_import_permission_are_refused(): void
    {
        $user = User::where('is_active', 1)->get()->first(fn (User $u) => ! $u->isSuperAdmin() && ! $u->can('ORG_USER_IMPORT'));
        $this->actingAs($user, 'backpack');

        $this->get('/admin/org/user/import')->assertForbidden();
    }
}
