<?php

use App\Services\Vehicle\VehicleCodeNormaliser;
use Illuminate\Database\Migrations\Migration;

/**
 * DEC-049: key-value codes of the model lists (CUSTOM-MODEL) to the hyphenated form
 * ("SCORPIO CLASSIC" → "SCORPIO-CLASSIC"), plus their exact references in enquiries/bookings.
 * Map written to storage/logs/model-keyword-normalisation-<db>.json.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(VehicleCodeNormaliser::class)->applyModelKeywords();
    }

    public function down(): void
    {
        // Irreversible by design: reverse with the map in storage/logs/model-keyword-normalisation-<db>.json.
    }
};
