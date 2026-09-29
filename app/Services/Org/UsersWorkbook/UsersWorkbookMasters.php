<?php

declare(strict_types=1);

namespace App\Services\Org\UsersWorkbook;

use App\Models\Admin\Branch;
use App\Models\Admin\Department;
use App\Models\Admin\Designation;
use App\Models\Admin\Division;
use App\Models\Admin\Employee;
use App\Models\Admin\Location;
use App\Models\Admin\Vertical;
use App\Models\Vehicle\Segment;
use App\Models\Vehicle\SubSegment;
use App\Models\Vehicle\VehicleModel;

/**
 * The active master codes the users workbook and the bulk screen offer (DEC-089), loaded once per request: each type's
 * codes with names, and the parent → children maps the dependent lists use (branch → locations, department →
 * divisions, segment → sub-segments, sub-segment → models). Codes are upper-case.
 */
final class UsersWorkbookMasters
{
    /** @var array<string, array<string, string>>|null type => CODE => name */
    private ?array $names = null;

    /** @var array<string, array<string, list<string>>>|null child type => PARENT => child codes */
    private ?array $children = null;

    /** @return array<string, string> CODE => name, for branch, location, department, division, vertical, segment, sub_segment, model, designation, employee */
    public function names(string $type): array
    {
        $this->load();

        return $this->names[$type] ?? [];
    }

    /** @return list<string> */
    public function codes(string $type): array
    {
        return array_keys($this->names($type));
    }

    public function exists(string $type, string $code): bool
    {
        return isset($this->names($type)[strtoupper($code)]);
    }

    /**
     * Child codes of the given parents (location ← branch, division ← department, sub_segment ← segment,
     * model ← sub_segment or segment).
     *
     * @param  list<string>  $parents
     * @return list<string>
     */
    public function childrenOf(string $childType, string $parentType, array $parents): array
    {
        $this->load();
        $map = $this->children[$childType.'<'.$parentType] ?? [];
        $out = [];
        foreach ($parents as $parent) {
            array_push($out, ...($map[strtoupper($parent)] ?? []));
        }

        return array_values(array_unique($out));
    }

    /** @return array<string, list<string>> PARENT => child codes */
    public function childMap(string $childType, string $parentType): array
    {
        $this->load();

        return $this->children[$childType.'<'.$parentType] ?? [];
    }

    private function load(): void
    {
        if ($this->names !== null) {
            return;
        }

        $simple = [
            'branch' => Branch::class, 'department' => Department::class, 'vertical' => Vertical::class,
            'segment' => Segment::class, 'designation' => Designation::class,
        ];
        foreach ($simple as $type => $model) {
            $this->names[$type] = $model::query()->where('is_active', true)->orderBy('code')->pluck('name', 'code')
                ->mapWithKeys(fn ($name, $code) => [strtoupper((string) $code) => (string) $name])->all();
        }

        $withParent = [
            'location' => [Location::class, ['branch' => 'branch_code']],
            'division' => [Division::class, ['department' => 'dept_code']],
            'sub_segment' => [SubSegment::class, ['segment' => 'segment_code']],
            'model' => [VehicleModel::class, ['sub_segment' => 'sub_segment_code', 'segment' => 'segment_code']],
        ];
        foreach ($withParent as $type => [$model, $parents]) {
            $rows = $model::query()->where('is_active', true)->orderBy('code')->get(array_merge(['code', 'name'], array_values($parents)));
            $this->names[$type] = [];
            foreach ($rows as $row) {
                $code = strtoupper((string) $row->getAttribute('code'));
                $this->names[$type][$code] = (string) $row->getAttribute('name');
                foreach ($parents as $parentType => $column) {
                    $parent = strtoupper((string) $row->getAttribute($column));
                    if ($parent !== '') {
                        $this->children[$type.'<'.$parentType][$parent][] = $code;
                    }
                }
            }
        }

        $this->names['employee'] = Employee::query()->with('person:person_code,display_name')
            ->where('employment_status', 'active')->orderBy('code')->get(['code', 'person_code'])
            ->mapWithKeys(fn (Employee $e) => [strtoupper((string) $e->getAttribute('code')) => (string) ($e->getRelationValue('person')?->getAttribute('display_name') ?? $e->getAttribute('code'))])
            ->all();
    }
}
