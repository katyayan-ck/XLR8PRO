<?php

declare(strict_types=1);

namespace App\Services\Org\UsersWorkbook;

/**
 * The users workbook layout (DEC-089, owner 30-09): the `Users` sheet's exact headers, in order, and the row key each
 * one maps to. The bulk screen (W11) uses the same keys, so both reach `UserRowService` with one shape.
 */
final class UsersWorkbookColumns
{
    public const SHEET = 'Users';

    public const LISTS_SHEET = 'Lists';

    /** A multi-value cell: every active code the column allows. */
    public const ALL = 'ALL';

    /** A multi-value cell: none of this type (clears it). Not allowed for primaries or vertical. */
    public const NONE = 'NONE';

    /** @var array<string, string> row key => header (exact text, in sheet order) */
    public const HEADERS = [
        'emp_code' => 'Emp Code*',
        'name' => 'Employee Name*',
        'personal_email' => 'Personal Mail Id',
        'official_email' => 'Official Mail ID',
        'personal_mobile' => 'Personal Contact Number*',
        'official_mobile' => 'Official Contact Number',
        'mile_id' => 'OEM Mile ID',
        'aadhaar' => 'Aadhaar No',
        'primary_branch' => 'Primary Branch*',
        'addon_branch' => 'Addon Branch',
        'primary_location' => 'Primary Location*',
        'addon_location' => 'AddOn Location',
        'primary_department' => 'Primary Department*',
        'addon_department' => 'Addon Department',
        'primary_division' => 'Primary Division',
        'addon_division' => 'Add On Divisions',
        'designation' => 'Designation*',
        'vertical' => 'Vertical',
        'segment' => 'Segment',
        'sub_segment' => 'Sub Segment',
        'models' => 'Models',
        'reporting_manager' => 'Reporting Manager',
    ];

    /** @var list<string> keys holding comma-separated codes (or ALL / NONE) */
    public const MULTI = ['addon_branch', 'addon_location', 'addon_department', 'addon_division', 'vertical', 'segment', 'sub_segment', 'models'];

    /**
     * Header text → row key, matched loosely (case, spaces and `*` ignored) so a re-typed header still maps.
     *
     * @param  list<mixed>  $headers
     * @return array<int, string> column index => row key
     */
    public static function map(array $headers): array
    {
        $byNorm = [];
        foreach (self::HEADERS as $key => $header) {
            $byNorm[self::norm($header)] = $key;
        }

        $map = [];
        foreach ($headers as $i => $header) {
            $key = $byNorm[self::norm((string) $header)] ?? null;
            if ($key !== null) {
                $map[(int) $i] = $key;
            }
        }

        return $map;
    }

    private static function norm(string $header): string
    {
        return (string) preg_replace('/[^a-z0-9]/', '', strtolower($header));
    }
}
