<?php

use App\Services\Vehicle\VehicleCodeNormaliser;
use Illuminate\Database\Migrations\Migration;

/**
 * DEC-049: vehicle model and sub-segment codes to the hyphenated form ("THAR ROXX" and its
 * space-stripped twin "THARROXX" → "THAR-ROXX") in the masters and every referencing column.
 * Idempotent; the old → new map is written to storage/logs/vehicle-code-normalisation-<db>.json.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(VehicleCodeNormaliser::class)->apply();
    }

    public function down(): void
    {
        // Irreversible by design: reverse with the map in storage/logs/vehicle-code-normalisation-<db>.json.
    }
};
