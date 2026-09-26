<?php

namespace Tests\Unit;

use App\Imports\Sheets\StandaloneUsersImport;
use App\Imports\UsersImportWorkbook;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * User bulk import (DEC-035 / BUG-162): builds a small workbook with a Users_Import sheet
 * and a Reporting sheet (which also has an "Emp Code" column) and checks creation,
 * idempotency and that existing identities are never re-pointed.
 */
class StandaloneUsersImportTest extends TestCase
{
    use DatabaseTransactions;

    private string $file;

    private object $existing;

    protected function setUp(): void
    {
        parent::setUp();

        $branch = DB::table('xlr8_admin_branch')->where('is_active', 1)->value('code');
        $location = DB::table('xlr8_admin_location')->where('branch_code', $branch)->value('code');
        $department = DB::table('xlr8_admin_department')->value('code');
        $designation = DB::table('xlr8_admin_designation')->where('name', '!=', 'superadmin')->value('name');
        $existing = DB::table('xlr8_admin_employee')->whereNotNull('person_code')->first();

        if (! $branch || ! $location || ! $department || ! $designation || ! $existing) {
            $this->markTestSkipped('Test database lacks org/employee reference data.');
        }

        $headers = ['Emp Code*', 'Employee Name*', 'Personal Contact Number*', 'Official Contact Number*', 'PAN No.',
            'Aadhaar No', 'Designation*', 'Primary Department*', 'Primary Branch*', 'Primary Location*', 'Employee Status'];

        $book = new Spreadsheet;
        $users = $book->getActiveSheet()->setTitle('Users_Import');
        $users->fromArray([
            $headers,
            ['BMPL-9901', 'Test Importer One', '9876500001', '9876500001', 'ABCTE9901K', '', $designation, $department, $branch, $location, 'Active'],
            // existing employee: must be updated, never re-pointed to another person
            [$existing->code, 'Existing Employee Renamed', '', '', '', '', $designation, $department, $branch, $location, 'Active'],
            // missing mandatory name: skipped
            ['BMPL-9902', '', '9876500002', '', '', '', $designation, $department, $branch, $location, 'Active'],
        ]);
        $reporting = $book->createSheet()->setTitle('Reporting');
        $reporting->fromArray([['Name', 'Emp Code', 'USER ID'], ['', $existing->code, 'x']]);

        $this->file = tempnam(sys_get_temp_dir(), 'users').'.xlsx';
        (new Xlsx($book))->save($this->file);
        $this->existing = $existing;
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        parent::tearDown();
    }

    private function import(): array
    {
        $rows = new StandaloneUsersImport;
        ob_start();
        Excel::import(new UsersImportWorkbook($rows), $this->file);
        ob_end_clean();

        return $rows->summary();
    }

    public function test_creates_updates_and_skips_as_expected(): void
    {
        $summary = $this->import();

        $this->assertSame(['created' => 1, 'updated' => 1, 'skipped' => 1, 'failed' => 0], array_intersect_key($summary, array_flip(['created', 'updated', 'skipped', 'failed'])));
        $this->assertDatabaseHas('xlr8_admin_employee', ['code' => 'BMPL-9901']);
        $this->assertDatabaseHas('users', ['username' => 'bmpl-9901']);
        $this->assertDatabaseMissing('xlr8_admin_employee', ['code' => 'BMPL-9902']);
    }

    public function test_existing_employee_keeps_its_person_and_reporting_sheet_is_ignored(): void
    {
        $personsBefore = DB::table('xlr8_admin_person')->count();

        $this->import();

        $this->assertSame($this->existing->person_code, DB::table('xlr8_admin_employee')->where('code', $this->existing->code)->value('person_code'));
        // exactly one new person (BMPL-9901); the Reporting row created none
        $this->assertSame($personsBefore + 1, DB::table('xlr8_admin_person')->count());
        $this->assertSame(0, DB::table('xlr8_admin_employee as e')
            ->join('xlr8_admin_person as p', 'p.person_code', '=', 'e.person_code')
            ->where(fn ($q) => $q->whereNull('p.display_name')->orWhere('p.display_name', ''))
            ->count());
    }

    public function test_a_second_run_is_idempotent(): void
    {
        $this->import();
        $counts = fn () => [DB::table('xlr8_admin_person')->count(), DB::table('xlr8_admin_employee')->count(), DB::table('users')->count()];
        $afterFirst = $counts();

        $summary = $this->import();

        $this->assertSame(0, $summary['created']);
        $this->assertSame($afterFirst, $counts());
    }
}
