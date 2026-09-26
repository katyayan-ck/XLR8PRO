<?php

use App\Models\Utilities\KeyValue\KeywordMaster;
use App\Services\Utils\KeywordMasterService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * DEC-055: keyword values must belong to an existing keyword. Two keywords already hold values
 * without a master row — PERMIT (vehicle import) and FOLLOW_UP_REMARKS_TYPE (enquiry import) —
 * so their masters are added (idempotent; through KeywordMasterService like every other write).
 */
return new class extends Migration
{
    /** @var array<string, string> code => keyword */
    private const MASTERS = [
        'PERMIT' => 'Vehicle Permit',
        'FOLLOW_UP_REMARKS_TYPE' => 'Follow Up Remarks Type',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('xlr8_utils_keyword_master')) {
            return;
        }

        foreach (self::MASTERS as $code => $keyword) {
            if (! KeywordMaster::withTrashed()->where('code', $code)->exists()) {
                app(KeywordMasterService::class)->create(['code' => $code, 'keyword' => $keyword]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('xlr8_utils_keyword_master')) {
            KeywordMaster::whereIn('code', array_keys(self::MASTERS))->forceDelete();
        }
    }
};
