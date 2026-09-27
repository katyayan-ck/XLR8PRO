<?php

namespace Tests\Unit\Services;

use App\Services\OrgService;
use Tests\TestCase;

/**
 * BUG-186 (DEC-070): variantName() returns the variant's display name (it used to return the whole row array and
 * throw a TypeError).
 */
class OrgServiceNameLookupTest extends TestCase
{
    public function test_variant_name_returns_the_display_name(): void
    {
        $variants = OrgService::variants(null);
        if ($variants === []) {
            $this->markTestSkipped('Needs vehicle variants in xlrm_testing.');
        }
        $code = (string) array_key_first($variants);

        $this->assertSame($variants[$code]['name'] ?? $code, OrgService::variantName($code));
    }

    public function test_an_unknown_variant_falls_back_to_its_code(): void
    {
        $this->assertSame('NO-SUCH-VARIANT', OrgService::variantName('no-such-variant'));
    }
}
