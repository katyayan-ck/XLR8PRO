<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class RulesUserImportTemplateGenerator
{
    /**
     * Generate the Rules User Import Template
     */
    public static function generate($outputPath = null)
    {
        if (!$outputPath) {
            $outputPath = storage_path('templates/rules_user_import_template.xlsx');
        }

        // Create directory if it doesn't exist
        @mkdir(dirname($outputPath), 0755, true);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('User List');

        // Define headers (17 columns)
        $headers = [
            'S.No',
            'Name',
            'Email',
            'Mobile',
            'Mile ID',
            'Emp Code',
            'Designation',
            'Department',
            'Sub Department',
            'Location',
            'Branch',
            'Vertical',
            'Segment',
            'Sub Segment',
            'Models',
            'User ID',
            'Password',
        ];

        // Write headers
        foreach ($headers as $col => $header) {
            $cell = $sheet->getCellByColumnAndRow($col + 1, 1);
            $cell->setValue($header);

            // Create Font object for header
            $headerFont = new Font();
            $headerFont->setBold(true);
            $headerFont->setColor(new Color('FFFFFFFF'));
            $headerFont->setSize(11);

            // Set header style
            $cell->getStyle()->setFont($headerFont);
            $cell->getStyle()->setFill(new Fill([
                'fillType' => Fill::FILL_SOLID,
                'startColor' => new Color('FF366092'),
            ]));
            $cell->getStyle()->setAlignment(new Alignment([
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ]));
        }

        // Add sample data row
        $sampleData = [
            1,                              // S.No
            'ADNAN QURESHI',               // Name
            'adnan.qureshi@bmpl.com',      // Email (optional)
            '9876543210',                   // Mobile (optional)
            'MILE001',                      // Mile ID (optional)
            'BMPL - 006',                   // Emp Code (REQUIRED)
            'SALES CONSULTANT',             // Designation (REQUIRED)
            'SALES',                        // Department (REQUIRED)
            'SALES-BIKES',                  // Sub Department (optional, NULL = all divisions)
            'RATANGARH',                    // Location (optional)
            'CHURU',                        // Branch (optional, can be comma-separated: BIKANER, CHURU)
            'NEW CAR',                      // Vertical (optional, can be comma-separated)
            'LMM',                          // Segment (optional, can be comma-separated)
            'SEDAN',                        // Sub Segment (optional, NULL = all subsegments)
            'MODEL123',                     // Models (optional, can be comma-separated)
            'ADNAN006',                     // User ID (optional, generated if blank)
            '',                             // Password (optional, generated if blank)
        ];

        foreach ($sampleData as $col => $value) {
            $cell = $sheet->getCellByColumnAndRow($col + 1, 2);
            $cell->setValue($value);

            // Light gray background for sample row
            $cell->getStyle()->setFill(new Fill([
                'fillType' => Fill::FILL_SOLID,
                'startColor' => new Color('FFE7E6E6'),
            ]));
            $cell->getStyle()->setAlignment(new Alignment([
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
            ]));
        }

        // Add instructions sheet
        $instructions = $spreadsheet->createSheet();
        $instructions->setTitle('Instructions');

        $instructionText = [
            ['RULES USER IMPORT - INSTRUCTIONS', ''],
            ['', ''],
            ['FIELD DESCRIPTIONS', ''],
            ['', ''],
            ['S.No', 'Row number (auto-generated, can be left blank)'],
            ['Name', 'Full name (e.g., "ADNAN QURESHI") - Will be split into First + Last Name'],
            ['Email', 'Email address - OPTIONAL. If blank, auto-generated as firstname.empcode@bmpl.com'],
            ['Mobile', 'Mobile number - OPTIONAL. If blank, auto-generated from sequence (9811122001, 9811122002, etc.)'],
            ['Mile ID', 'Milestone ID - Optional lookup field'],
            ['Emp Code', 'Employee Code - REQUIRED. Must be unique (e.g., "BMPL - 006")'],
            ['Designation', 'Job designation - REQUIRED. Must exist in system'],
            ['Department', 'Department - REQUIRED. Must exist in system'],
            ['Sub Department', 'Division/Sub Department - OPTIONAL. If blank, user has access to ALL divisions of the department'],
            ['Location', 'Office location - OPTIONAL. Can be comma-separated (e.g., "LOC1, LOC2")'],
            ['Branch', 'Branch - OPTIONAL. Can be comma-separated (e.g., "BIKANER, CHURU")'],
            ['Vertical', 'Vertical (NEW CAR, USED CAR) - OPTIONAL. Can be comma-separated'],
            ['Segment', 'Segment (PERSONAL, COMMERCIAL, LMM) - OPTIONAL. Can be comma-separated'],
            ['Sub Segment', 'Sub Segment - OPTIONAL. If blank, user has access to ALL sub segments of segment'],
            ['Models', 'Vehicle Models - OPTIONAL. Can be comma-separated'],
            ['User ID', 'Login username - OPTIONAL. If blank, auto-generated from Emp Code (BMPL006)'],
            ['Password', 'Login password - OPTIONAL. If blank, auto-generated as user@bmpl#EMPCODE'],
            ['', ''],
            ['IMPORTANT RULES', ''],
            ['', ''],
            ['1. Comma-Separated Values', 'Fields can contain comma-separated values for multiple assignments:'],
            ['', '  Branch: "BIKANER, CHURU" → Creates 2 branch assignments'],
            ['', '  Vertical: "NEW CAR, USED CAR" → Creates 2 vertical assignments'],
            ['', '  Department, Location, Segment, Sub Segment, Models also support this'],
            ['', ''],
            ['2. NULL Scoping (Hierarchical Wildcard)', 'When a child entity is NULL, user gets access to ALL children:'],
            ['', '  Department: ADMIN, Sub Department: NULL → Access ALL divisions under ADMIN'],
            ['', '  Branch: BIKANER, Location: NULL → Access ALL locations under BIKANER'],
            ['', '  Segment: PERSONAL, Sub Segment: NULL → Access ALL sub segments under PERSONAL'],
            ['', ''],
            ['3. Re-importing Existing Users', 'If Emp Code already exists:'],
            ['', '  - Old assignments are DELETED'],
            ['', '  - New assignments are CREATED from the latest import'],
            ['', '  - Use this to update user roles/permissions'],
            ['', ''],
            ['4. Auto-Generated Fields', 'If left blank, these are auto-generated:'],
            ['', '  Email: firstname.empcode@bmpl.com (slugified)'],
            ['', '  Mobile: Sequential from 9811122001'],
            ['', '  User ID: Emp code with spaces/hyphens removed'],
            ['', '  Password: user@bmpl#EMPCODE (hashed with bcrypt)'],
        ];

        $row = 1;
        foreach ($instructionText as $instructionRow) {
            $cell1 = $instructions->getCellByColumnAndRow(1, $row);
            $cell2 = $instructions->getCellByColumnAndRow(2, $row);

            $cell1->setValue($instructionRow[0] ?? '');
            $cell2->setValue($instructionRow[1] ?? '');

            // Bold for section headers
            if (strpos($instructionRow[0], ':', 0) === false && !empty($instructionRow[0])) {
                if (in_array($instructionRow[0], ['FIELD DESCRIPTIONS', 'IMPORTANT RULES', 'RULES USER IMPORT - INSTRUCTIONS'])) {
                    $sectionFont = new Font();
                    $sectionFont->setBold(true);
                    $sectionFont->setSize(12);

                    if ($instructionRow[0] === 'RULES USER IMPORT - INSTRUCTIONS') {
                        $sectionFont->setSize(14);
                        $sectionFont->setColor(new Color('FFFFFFFF'));

                        $cell1->getStyle()->setFont($sectionFont);
                        $cell1->getStyle()->setFill(new Fill([
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => new Color('FF366092'),
                        ]));
                    } else {
                        $cell1->getStyle()->setFont($sectionFont);
                    }
                }
            }

            $row++;
        }

        // Set column widths
        $sheet->getColumnDimensionByColumn(1)->setWidth(8);   // S.No
        $sheet->getColumnDimensionByColumn(2)->setWidth(20);  // Name
        $sheet->getColumnDimensionByColumn(3)->setWidth(25);  // Email
        $sheet->getColumnDimensionByColumn(4)->setWidth(15);  // Mobile
        $sheet->getColumnDimensionByColumn(5)->setWidth(12);  // Mile ID
        $sheet->getColumnDimensionByColumn(6)->setWidth(15);  // Emp Code
        $sheet->getColumnDimensionByColumn(7)->setWidth(20);  // Designation
        $sheet->getColumnDimensionByColumn(8)->setWidth(15);  // Department
        $sheet->getColumnDimensionByColumn(9)->setWidth(18);  // Sub Department
        $sheet->getColumnDimensionByColumn(10)->setWidth(15); // Location
        $sheet->getColumnDimensionByColumn(11)->setWidth(25); // Branch
        $sheet->getColumnDimensionByColumn(12)->setWidth(20); // Vertical
        $sheet->getColumnDimensionByColumn(13)->setWidth(20); // Segment
        $sheet->getColumnDimensionByColumn(14)->setWidth(18); // Sub Segment
        $sheet->getColumnDimensionByColumn(15)->setWidth(20); // Models
        $sheet->getColumnDimensionByColumn(16)->setWidth(15); // User ID
        $sheet->getColumnDimensionByColumn(17)->setWidth(20); // Password

        $instructions->getColumnDimensionByColumn(1)->setWidth(25);
        $instructions->getColumnDimensionByColumn(2)->setWidth(60);

        // Save file
        $writer = new Xlsx($spreadsheet);
        $writer->save($outputPath);

        return $outputPath;
    }
}
