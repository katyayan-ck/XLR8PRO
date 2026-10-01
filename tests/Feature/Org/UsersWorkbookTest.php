<?php

namespace Tests\Feature\Org;

use App\Models\Admin\Department;
use App\Models\Admin\Designation;
use App\Models\Admin\Employee;
use App\Models\Admin\EmployeeHistory;
use App\Models\Admin\Person;
use App\Models\Admin\Vertical;
use App\Models\User;
use App\Services\Org\BranchService;
use App\Services\Org\LocationService;
use App\Services\Org\UsersWorkbook\UserRowService;
use App\Services\Org\UsersWorkbook\UsersWorkbookColumns;
use App\Services\Org\UsersWorkbook\UsersWorkbookService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * The DEC-089 users workbook (W10): one row → person, employee, login, role, scopes and history through the entity
 * services; blank keeps, NONE clears (primary only), ALL = unrestricted; add-on locations limited to the primary /
 * add-on branches; masked Aadhaar keeps the stored number; an unchanged export re-imports as a no-op.
 */
class UsersWorkbookTest extends TestCase
{
    use DatabaseTransactions;

    /** @var array<string, string> */
    private array $org;

    protected function setUp(): void
    {
        parent::setUp();
        $tag = strtoupper(substr(uniqid(), -4));
        foreach (['main' => 'WM', 'addon' => 'WA', 'third' => 'WT'] as $key => $prefix) {
            app(BranchService::class)->create(['code' => $prefix.$tag, 'name' => "Workbook {$key}", 'is_active' => true]);
        }
        app(LocationService::class)->create(['code' => "WX{$tag}", 'name' => 'Addon second site', 'branch_code' => "WA{$tag}", 'is_active' => true]);

        $this->org = [
            'branch' => "WM{$tag}", 'addon' => "WA{$tag}", 'third' => "WT{$tag}", 'addon_site' => "WX{$tag}",
            'department' => (string) Department::withoutGlobalScopes()->toBase()->where('is_active', 1)->whereNull('deleted_at')->value('code'),
            'vertical' => (string) Vertical::withoutGlobalScopes()->toBase()->where('is_active', 1)->whereNull('deleted_at')->value('code'),
            'designation' => (string) Designation::withoutGlobalScopes()->toBase()->where('is_active', 1)->where('name', '!=', 'superadmin')->value('code'),
        ];
    }

    /** @return array<string, string|null> */
    private function row(array $overrides = []): array
    {
        do {
            $code = 'BMPL-9'.random_int(100, 999);
        } while (Employee::withoutGlobalScopes()->toBase()->where('code', $code)->exists());

        return array_merge([
            'emp_code' => $code, 'name' => 'Workbook Tester', 'personal_mobile' => '98'.random_int(10000000, 99999999),
            'primary_branch' => $this->org['branch'], 'primary_department' => $this->org['department'],
            'designation' => $this->org['designation'], 'vertical' => $this->org['vertical'],
        ], $overrides);
    }

    /** @return array<string, list<string>> */
    private function scopes(string $empCode): array
    {
        $user = User::where('employee_code', $empCode)->firstOrFail();
        $scopes = $user->getAllScopes();
        foreach ($scopes as &$codes) {
            sort($codes);
        }

        return $scopes;
    }

    private function history(string $empCode): int
    {
        return EmployeeHistory::withoutGlobalScopes()->toBase()->where('emp_code', $empCode)->count();
    }

    public function test_a_new_row_creates_the_user_with_primary_plus_add_on_scopes_and_history(): void
    {
        $row = $this->row(['addon_branch' => $this->org['addon'], 'addon_location' => $this->org['addon_site']]);

        $result = app(UserRowService::class)->save($row);

        $this->assertSame('created', $result['status'], implode('; ', $result['messages']));
        $employee = Employee::withoutGlobalScopes()->toBase()->where('code', $row['emp_code'])->first();
        $this->assertSame([$this->org['branch'], $this->org['branch'], $this->org['vertical']], [$employee->primary_branch_code, $employee->primary_loc_code, $employee->vertical_code]);
        $scopes = $this->scopes($row['emp_code']);
        $expected = [$this->org['addon'], $this->org['branch']];
        sort($expected);
        $this->assertSame($expected, $scopes['branch']);
        $this->assertContains($this->org['addon_site'], $scopes['location']);
        $this->assertArrayNotHasKey('segment', $scopes, 'a blank vehicle scope on create is unrestricted');
        $this->assertTrue(User::where('employee_code', $row['emp_code'])->first()->hasRole(Designation::withoutGlobalScopes()->toBase()->where('code', $this->org['designation'])->value('name')));
        $this->assertSame(1, $this->history($row['emp_code']));
    }

