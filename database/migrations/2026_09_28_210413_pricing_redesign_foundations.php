<?php

use App\Models\Utilities\KeyValue\Keyvalue;
use App\Models\Utilities\KeyValue\KeywordMaster;
use App\Services\KeywordValueService;
use App\Services\Utils\KeyvalueService;
use App\Services\Utils\KeywordMasterService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DEC-073 (pricing redesign) foundations:
 *  - snapshots gain `permit` (+ the RTO / insurance permits used) and the unique key includes it, so a taxi's PRIVATE
 *    and PASSENGER snapshots no longer overwrite each other;
 *  - xlr8_vehicle_pricing_session_changes: every insert / expire / update a pricing session makes, so Discard can undo
 *    exactly what the session did (and nothing else);
 *  - xlr8_vehicle_pricing_permit_map: vehicle permit (+ wheels) → RTO rule permit and insurance permit, seeded from the
 *    reference insurance "Rules" sheet (4W taxi → RTO "Taxi" / insurance "Passenger"; MISC → "Ambulance" / "Misc");
 *  - import sessions: progress, hold lists, upload path, published / completed stamps; holds: the session that set them;
 *  - VEHICLE_STATUS gains INCOMPLETE and DISCONTINUED (through the keyword services).
 * Guarded and reversible.
 */
