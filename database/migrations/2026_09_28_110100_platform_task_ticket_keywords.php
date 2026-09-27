<?php

use App\Models\Utilities\KeyValue\Keyvalue;
use App\Models\Utilities\KeyValue\KeywordMaster;
use App\Services\KeywordValueService;
use App\Services\Utils\KeyvalueService;
use App\Services\Utils\KeywordMasterService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * DEC-062: keyword values the Task and Ticket utilities read (FRS law 8 — enums come from KeyValue).
 * Idempotent, written through the keyword entity services.
 */
return new class extends Migration
{
    /** @var array<string, array{0: string, 1: array<string, string>}> keyword code => [name, [value code => label]] */
    private const KEYWORDS = [
        'TASK_TYPE' => ['Task Type', ['ASSIGNED_TASK' => 'Assigned task', 'SELF_TASK' => 'Self task']],
        'TASK_PRIORITY' => ['Task Priority', ['LOW' => 'Low', 'NORMAL' => 'Normal', 'HIGH' => 'High']],
        'TICKET_CATEGORY' => ['Ticket Category', ['HARDWARE' => 'Hardware', 'ACCESS' => 'Access', 'DATA' => 'Data', 'BUG' => 'Bug', 'ENHANCEMENT' => 'Enhancement', 'PROCESS' => 'Process']],
        'TICKET_PRIORITY' => ['Ticket Priority', ['P1' => 'P1 — Critical', 'P2' => 'P2 — High', 'P3' => 'P3 — Normal', 'P4' => 'P4 — Low']],
    ];

    /** @var list<array{0: string, 1: string}> values this migration added (for down) */
    private const ADDED = [
        ['TASK_TYPE', 'ASSIGNED_TASK'], ['TASK_TYPE', 'SELF_TASK'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('xlr8_utils_keyword_master') || ! Schema::hasTable('xlr8_utils_keyvalue')) {
            return;
        }

        foreach (self::KEYWORDS as $code => [$name, $values]) {
            if (! KeywordMaster::withTrashed()->where('code', $code)->exists()) {
                app(KeywordMasterService::class)->create(['code' => $code, 'keyword' => $name]);
            }
            foreach ($values as $valueCode => $label) {
                if (! Keyvalue::withTrashed()->where('keyword_code', $code)->where('code', $valueCode)->exists()) {
                    app(KeyvalueService::class)->create(['keyword_code' => $code, 'code' => $valueCode, 'value' => $label]);
                }
            }
            KeywordValueService::clearCache($code);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('xlr8_utils_keyvalue')) {
            return;
        }
        foreach (self::ADDED as [$keyword, $code]) {
            Keyvalue::where('keyword_code', $keyword)->where('code', $code)->forceDelete();
        }
        foreach (['TICKET_CATEGORY', 'TICKET_PRIORITY'] as $keyword) {
            Keyvalue::where('keyword_code', $keyword)->forceDelete();
            KeywordMaster::where('code', $keyword)->forceDelete();
        }
    }
};