    public function test_blank_keeps_none_leaves_the_primary_and_all_is_unrestricted(): void
    {
        $row = $this->row(['addon_branch' => $this->org['addon']]);
        app(UserRowService::class)->save($row);
        $code = $row['emp_code'];

        app(UserRowService::class)->save(['emp_code' => $code, 'addon_branch' => '']);
        $this->assertCount(2, $this->scopes($code)['branch'], 'blank keeps the add-ons');

        app(UserRowService::class)->save(['emp_code' => $code, 'addon_branch' => 'NONE']);
        $this->assertSame([$this->org['branch']], $this->scopes($code)['branch']);

        app(UserRowService::class)->save(['emp_code' => $code, 'addon_branch' => 'ALL']);
        $this->assertArrayNotHasKey('branch', $this->scopes($code));
        $this->assertSame(3, $this->history($code), 'each scope change is kept in the history');
    }

    public function test_out_of_rule_values_fail_the_row_and_write_nothing(): void
    {
        $bad = $this->row(['addon_location' => $this->org['addon_site']]);   // its branch is not an add-on
        $result = app(UserRowService::class)->save($bad);

        $this->assertSame('failed', $result['status']);
        $this->assertStringContainsString('AddOn Location: '.$this->org['addon_site'], implode(' ', $result['messages']));
        $this->assertFalse(Employee::withoutGlobalScopes()->toBase()->where('code', $bad['emp_code'])->exists());

        $result = app(UserRowService::class)->save($this->row(['primary_branch' => 'ALL', 'vertical' => 'NONE']));
        $this->assertSame('failed', $result['status']);
        $this->assertCount(2, $result['messages']);
    }

    public function test_a_masked_aadhaar_keeps_the_stored_number_and_a_full_one_replaces_it(): void
    {
        $row = $this->row(['aadhaar' => '2345 6789 0123']);
        app(UserRowService::class)->save($row);
        $person = fn () => Person::withoutGlobalScopes()->toBase()->where('person_code', Employee::withoutGlobalScopes()->toBase()->where('code', $row['emp_code'])->value('person_code'))->value('aadhaar_no');
        $this->assertSame('234567890123', $person());

        app(UserRowService::class)->save(['emp_code' => $row['emp_code'], 'aadhaar' => 'XXXXXXXX0123']);
        $this->assertSame('234567890123', $person());

        app(UserRowService::class)->save(['emp_code' => $row['emp_code'], 'aadhaar' => '345678901234']);
        $this->assertSame('345678901234', $person());
    }

    public function test_the_export_has_the_owner_headers_and_re_imports_without_changes(): void
    {
        $row = $this->row(['addon_branch' => $this->org['addon'], 'aadhaar' => '456789012345']);
        app(UserRowService::class)->save($row);
        $path = tempnam(sys_get_temp_dir(), 'uw').'.xlsx';

        app(UsersWorkbookService::class)->export($path);

        $book = IOFactory::load($path);
        $sheet = $book->getSheetByName(UsersWorkbookColumns::SHEET);
        $this->assertSame(array_values(UsersWorkbookColumns::HEADERS), $sheet->rangeToArray('A1:V1')[0]);
        $this->assertNotNull($book->getSheetByName(UsersWorkbookColumns::LISTS_SHEET));
        $this->assertNotContains('456789012345', array_merge(...$sheet->toArray()), 'Aadhaar is masked');

        $before = [$this->scopes($row['emp_code']), $this->history($row['emp_code'])];
        $result = app(UsersWorkbookService::class)->import($path);
        @unlink($path);

        $this->assertSame(0, $result['summary']['failed'], implode("\n", array_slice($result['issues'], 0, 5)));
        $this->assertSame($before, [$this->scopes($row['emp_code']), $this->history($row['emp_code'])]);
    }

    public function test_export_and_template_download_for_a_permitted_user(): void
    {
        $this->actingAs(User::whereHas('roles', fn ($q) => $q->where('name', 'superadmin'))->firstOrFail(), 'backpack');
        $this->get('/admin/org/user/export')->assertOk()->assertDownload();
        $this->get('/admin/org/user/import/template')->assertOk()->assertDownload('users-template.xlsx');
    }

    public function test_export_needs_the_export_permission(): void
    {
        $user = User::where('is_active', 1)->get()->first(fn (User $u) => ! $u->isSuperAdmin() && ! $u->can('ORG_USER_EXPORT'));
        $this->actingAs($user, 'backpack');
        $this->get('/admin/org/user/export')->assertForbidden();
    }
}
