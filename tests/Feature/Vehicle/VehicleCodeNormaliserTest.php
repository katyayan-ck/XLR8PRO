<?php

namespace Tests\Feature\Vehicle;

use App\Models\Vehicle\VehicleModel;
use App\Services\Vehicle\VehicleCodeNormaliser;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * DEC-049: codes are upper-case with hyphens for spaces ("THAR ROXX" / "THARROXX" → "THAR-ROXX"),
 * both for new input (column transform) and for existing data (VehicleCodeNormaliser).
 */
class VehicleCodeNormaliserTest extends TestCase
{
    use DatabaseTransactions;

    /** @return array<string, array{string, string}> */
    public static function codes(): array
    {
        return [
            'spaces' => ['THAR ROXX', 'THAR-ROXX'],
            'mixed case and padding' => ['  xuv 3xo ', 'XUV-3XO'],
            'spaced hyphen' => ['Thar - Roxx', 'THAR-ROXX'],
            'existing hyphen' => ['SCORPIO-N', 'SCORPIO-N'],
            'plus sign' => ['Bolero Neo +', 'BOLERO-NEO-PLUS'],
            'full OEM code untouched' => ['1AM2NR1T9LVB1', '1AM2NR1T9LVB1'],
            'underscore kept' => ['NON_XUV', 'NON_XUV'],
        ];
    }

    #[DataProvider('codes')]
    public function test_code_transform_uses_hyphens(string $input, string $expected): void
    {
        $this->assertSame($expected, (new VehicleModel)->transformRaw($input, ['trim', 'uppercase_alphanumeric_dash_underscore']));
        $this->assertSame($expected, VehicleCodeNormaliser::canonical($input));
    }

    public function test_normaliser_reconnects_orphaned_variants_and_is_idempotent(): void
    {
        $normaliser = app(VehicleCodeNormaliser::class);
        $normaliser->apply();

        $orphans = DB::table('xlr8_vehicle_variant as v')
            ->leftJoin('xlr8_vehicle_model as m', 'm.code', '=', 'v.model_code')
            ->whereNull('m.id')
            ->whereNotNull('v.model_code')
            ->pluck('v.model_code')->unique()->values()->all();

        // Only codes with no model at all may remain orphaned (not a spelling mismatch).
        $squash = fn ($c) => strtoupper(str_replace([' ', '-'], '', (string) $c));
        $models = DB::table('xlr8_vehicle_model')->pluck('code')->map($squash)->all();
        $this->assertSame([], array_values(array_filter($orphans, fn ($c) => in_array($squash($c), $models, true))));

        $this->assertSame(0, DB::table('xlr8_vehicle_model')->whereRaw("code REGEXP '[[:space:]]'")->count());
        $this->assertSame(['model' => [], 'sub_segment' => []], $normaliser->plan(), 'second run has nothing to do');
    }

    public function test_model_keyword_codes_are_hyphenated_with_their_references(): void
    {
        $normaliser = app(VehicleCodeNormaliser::class);
        $normaliser->applyModelKeywords();

        $this->assertSame(0, DB::table('xlr8_utils_keyvalue')->whereIn('keyword_code', VehicleCodeNormaliser::MODEL_KEYWORDS)->whereRaw("code REGEXP '[[:space:]]'")->count());
        $this->assertSame(0, DB::table('xlr8_crm_enquiries')->where('model', 'SCORPIO CLASSIC')->count());
        $this->assertSame([], $normaliser->applyModelKeywords(true)['map'], 'second run has nothing to do');
        // Label-type keywords keep their text (pricing header synonyms are matched verbatim).
        $this->assertGreaterThan(0, DB::table('xlr8_utils_keyvalue')->where('keyword_code', 'PL-LABEL-MAPPING')->whereRaw("code REGEXP '[[:space:]]'")->count());
    }
}
