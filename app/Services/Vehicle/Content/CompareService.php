<?php

declare(strict_types=1);

namespace App\Services\Vehicle\Content;

use App\Models\Vehicle\FeatureItem;
use App\Models\Vehicle\ModelSpec;
use App\Models\Vehicle\SpecItem;
use App\Models\Vehicle\TrimFeature;
use App\Models\Vehicle\Variant;
use App\Models\Vehicle\VehicleModel;
use App\Support\Result;
use Illuminate\Database\Eloquent\Model;

/**
 * Compare vehicles (DEC-092 Phase 4) — used by the admin screen and the app API, same shape for both:
 *  - `variants()` — intra-model: trims (variant codes) of one model side by side on their features;
 *  - `models()` — inter-model: models of ONE segment side by side on their specifications.
 * Result data: {columns: [{code, name}], groups: {group: [{label, values: {code: ?string}, differs: bool}]}, …}; with
 * `$onlyDifferences` rows where every vehicle has the same value are left out. 2–6 vehicles per comparison.
 */
final class CompareService
{
    public const MIN = 2;

    public const MAX = 6;

    /**
     * @param  list<string>  $variantCodes  trims of $modelCode
     */
    public function variants(string $modelCode, array $variantCodes, bool $onlyDifferences = false): Result
    {
        $model = VehicleModel::query()->where('code', strtoupper(trim($modelCode)))->first();
        if ($model === null) {
            return Result::fail('RESOURCE_NOT_FOUND', __('errors.RESOURCE_NOT_FOUND'));
        }
        $codes = $this->codes($variantCodes);
        $trims = Variant::query()->where('model_code', $model->code)->whereIn('code', $codes)
            ->selectRaw('code, MAX(display_name) as label')->groupBy('code')->get()->keyBy(fn ($t) => strtoupper((string) $t->code));
        $codes = array_values(array_filter($codes, fn ($c) => $trims->has($c)));
        if (count($codes) < self::MIN || count($codes) > self::MAX) {
            return Result::fail('VEHICLE_COMPARE_SELECTION', __('errors.VEHICLE_COMPARE_SELECTION'));
        }

        $values = TrimFeature::query()->whereIn('variant_code', $codes)->get(['variant_code', 'feature_item_code', 'value'])
            ->groupBy('feature_item_code');
        $groups = [];
        foreach (FeatureItem::query()->where('is_active', true)->orderBy('feature_group')->orderBy('sort')->orderBy('name')->get() as $item) {
            $row = $this->row((string) $item->name, $codes, $values->get($item->code), 'variant_code');
            if ($row !== null && (! $onlyDifferences || $row['differs'])) {
                $groups[(string) $item->feature_group][] = $row;
            }
        }

        return Result::ok([
            'kind' => 'variants', 'model' => ['code' => (string) $model->code, 'name' => (string) $model->name, 'segment' => (string) $model->segment_code],
            'columns' => array_map(fn ($c) => ['code' => $c, 'name' => (string) $trims[$c]->getAttribute('label')], $codes),
            'groups' => $groups,
        ]);
    }

    /**
     * @param  list<string>  $modelCodes  models of one segment
     */
    public function models(array $modelCodes, bool $onlyDifferences = false): Result
    {
        $codes = $this->codes($modelCodes);
        $models = VehicleModel::query()->whereIn('code', $codes)->get(['code', 'name', 'segment_code'])->keyBy(fn ($m) => strtoupper((string) $m->code));
        $codes = array_values(array_filter($codes, fn ($c) => $models->has($c)));
        if (count($codes) < self::MIN || count($codes) > self::MAX) {
            return Result::fail('VEHICLE_COMPARE_SELECTION', __('errors.VEHICLE_COMPARE_SELECTION'));
        }
        $segments = $models->pluck('segment_code')->map(fn ($s) => strtoupper((string) $s))->unique();
        if ($segments->count() > 1) {
            return Result::fail('VEHICLE_COMPARE_SEGMENT', __('errors.VEHICLE_COMPARE_SEGMENT'), ['segments' => $segments->values()->all()]);
        }

        $values = ModelSpec::query()->whereIn('model_code', $codes)->get(['model_code', 'spec_item_code', 'value'])->groupBy('spec_item_code');
        $groups = [];
        foreach (SpecItem::query()->where('is_active', true)->orderBy('category')->orderBy('sort')->orderBy('name')->get() as $item) {
            $label = (string) $item->name.($item->unit ? ' ('.$item->unit.')' : '');
            $row = $this->row($label, $codes, $values->get($item->code), 'model_code');
            if ($row !== null && (! $onlyDifferences || $row['differs'])) {
                $groups[(string) $item->category][] = $row;
            }
        }

        return Result::ok([
            'kind' => 'models', 'segment' => (string) $segments->first(),
            'columns' => array_map(fn ($c) => ['code' => $c, 'name' => (string) $models[$c]->name], $codes),
            'groups' => $groups,
        ]);
    }

    /**
     * One comparison row; null when no vehicle has a value for the item.
     *
     * @param  list<string>  $codes
     * @param  iterable<Model>|null  $rows
     * @return array{label: string, values: array<string, ?string>, differs: bool}|null
     */
    private function row(string $label, array $codes, ?iterable $rows, string $keyColumn): ?array
    {
        $byCode = [];
        foreach ($rows ?? [] as $r) {
            $byCode[strtoupper((string) $r->getAttribute($keyColumn))] = $r->getAttribute('value');
        }
        $values = [];
        foreach ($codes as $code) {
            $v = $byCode[$code] ?? null;
            $values[$code] = $v === null || $v === '' ? null : (string) $v;
        }
        if (array_filter($values, fn ($v) => $v !== null) === []) {
            return null;
        }
        $distinct = array_unique(array_map(fn ($v) => $v === null ? "\0" : mb_strtolower(trim($v)), $values));

        return ['label' => $label, 'values' => $values, 'differs' => count($distinct) > 1];
    }

    /**
     * @param  list<string>  $codes
     * @return list<string>
     */
    private function codes(array $codes): array
    {
        return array_values(array_unique(array_filter(array_map(fn ($c) => strtoupper(trim((string) $c)), $codes))));
    }
}
