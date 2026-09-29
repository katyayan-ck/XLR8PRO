<?php

declare(strict_types=1);

namespace App\Support\PricingMaster\Masters;

use App\Models\Vehicle\Accessory;
use App\Services\Vehicle\Accessories\AccessoryItemService;
use App\Services\Vehicle\AccessoryService;
use App\Services\Vehicle\Pricing\PricingSyncStamp;
use App\Support\Entity\EntityService;
use App\Support\PricingMaster\MasterDefinition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Accessory catalogue (DEC-083). Import / export = the spec's typed-sheet workbook (one sheet per type: Accessories,
 * Maxicare, Ceramic, PPF, GPS VLTD, RTO Tape, Kazam) through AccessoryService — the import replaces the whole catalogue
 * and its scopes (BUG-179: this is the authoritative importer). Accessories never recalculate prices; they bump the
 * app's sync stamp.
 *
 * @extends MasterDefinition<Accessory>
 */
final class AccessoriesMaster extends MasterDefinition
{
    /** type => sheet title, in AccessoryService::importExcelWithSheetOrder()'s order */
    private const SHEETS = [
        Accessory::TYPE_ACCESSORY => 'Accessories', Accessory::TYPE_MAXICARE => 'Maxicare', Accessory::TYPE_CERAMIC => 'Ceramic',
        Accessory::TYPE_PPF => 'PPF', Accessory::TYPE_GPS_VLTD => 'GPS VLTD', Accessory::TYPE_RTO_TAPE => 'RTO Tape', Accessory::TYPE_KAZAM => 'Kazam',
    ];

    public function key(): string
    {
        return 'accessories';
    }

    public function label(): string
    {
        return 'Accessories';
    }

    public function permission(): string
    {
        return 'PRC_ACCS';
    }

    public function icon(): string
    {
        return 'la-toolbox';
    }

    public function description(): string
    {
        return 'Catalogue by type (Accessories, Maxicare, Ceramic, PPF, GPS VLTD, RTO Tape, Kazam). Where each part applies: Accessory Scopes. Chosen on the quotation; never recalculates prices.';
    }

    public function recalculates(): bool
    {
        return false;
    }

    public function importReplacesAll(): bool
    {
        return true;
    }

    public function model(): string
    {
        return Accessory::class;
    }

    public function service(): EntityService
    {
        return app(AccessoryItemService::class);
    }

    public function formFields(): array
    {
        return ['part_no', 'type', 'item', 'display_name', 'ndp', 'mrp', 'set_qty', 'discount', 'details', 'bundle', 'status'];
    }

    /** @return Builder<Accessory> */
    public function query(bool $history = false): Builder
    {
        return Accessory::query()->withCount('scopes')->when(! $history, fn ($q) => $q->where('status', 1))->orderBy('type')->orderBy('item');
    }

    public function columns(): array
    {
        return [
            ['field' => 'part_no', 'label' => 'Part No.', 'pinned' => 'left', 'width' => 140],
            ['field' => 'type', 'label' => 'Type', 'width' => 110],
            ['field' => 'item', 'label' => 'Item Name', 'width' => 240],
            ['field' => 'display_name', 'label' => 'Display Name', 'width' => 200],
            ['field' => 'ndp', 'label' => 'NDP', 'type' => 'number'],
            ['field' => 'mrp', 'label' => 'MRP', 'type' => 'number'],
            ['field' => 'discount', 'label' => 'Discount', 'type' => 'number'],
            ['field' => 'set_qty', 'label' => 'Set Qty', 'type' => 'number', 'width' => 100],
            ['field' => 'scopes_count', 'label' => 'Scopes', 'type' => 'number', 'width' => 100],
            ['field' => 'status', 'label' => 'Status', 'width' => 100],
        ];
    }

    public function remove(Model $model): void
    {
        $this->service()->update($model, ['status' => '0']);   // catalogue rows are deactivated, never deleted
    }

    public function export(string $path): int
    {
        $rows = app(AccessoryService::class)->exportRows(['type' => 'all_with_special']);
        $book = new Spreadsheet;
        $book->removeSheetByIndex(0);
        foreach (self::SHEETS as $type => $title) {
            $typed = array_values(array_map(fn ($r) => array_diff_key($r, ['TYPE' => 1]), array_filter($rows, fn ($r) => $r['TYPE'] === $type)));
            $head = array_keys(array_diff_key($rows[0] ?? array_flip(['TYPE', 'SEGMENT', 'MODEL', 'Variant', 'Permit', 'DISPLAY NAME', 'ITEM NAME', 'PART NO.', 'Set Qty', 'NDP', 'MRP (ROUNDED)', 'Discount', 'STATUS']), ['TYPE' => 1]));
            $sheet = $book->createSheet()->setTitle($title);
            $sheet->fromArray(array_merge([$head], array_map('array_values', $typed)), null, 'A1', true);
            $sheet->freezePane('A2');
        }
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();

        return count($rows);
    }

    public function import(string $path, ?string $wef = null, ?callable $progress = null): array
    {
        // sheets arrive by position, so the order-aware import (SHEETS is in its order) — BUG-179 authoritative format
        $result = app(AccessoryService::class)->importExcelWithSheetOrder($path, (int) (auth(backpack_guard_name())->id() ?? 1));
        app(PricingSyncStamp::class)->touch();
        $issues = array_map(fn ($e) => ['row' => 0, 'reason' => (string) $e], array_slice(array_merge((array) ($result['errors'] ?? []), (array) ($result['warnings'] ?? [])), 0, 500));
        if (! ($result['success'] ?? false)) {
            throw new \RuntimeException((string) ($result['errors'][0] ?? $result['message'] ?? 'Accessory import failed.'));
        }

        return ['rows' => (int) $result['total_records'], 'written' => (int) $result['imported_count'], 'rejected' => (int) $result['skipped_count'], 'issues' => $issues];
    }
}
