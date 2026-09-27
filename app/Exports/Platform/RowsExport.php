<?php

namespace App\Exports\Platform;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * A plain sheet from headings + rows (approval report, import error sheet, power-sheet template).
 */
class RowsExport implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
{
    /**
     * @param  list<string>  $headings
     * @param  list<array<int|string, mixed>>  $rows
     */
    public function __construct(private readonly array $headings, private readonly array $rows, private readonly string $title = 'Sheet1') {}

    public function array(): array
    {
        return array_map('array_values', $this->rows);
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function title(): string
    {
        return mb_substr($this->title, 0, 31);
    }
}
