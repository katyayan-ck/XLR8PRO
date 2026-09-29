<?php

namespace Tests\Feature\IAM;

use App\Services\Platform\Settings\SettingsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** Go-live to-do S10: every response carries the baseline headers; the CSP mode follows the setting. */
class SecurityHeadersTest extends TestCase
{
    use DatabaseTransactions;

    public function test_baseline_headers_and_report_only_csp_by_default(): void
    {
        app(SettingsService::class)->reset('security.csp_mode');
        $this->get(route('backpack.auth.login'))->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeaderMissing('Content-Security-Policy')
            ->assertHeader('Content-Security-Policy-Report-Only');
        $this->getJson('/api/v1/vehicle/pricing/X')->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_the_csp_can_be_enforced_or_switched_off(): void
    {
        app(SettingsService::class)->set('security.csp_mode', 'enforce');
        $this->get(route('backpack.auth.login'))->assertHeader('Content-Security-Policy');
        app(SettingsService::class)->set('security.csp_mode', 'off');
        $this->get(route('backpack.auth.login'))->assertHeaderMissing('Content-Security-Policy')->assertHeaderMissing('Content-Security-Policy-Report-Only');
    }
}
