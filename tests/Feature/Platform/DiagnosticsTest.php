<?php

namespace Tests\Feature\Platform;

use App\Models\User;
use App\Services\Platform\Help\DiagnosticsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Support diagnostics (DEC-094, W16d): personal data is masked before it leaves the app, secret URL values are removed,
 * and every admin request lands in the user's short server trail (capped, heartbeat skipped).
 */
class DiagnosticsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_personal_data_and_secrets_are_masked(): void
    {
        $this->assertSame(
            'Aadhaar XXXXXXXX9012, XXXXXXXX9012, PAN XXXXXX234F, mobile XXXXXX3210, XXXXXX3210, mail r***@example.com, [token]',
            DiagnosticsService::mask('Aadhaar 2345 6789 9012, 2345-6789-9012, PAN ABCDE1234F, mobile 9876543210, +91 9876543210, mail ravi.k@example.com, '.str_repeat('a1B2', 10))
        );
        $this->assertSame('TRC 123456789', DiagnosticsService::mask('TRC 123456789'), 'other numbers stay');
        $this->assertSame('/admin/x?otp=[removed]&page=2&_token=[removed]', DiagnosticsService::cleanUrl('https://host/admin/x?otp=123456&page=2&_token=abc'));
    }

    public function test_admin_requests_are_kept_in_a_short_capped_trail(): void
    {
        $user = User::role('superadmin')->where('is_active', 1)->firstOrFail();
        $diag = app(DiagnosticsService::class);
        foreach (range(1, DiagnosticsService::TRAIL_SIZE + 5) as $i) {
            $diag->record($user->id, ['route' => "old.{$i}", 'status' => 200]);
        }

        $this->actingAs($user, 'backpack')->get('/admin/dashboard?token=secret123')->assertOk()
            ->assertSee('name="xl-diag"', false)->assertSee('js/xl-diag.js', false);
        $this->postJson(route('xl.session.activity'));

        $trail = $diag->trail($user->id);
        $this->assertCount(DiagnosticsService::TRAIL_SIZE, $trail);
        $this->assertSame(['GET', 'backpack.dashboard', '/admin/dashboard?token=[removed]', 200, null],
            [$trail[0]['method'], $trail[0]['route'], $trail[0]['path'], $trail[0]['status'], $trail[0]['ref']]);
        $this->assertNotContains('xl.session.activity', array_column($trail, 'route'), 'the idle heartbeat is not recorded');
    }
}