return new class extends Migration
{
    private const PERMIT_MAP = [
        // vehicle permit, wheels (null = any), RTO rule permit, insurance permit, label (reference "RTO Permit" text)
        ['PRIVATE', 4, 'Private', 'Private', 'Private - U/C (4 Wheeler)'],
        ['GOODS', 4, 'Goods', 'Goods', 'Goods - G (4 Wheeler)'],
        ['GOODS', 3, 'Goods', 'Goods', 'Goods - G (3 Wheeler)'],
        ['PASSENGER', 4, 'Taxi', 'Passenger', 'Taxi - T (4 Wheeler)'],
        ['PASSENGER', 3, 'Passenger', 'Passenger', 'Passenger - P (3 Wheeler)'],
        ['MISC', null, 'Ambulance', 'Misc', 'Ambulance (Misc.)'],
    ];

    private const STATUSES = ['INCOMPLETE' => 'Incomplete', 'DISCONTINUED' => 'Discontinued'];

    public function up(): void
    {
        if (Schema::hasTable('xlr8_vehicle_pricing_snapshots')) {
            Schema::table('xlr8_vehicle_pricing_snapshots', function (Blueprint $t) {
                if (! Schema::hasColumn('xlr8_vehicle_pricing_snapshots', 'permit')) {
                    $t->string('permit', 20)->default('')->after('vin_type');
                    $t->string('rto_permit', 30)->nullable()->after('permit');
                    $t->string('insu_permit', 30)->nullable()->after('rto_permit');
                }
            });
            if ($this->hasIndex('xlr8_vehicle_pricing_snapshots', 'uk_snap_code_ch_vin_wef')) {
                Schema::table('xlr8_vehicle_pricing_snapshots', fn (Blueprint $t) => $t->dropUnique('uk_snap_code_ch_vin_wef'));
            }
            if (! $this->hasIndex('xlr8_vehicle_pricing_snapshots', 'uk_snap_code_ch_vin_permit_wef')) {
                Schema::table('xlr8_vehicle_pricing_snapshots', fn (Blueprint $t) => $t->unique(['model_code', 'channel', 'vin_type', 'permit', 'wef_date'], 'uk_snap_code_ch_vin_permit_wef'));
            }
        }

        if (! Schema::hasTable('xlr8_vehicle_pricing_session_changes')) {
            Schema::create('xlr8_vehicle_pricing_session_changes', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('import_session_id');
                $t->string('table_name', 64);
                $t->unsignedBigInteger('row_id');
                $t->string('action', 16);            // insert | expire | update
                $t->json('before')->nullable();      // previous values for expire / update
                $t->timestamp('created_at')->nullable();
                $t->unsignedBigInteger('created_by')->nullable();
                $t->index(['import_session_id', 'id'], 'idx_psc_session');
            });
        }

        if (! Schema::hasTable('xlr8_vehicle_pricing_permit_map')) {
            Schema::create('xlr8_vehicle_pricing_permit_map', function (Blueprint $t) {
                $t->id();
                $t->string('vehicle_permit', 20);
                $t->unsignedTinyInteger('wheels')->nullable();
                $t->string('rto_permit', 30);
                $t->string('insu_permit', 30);
                $t->string('label', 100)->nullable();
                $t->boolean('is_active')->default(true);
                $t->timestamps();
                $t->unsignedBigInteger('created_by')->nullable();
                $t->unsignedBigInteger('updated_by')->nullable();
                $t->softDeletes();
                $t->unsignedBigInteger('deleted_by')->nullable();
                $t->index(['vehicle_permit', 'wheels'], 'idx_ppm_permit_wheels');
            });
            foreach (self::PERMIT_MAP as [$permit, $wheels, $rto, $insu, $label]) {
                DB::table('xlr8_vehicle_pricing_permit_map')->insert([
                    'vehicle_permit' => $permit, 'wheels' => $wheels, 'rto_permit' => $rto, 'insu_permit' => $insu,
                    'label' => $label, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        if (Schema::hasTable('xlr8_vehicle_pricing_import_sessions')) {
            Schema::table('xlr8_vehicle_pricing_import_sessions', function (Blueprint $t) {
                foreach ([
                    'progress' => fn () => $t->json('progress')->nullable()->after('stats'),
                    'hold_lists' => fn () => $t->json('hold_lists')->nullable()->after('hold_scopes'),
                    'upload_path' => fn () => $t->string('upload_path', 255)->nullable()->after('source_filename'),
                    'published_at' => fn () => $t->timestamp('published_at')->nullable()->after('cancelled_by'),
                    'completed_at' => fn () => $t->timestamp('completed_at')->nullable()->after('published_at'),
                    'completed_by' => fn () => $t->unsignedBigInteger('completed_by')->nullable()->after('completed_at'),
                ] as $column => $add) {
                    if (! Schema::hasColumn('xlr8_vehicle_pricing_import_sessions', $column)) {
                        $add();
                    }
                }
            });
        }

        if (Schema::hasTable('xlr8_vehicle_pricing_holds') && ! Schema::hasColumn('xlr8_vehicle_pricing_holds', 'import_session_id')) {
            Schema::table('xlr8_vehicle_pricing_holds', fn (Blueprint $t) => $t->unsignedBigInteger('import_session_id')->nullable()->after('scope'));
        }

        if (Schema::hasTable('xlr8_utils_keyword_master')) {
            if (! KeywordMaster::withTrashed()->where('code', 'VEHICLE_STATUS')->exists()) {
                app(KeywordMasterService::class)->create(['code' => 'VEHICLE_STATUS', 'keyword' => 'Vehicle Status']);
            }
            foreach (self::STATUSES as $code => $label) {
                if (! Keyvalue::withTrashed()->where('keyword_code', 'VEHICLE_STATUS')->where('code', $code)->exists()) {
                    app(KeyvalueService::class)->create(['keyword_code' => 'VEHICLE_STATUS', 'code' => $code, 'value' => $label]);
                }
            }
            KeywordValueService::clearCache('VEHICLE_STATUS');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('xlr8_utils_keyvalue')) {
            Keyvalue::where('keyword_code', 'VEHICLE_STATUS')->whereIn('code', array_keys(self::STATUSES))->forceDelete();
            KeywordValueService::clearCache('VEHICLE_STATUS');
        }
        if (Schema::hasTable('xlr8_vehicle_pricing_holds') && Schema::hasColumn('xlr8_vehicle_pricing_holds', 'import_session_id')) {
            Schema::table('xlr8_vehicle_pricing_holds', fn (Blueprint $t) => $t->dropColumn('import_session_id'));
        }
        if (Schema::hasTable('xlr8_vehicle_pricing_import_sessions')) {
            Schema::table('xlr8_vehicle_pricing_import_sessions', function (Blueprint $t) {
                foreach (['progress', 'hold_lists', 'upload_path', 'published_at', 'completed_at', 'completed_by'] as $column) {
                    if (Schema::hasColumn('xlr8_vehicle_pricing_import_sessions', $column)) {
                        $t->dropColumn($column);
                    }
                }
            });
        }
        Schema::dropIfExists('xlr8_vehicle_pricing_permit_map');
        Schema::dropIfExists('xlr8_vehicle_pricing_session_changes');
        if (Schema::hasTable('xlr8_vehicle_pricing_snapshots')) {
            if ($this->hasIndex('xlr8_vehicle_pricing_snapshots', 'uk_snap_code_ch_vin_permit_wef')) {
                Schema::table('xlr8_vehicle_pricing_snapshots', fn (Blueprint $t) => $t->dropUnique('uk_snap_code_ch_vin_permit_wef'));
            }
            // taxi duplicates would break the old key — keep only one row per old key before restoring it
            DB::statement('DELETE s1 FROM xlr8_vehicle_pricing_snapshots s1 JOIN xlr8_vehicle_pricing_snapshots s2
                ON s1.model_code = s2.model_code AND s1.channel = s2.channel AND s1.vin_type = s2.vin_type AND s1.wef_date = s2.wef_date AND s1.id > s2.id');
            if (! $this->hasIndex('xlr8_vehicle_pricing_snapshots', 'uk_snap_code_ch_vin_wef')) {
                Schema::table('xlr8_vehicle_pricing_snapshots', fn (Blueprint $t) => $t->unique(['model_code', 'channel', 'vin_type', 'wef_date'], 'uk_snap_code_ch_vin_wef'));
            }
            Schema::table('xlr8_vehicle_pricing_snapshots', function (Blueprint $t) {
                foreach (['permit', 'rto_permit', 'insu_permit'] as $column) {
                    if (Schema::hasColumn('xlr8_vehicle_pricing_snapshots', $column)) {
                        $t->dropColumn($column);
                    }
                }
            });
        }
    }

    private function hasIndex(string $table, string $name): bool
    {
        return collect(Schema::getIndexes($table))->contains(fn (array $i) => $i['name'] === $name);
    }
};
