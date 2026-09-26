<?php

namespace Tests\Feature\Org;

use App\Exports\UserRbac\UserRbacWorkbookExport;
use App\Imports\Sheets\UserScopesSheetImport;
use App\Imports\UsersImportWorkbook;
use App\Models\User;
use App\Services\IAM\UserRbacExportService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Users & RBAC workbook (DEC-040): export structure/dropdowns, a no-op round trip through the
 * user importer, User_Scopes replacement semantics and the export permission gate.
 */
class UserRbacWorkbookTest extends TestCase
{
    use DatabaseTransactions;

    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    private function exportFile(): string
    {
        $file = tempnam(sys_get_temp_dir(), 'rbac').'.xlsx';
        file_put_contents($file, Excel::raw(new UserRbacWorkbookExport(app(UserRbacExportService::class)), ExcelFormat::XLSX));

        return $this->files[] = $file;
    }

    /** @param list<list<string>> $rows */
    private function scopesFile(array $rows): string
    {
        $book = new Spreadsheet;
        $book->getActiveSheet()->setTitle(UserScopesSheetImport::SHEET)
            ->fromArray(array_merge([['Emp Code*', 'Scope Type*', 'Scope Value*']], $rows));
        $file = tempnam(sys_get_temp_dir(), 'scp').'.xlsx';
        (new Xlsx($book))->save($file);

        return $this->files[] = $file;
    }

    /** @return array<string, mixed> */
    private function snapshot(): array
    {
        return [
            'employees' => DB::table('xlr8_admin_employee')->orderBy('code')
                ->get(['code', 'person_code', 'designation_code', 'primary_branch_code', 'primary_loc_code', 'primary_dept_code', 'primary_div_code', 'vertical_code', 'segment_code', 'sub_segment_code', 'mile_id', 'joining_date', 'employment_status', 'employment_type', 'reporting_manager_code'])
                ->map(fn ($e) => (array) $e)->all(),
            'users' => DB::table('users')->whereNull('deleted_at')->orderBy('id')->get(['id', 'username', 'user_type', 'is_active', 'employee_code', 'person_code'])->map(fn ($u) => (array) $u)->all(),
            'roles' => DB::table('xlr8_iam_model_has_roles')->orderBy('model_id')->orderBy('role_id')->get()->map(fn ($r) => "{$r->model_id}|{$r->role_id}")->all(),
            'contacts' => DB::table('xlr8_admin_person_contacts')->whereNull('deleted_at')->count(),
            'scopes' => $this->activeScopes(),
        ];
    }

    /** @return list<string> "user|type|CODE" */
    private function activeScopes(?int $userId = null): array
    {
        return DB::table('xlr8_admin_user_scopes')->where('is_active', 1)->whereNull('deleted_at')
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->get()->map(fn ($s) => "{$s->user_id}|{$s->scope_type}|".strtoupper($s->scope_code))->sort()->values()->all();
    }

    public function test_export_has_the_sheets_dropdowns_and_named_ranges(): void
    {
        $book = IOFactory::load($this->exportFile());

        $this->assertSame(['Instructions', 'Permissions', 'Roles', 'Users_Import', 'User_Scopes', 'Lists'], $book->getSheetNames());
        $this->assertSame(Worksheet::SHEETSTATE_HIDDEN, $book->getSheetByName('Lists')->getSheetState());

        $users = $book->getSheetByName('Users_Import');
        $headers = $users->rangeToArray('A1:'.$users->getHighestDataColumn().'1')[0];
        $this->assertSame(app(UserRbacExportService::class)->userHeaders(), $headers);

        $branchCol = array_search('Primary Branch*', $headers, true) + 1;
        $cell = Coordinate::stringFromColumnIndex($branchCol).'2';
        $this->assertSame('L_branch', ltrim($users->getDataValidation($cell)->getFormula1(), '='));
        $this->assertSame('INDIRECT("L_"&$B2)', ltrim($book->getSheetByName('User_Scopes')->getDataValidation('C2')->getFormula1(), '='));

        foreach (['L_branch', 'L_location', 'L_designation', 'L_variant', 'L_emp_code', 'L_scope_type'] as $name) {
            $this->assertNotNull($book->getNamedRange($name), "named range {$name}");
        }
        $this->assertSame('ALL', $book->getSheetByName('Lists')->getCell('B2')->getValue(), 'scope lists start with ALL');

        $permissions = DB::table('xlr8_iam_permissions')->whereNull('deleted_at')->count();
        $this->assertSame($permissions + 1, $book->getSheetByName('Permissions')->getHighestDataRow('A'));
    }

