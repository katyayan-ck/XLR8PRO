<?php

namespace Database\Seeders;

use App\Models\Utilities\KeyValue\KeywordMaster;
use App\Models\Utilities\KeyValue\Keyvalue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EnumToKeyValueSeeder extends Seeder
{
    public function run(): void
    {
        echo "🚀 Starting clean migration...\n\n";

        $columns = DB::table('bmpl_enum_columns')->get();

        foreach ($columns as $col) {
            $keywordCode = strtoupper(trim($col->keyword ?? 'UNKNOWN'));
            $keywordName = trim($col->name ?? $keywordCode);

            $master = KeywordMaster::where('keyword', $keywordName)->first()
                ?? KeywordMaster::firstOrCreate(
                    ['code' => $keywordCode],
                    [
                        'keyword'      => $keywordName,
                        'description'  => $col->details ?? $keywordName,
                        'is_active'    => true,
                        'is_recursive' => (int)($col->recursive ?? 0) === 1,
                    ]
                );

            $values = DB::table('bmpl_enum_master')->where('master_id', $col->id)->get();

            $added = 0;
            foreach ($values as $val) {
                $valueText = trim($val->value ?? '');
                $rawCode   = trim($val->value_code ?? $valueText);

                // Generate safe short code (max 80 chars)
                $code = strtoupper($rawCode);
                if (empty($code) || $code === '0' || $code === 'NULL' || strlen($code) > 80) {
                    $code = strtoupper(Str::slug($valueText, '_'));
                    if (empty($code) || strlen($code) > 80) {
                        $code = 'VAL_' . strtoupper(Str::random(8));
                    }
                }

                // Ensure uniqueness
                $finalCode = substr($code, 0, 80);
                $counter = 1;
                while (Keyvalue::where('keyword_code', $master->code)
                    ->where('code', $finalCode)
                    ->exists()) {
                    $finalCode = substr($code, 0, 70) . '_' . $counter++;
                }

                $parentId = !empty($val->parent_id) && $val->parent_id != 0 ? $val->parent_id : null;

                $created = Keyvalue::firstOrCreate(
                    ['keyword_code' => $master->code, 'code' => $finalCode],
                    [
                        'value'     => $valueText,
                        'key'       => trim($val->value_key ?? ''),
                        'is_active' => true,
                        'parent_id' => $parentId,
                        'level'     => 0,
                        'path'      => '',
                    ]
                );

                if ($created->wasRecentlyCreated) $added++;
            }

            echo "✅ {$keywordCode} → {$added} values\n";
        }

        echo "\n🎉 Migration completed successfully!\n";
    }
}