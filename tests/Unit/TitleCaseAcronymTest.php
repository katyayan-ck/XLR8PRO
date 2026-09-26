<?php

namespace Tests\Unit;

use App\Models\Admin\Department;
use Tests\TestCase;

/**
 * DEC-046: the `title_case` column transformation keeps business acronyms upper-case
 * (the IT department was saved as "It") without upper-casing ordinary names.
 */
class TitleCaseAcronymTest extends TestCase
{
    /** @return array<string, array{string, string}> */
    public static function names(): array
    {
        return [
            'acronym alone' => ['IT', 'IT'],
            'lower-case acronym' => ['hr', 'HR'],
            'acronym in a phrase' => ['PDI inspection', 'PDI Inspection'],
            'acronym at the end' => ['mechanical lmm', 'Mechanical LMM'],
            'shouted person name' => ['RAM DEV', 'Ram Dev'],
            'md is a name, not an acronym' => ['md rafiq', 'Md Rafiq'],
            'plain word' => ['bikaner', 'Bikaner'],
            'acronym letters inside a word' => ['itarsi', 'Itarsi'],
        ];
    }

    /** @dataProvider names */
    public function test_title_case_keeps_acronyms(string $input, string $expected): void
    {
        $this->assertSame($expected, (new Department)->transformRaw($input, ['trim_spaces', 'title_case']));
    }
}
