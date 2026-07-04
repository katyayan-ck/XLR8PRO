<?php
namespace App\Imports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use App\Imports\Sheets\{
    BranchSheet, LocationSheet, DesignationSheet, DepartmentSheet,
    DivisionSheet, DesignationTreeSheet, PostSheet,
    VerticalSheet, SegmentSheet, SubSegmentSheet, ModelSheet,
    UsersImportSheet
};

/**
 * Master RBAC Import — processes all 12 sheets in strict FK-safe order.
 *
 * Processing Order:
 * Phase 1 — No FKs:        Branch, Designation, Department, Vertical, Segment, SubSegment, Model
 * Phase 2 — Depends Ph1:   Location(Branch), Division(Dept), DesignationTree(Desig+Dept+Div)
 * Phase 3 — Depends Ph2:   Post/Role (Desig+Dept+Div+Branch+Loc)
 * Phase 4 — Full chain:    UsersImport (Person → Employee → Pivots → User → PostAssignment)
 *
 * Strategy: continue-on-error. Every failure is logged, import continues.
 * Duplicates: upsert (update if exists, insert if not) — EXCEPT vehicle tree (skip if exists).
 */
class RbacMasterImport implements WithMultipleSheets
{
    public array $log = ['inserted' => 0, 'skipped' => 0, 'updated' => 0, 'errors' => []];

    public function sheets(): array
    {
        return [
            //'M_Branch'          => new BranchSheet($this),
           // 'M_Location'        => new LocationSheet($this),
           //'M_Designation'     => new DesignationSheet($this),
            'M_Department'      => new DepartmentSheet($this),
            'M_Division'        => new DivisionSheet($this),
            'M_DesignationTree' => new DesignationTreeSheet($this),
            'M_Post'            => new PostSheet($this),
           // 'M_Vertical'        => new VerticalSheet($this),
           //'M_Segment'         => new SegmentSheet($this),
          // 'M_SubSegment'      => new SubSegmentSheet($this),
          // 'M_Model'           => new ModelSheet($this),
            'Users_Import'      => new UsersImportSheet($this),
        ];
    }

    public function recordInsert(): void  { $this->log['inserted']++; }
    public function recordUpdate(): void  { $this->log['updated']++; }
    public function recordSkip(): void    { $this->log['skipped']++; }

    public function recordError(string $sheet, int $row, string $msg): void
    {
        $key = "{$sheet} Row {$row}";
        $this->log['errors'][$key] = $msg;
        \Log::warning("RbacMasterImport | {$key} | {$msg}");
    }

    public function summary(): array { return $this->log; }
}