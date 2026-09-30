<?php

/**
 * Path: app/Services/Vehicle/Pricing/PricingResetService.php
 *
 * Destructive reset of workflow / OEM price / snapshot tables.
 * Does NOT touch sheet headers, Insurance, RTO, addons, discounts, dealer charges, TCS.
 * Deletes vehicle_model / vehicle_variant rows created on or after $afterDate.
 */

namespace App\Services\Vehicle\Pricing;

use App\Models\Utilities\Synonym;
use App\Models\Vehicle\Pricing\Addon;
use App\Models\Vehicle\Pricing\AddonHistory;
use App\Models\Vehicle\Pricing\Affected;
use App\Models\Vehicle\Pricing\ChangeFlag;
use App\Models\Vehicle\Pricing\Csd;
use App\Models\Vehicle\Pricing\DealerCharge;
use App\Models\Vehicle\Pricing\Discount;
use App\Models\Vehicle\Pricing\DiscountHistory;
use App\Models\Vehicle\Pricing\Draft;
use App\Models\Vehicle\Pricing\Hold;
use App\Models\Vehicle\Pricing\ImportSession;
use App\Models\Vehicle\Pricing\InsAddonRate;
use App\Models\Vehicle\Pricing\InsBaseRule;
use App\Models\Vehicle\Pricing\InsDefault;
use App\Models\Vehicle\Pricing\Pricing;
use App\Models\Vehicle\Pricing\PricingHistory;
use App\Models\Vehicle\Pricing\Profile;
use App\Models\Vehicle\Pricing\RtoRule;
use App\Models\Vehicle\Pricing\SheetHeader;
use App\Models\Vehicle\Pricing\Snapshot;
use App\Models\Vehicle\Pricing\TcsConfig;
use App\Models\Vehicle\Variant;
use App\Models\Vehicle\VehicleModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Local-only pricing reset (DEC-082): empties the workflow / price / snapshot tables, deletes vehicles created after a
 * date, clears the database queue, and reports the kept rule tables. All table work goes through the models (DEC-093);
 * counts and deletes include soft-deleted rows, as a reset must.
 */
class PricingResetService
{
    /** @var list<class-string<Model>> emptied (TRUNCATE) */
    public const FLUSH_MODELS = [
        ImportSession::class, Profile::class, Pricing::class, PricingHistory::class, ChangeFlag::class,
        Draft::class, Affected::class, Snapshot::class, Hold::class, Csd::class,
    ];

    /** @var list<class-string<Model>> never touched; their row counts are reported */
    public const KEEP_MODELS = [
        SheetHeader::class, Addon::class, AddonHistory::class, Discount::class, DiscountHistory::class,
        DealerCharge::class, RtoRule::class, InsDefault::class, InsBaseRule::class, InsAddonRate::class,
        TcsConfig::class, Synonym::class,
    ];

    /**
     * @return list<string>
     */
    public function run(string $afterDate, bool $flushQueue = true): array
    {
        $log = [];
        $after = date('Y-m-d 00:00:00', strtotime($afterDate));
        $log[] = '['.now()->toDateTimeString().'] Pricing reset start';
        $log[] = 'Cutoff (created_at >=): '.$after;
        $log[] = 'KEEP: headers, addons, discounts, dealer charges, RTO, insurance, TCS, synonyms';

        Schema::disableForeignKeyConstraints();

        foreach (self::FLUSH_MODELS as $model) {
            $table = (new $model)->getTable();
            if (! Schema::hasTable($table)) {
                $log[] = "SKIP missing table {$table}";

                continue;
            }
            $before = $this->all($model)->count();
            $model::query()->truncate();
            $log[] = "FLUSH {$table} ({$before} → 0)";
        }

        $log = array_merge($log, $this->deleteVehiclesAfter($after));

        Schema::enableForeignKeyConstraints();

        if ($flushQueue) {
            $log = array_merge($log, $this->flushQueue());
        }

        Cache::flush();
        $log[] = 'Cache::flush() done';

        foreach (self::KEEP_MODELS as $model) {
            $table = (new $model)->getTable();
            if (Schema::hasTable($table)) {
                $log[] = 'KEEP '.$table.' rows='.$this->all($model)->count();
            }
        }

        $log[] = '['.now()->toDateTimeString().'] Pricing reset finished';

        return $log;
    }

    /**
     * @return list<string>
     */
    protected function deleteVehiclesAfter(string $after): array
    {
        $log = [];
        $variantTable = (new Variant)->getTable();
        $modelTable = (new VehicleModel)->getTable();

        $q = Variant::withTrashed()->where('created_at', '>=', $after);
        $n = (clone $q)->count();
        $q->forceDelete();
        $log[] = "DELETE {$variantTable} created_at >= {$after} ({$n} rows)";

        $keepCodes = Variant::withTrashed()->pluck('model_code')->unique()->filter()->all();
        $q = VehicleModel::withTrashed()->where('created_at', '>=', $after);
        if ($keepCodes !== []) {
            $q->whereNotIn('code', $keepCodes);
        }
        $n = (clone $q)->count();
        $q->forceDelete();
        $log[] = "DELETE {$modelTable} created_at >= {$after} with no remaining variants ({$n} rows)";
        $log[] = "REMAIN {$modelTable}=".VehicleModel::withTrashed()->count()." {$variantTable}=".Variant::withTrashed()->count();

        return $log;
    }

    /**
     * Pending, failed and batch records of the database queue, through the framework's own commands (the queue tables
     * have no models). Before DEC-093 this step named tables that do not exist here (`jobs`, …) and was a no-op.
     *
     * @return list<string>
     */
    protected function flushQueue(): array
    {
        $connection = (string) config('queue.default');
        if (config("queue.connections.{$connection}.driver") !== 'database') {
            return ["SKIP queue flush (connection {$connection} is not the database driver)"];
        }
        Artisan::call('queue:clear', ['connection' => $connection, '--force' => true]);
        Artisan::call('queue:flush');
        Artisan::call('queue:prune-batches', ['--hours' => 0, '--unfinished' => 0, '--cancelled' => 0]);

        return ["FLUSH queue {$connection}: pending, failed and batch records"];
    }

    /**
     * Every row of the model's table, soft-deleted ones included.
     *
     * @param  class-string<Model>  $model
     * @return Builder<Model>
     */
    private function all(string $model): Builder
    {
        return $model::query()->withoutGlobalScopes();
    }
}