    public function test_importing_an_unchanged_export_changes_nothing_but_missing_primary_scopes(): void
    {
        $file = $this->exportFile();
        $before = $this->snapshot();

        $workbook = new UsersImportWorkbook;
        ob_start();
        Excel::import($workbook, $file);
        ob_end_clean();

        $after = $this->snapshot();
        foreach (['employees', 'users', 'roles', 'contacts'] as $key) {
            $this->assertEquals($before[$key], $after[$key], "{$key} changed on a no-op round trip");
        }
        $this->assertSame(0, $workbook->rows()->summary()['failed']);
        $this->assertSame(0, $workbook->scopes()->summary()['deactivated']);
        $this->assertSame([], array_values(array_diff($before['scopes'], $after['scopes'])), 'no scope removed');

        // Additions are only employees' primary codes that were missing from their scopes.
        $primaries = DB::table('users as u')->join('xlr8_admin_employee as e', 'e.code', '=', 'u.employee_code')
            ->get(['u.id', 'e.primary_branch_code', 'e.primary_loc_code', 'e.primary_dept_code', 'e.primary_div_code', 'e.vertical_code', 'e.segment_code', 'e.sub_segment_code'])
            ->flatMap(fn ($r) => array_filter([
                $r->primary_branch_code ? "{$r->id}|branch|".strtoupper($r->primary_branch_code) : null,
                $r->primary_loc_code ? "{$r->id}|location|".strtoupper($r->primary_loc_code) : null,
                $r->primary_dept_code ? "{$r->id}|department|".strtoupper($r->primary_dept_code) : null,
                $r->primary_div_code ? "{$r->id}|division|".strtoupper($r->primary_div_code) : null,
                $r->vertical_code ? "{$r->id}|vertical|".strtoupper($r->vertical_code) : null,
                $r->segment_code ? "{$r->id}|segment|".strtoupper($r->segment_code) : null,
                $r->sub_segment_code ? "{$r->id}|sub_segment|".strtoupper($r->sub_segment_code) : null,
            ]))->all();
        $this->assertSame([], array_values(array_diff(array_diff($after['scopes'], $before['scopes']), $primaries)));
    }

    public function test_user_scopes_rows_replace_a_listed_users_scopes_only(): void
    {
        $user = DB::table('users as u')->join('xlr8_admin_employee as e', 'e.code', '=', 'u.employee_code')
            ->whereNotNull('e.primary_loc_code')
            ->whereExists(fn ($q) => $q->from('xlr8_admin_user_scopes as s')->whereColumn('s.user_id', 'u.id')
                ->where('s.scope_type', 'location')->where('s.is_active', 1)->whereNull('s.deleted_at')->whereColumn('s.scope_code', '!=', 'e.primary_loc_code'))
            ->first(['u.id', 'u.employee_code', 'e.primary_loc_code']);
        if (! $user) {
            $this->markTestSkipped('No user with a non-primary location scope.');
        }
        $newLocation = DB::table('xlr8_admin_location')->where('is_active', 1)->where('code', '!=', $user->primary_loc_code)->orderByDesc('code')->first(['code', 'name']);
        $otherUsersBefore = array_values(array_filter($this->activeScopes(), fn ($s) => ! str_starts_with($s, "{$user->id}|")));

        $import = new UserScopesSheetImport;
        ob_start();
        Excel::import($import, $this->scopesFile([
            [$user->employee_code, 'location', "{$newLocation->name} ({$newLocation->code})"],
            [$user->employee_code, 'vertical', 'ALL'],
        ]));
        ob_end_clean();

        $primaryBranch = DB::table('xlr8_admin_employee')->where('code', $user->employee_code)->value('primary_branch_code');
        $expected = collect(["{$user->id}|location|".strtoupper($newLocation->code), "{$user->id}|location|".strtoupper($user->primary_loc_code)])
            ->merge(DB::table('xlr8_admin_vertical')->where('is_active', 1)->pluck('code')->map(fn ($c) => "{$user->id}|vertical|".strtoupper($c)))
            ->merge($primaryBranch ? ["{$user->id}|branch|".strtoupper($primaryBranch)] : []);

        $actual = collect($this->activeScopes($user->id));
        foreach ($expected as $scope) {
            $this->assertContains($scope, $actual->all());
        }
        $this->assertSame([], $actual->filter(fn ($s) => str_contains($s, '|location|'))->diff($expected)->values()->all(), 'unlisted locations removed');
        $this->assertTrue(DB::table('xlr8_admin_user_scopes')->where('user_id', $user->id)->where('is_active', 0)->whereNotNull('to_date')->exists());
        $this->assertSame($otherUsersBefore, array_values(array_filter($this->activeScopes(), fn ($s) => ! str_starts_with($s, "{$user->id}|"))), 'other users untouched');
        $this->assertSame(1, $import->summary()['users']);
    }

    public function test_a_user_with_an_invalid_scope_row_is_left_unchanged(): void
    {
        $user = DB::table('users')->whereNotNull('employee_code')
            ->whereExists(fn ($q) => $q->from('xlr8_admin_user_scopes as s')->whereColumn('s.user_id', 'users.id')->where('s.is_active', 1))
            ->first(['id', 'employee_code']);
        $before = $this->activeScopes($user->id);

        $import = new UserScopesSheetImport;
        ob_start();
        Excel::import($import, $this->scopesFile([
            [$user->employee_code, 'branch', 'No Such Branch (NOPE)'],
            [$user->employee_code, 'vertical', 'ALL'],
        ]));
        ob_end_clean();

        $this->assertSame($before, $this->activeScopes($user->id));
        $this->assertSame(['users' => 0, 'activated' => 0, 'inserted' => 0, 'deactivated' => 0, 'skipped_users' => 1, 'failed_rows' => 1], $import->summary());
    }

    public function test_export_downloads_for_a_permitted_user(): void
    {
        $this->actingAs(User::whereHas('roles', fn ($q) => $q->where('name', 'superadmin'))->firstOrFail(), 'backpack');
        $this->get('/admin/org/user/export')->assertOk()->assertDownload();
    }

    public function test_export_download_needs_the_export_permission(): void
    {
        $user = User::where('is_active', 1)->get()->first(fn (User $u) => ! $u->isSuperAdmin() && ! $u->can('ORG_USER_EXPORT'));
        $this->actingAs($user, 'backpack');
        $this->get('/admin/org/user/export')->assertForbidden();
    }
}
