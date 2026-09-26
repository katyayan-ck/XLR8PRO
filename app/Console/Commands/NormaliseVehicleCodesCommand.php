<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Vehicle\VehicleCodeNormaliser;
use Illuminate\Console\Command;

/**
 * Converts vehicle model / sub-segment codes to the hyphenated canonical form (DEC-049).
 * The same step runs automatically in migration 2026_09_27_120000_normalise_vehicle_codes.
 *
 *   php artisan vehicle:normalise-codes --dry-run
 */
class NormaliseVehicleCodesCommand extends Command
{
    protected $signature = 'vehicle:normalise-codes {--dry-run : Show the old → new map without changing data}';

    protected $description = 'Convert vehicle model and sub-segment codes to the hyphenated form (THAR ROXX / THARROXX → THAR-ROXX)';

    public function handle(VehicleCodeNormaliser $normaliser): int
    {
        $result = $normaliser->apply((bool) $this->option('dry-run'));

        foreach ($result['plan'] as $kind => $map) {
            $this->info("{$kind}: ".count($map).' code(s) to convert');
            $this->table(['Old', 'New'], collect($map)->map(fn ($new, $old) => [$old, $new])->values()->all());
        }

        $keywords = $normaliser->applyModelKeywords((bool) $this->option('dry-run'));
        $this->info('model keywords ('.implode(', ', VehicleCodeNormaliser::MODEL_KEYWORDS).'): '.count($keywords['map']).' code(s) to convert');
        $this->table(['Old', 'New'], collect($keywords['map'])->map(fn ($new, $old) => [$old, $new])->values()->all());

        if ($this->option('dry-run')) {
            $this->comment('Dry run — nothing changed.');
        } else {
            $this->table(['Column', 'Rows updated'], collect($result['updated'])->map(fn ($n, $col) => [$col, $n])->values()->all());
        }

        return self::SUCCESS;
    }
}
