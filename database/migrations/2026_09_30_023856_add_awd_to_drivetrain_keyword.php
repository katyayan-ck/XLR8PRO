<?php

use App\Models\Utilities\KeyValue\Keyvalue;
use App\Models\Utilities\KeyValue\KeywordMaster;
use App\Services\KeywordValueService;
use App\Services\Utils\KeyvalueService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * W9 (owner request 30-09): the Vehicle Info import now accepts drivetrains only from the DRIVETRAIN keyword master.
 * Vehicles already use `AWD` (22 on the test copy), which the master lacked, so their re-import would be rejected.
 * Adds `AWD` through the keyword entity service when missing; nothing else in the master changes. Fail-safe both ways.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('xlr8_utils_keyvalue') || ! KeywordMaster::query()->where('code', 'DRIVETRAIN')->exists()) {
            return;
        }
        if (! Keyvalue::withTrashed()->where('keyword_code', 'DRIVETRAIN')->where('code', 'AWD')->exists()) {
            app(KeyvalueService::class)->create(['keyword_code' => 'DRIVETRAIN', 'code' => 'AWD', 'value' => 'AWD']);
        }
        KeywordValueService::clearCache('DRIVETRAIN');
    }

    public function down(): void
    {
        if (Schema::hasTable('xlr8_utils_keyvalue')) {
            Keyvalue::where('keyword_code', 'DRIVETRAIN')->where('code', 'AWD')->forceDelete();
            KeywordValueService::clearCache('DRIVETRAIN');
        }
    }
};
