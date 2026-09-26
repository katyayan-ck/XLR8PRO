<?php

declare(strict_types=1);

namespace App\Exports\UserRbac;

use Closure;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

/**
 * One sheet of the user & RBAC workbook: rows + headings + an AfterSheet hook for styling,
 * dropdowns and named ranges. With `$asText` every value is written as text, so mobile,
 * Aadhaar and account numbers keep their digits and dates stay `YYYY-MM-DD`.
 */
final class UserRbacSheet extends DefaultValueBinder implements FromArray, WithCustomValueBinder, WithEvents, WithHeadings, WithTitle
{
    /**
     * @param  list<string>  $headings
     * @param  list<array<int, mixed>>  $rows
     * @param  Closure(AfterSheet): void|null  $afterSheet
     */
    public function __construct(
        private readonly string $title,
        private readonly array $headings,
        private readonly array $rows,
        private readonly ?Closure $afterSheet = null,
        private readonly bool $asText = false,
    ) {}

    public function title(): string
    {
        return $this->title;
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function bindValue(Cell $cell, mixed $value): bool
    {
        if (! $this->asText || $value === null) {
            return parent::bindValue($cell, $value);
        }

        $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

        return true;
    }

    public function registerEvents(): array
    {
        return $this->afterSheet ? [AfterSheet::class => $this->afterSheet] : [];
    }
}
