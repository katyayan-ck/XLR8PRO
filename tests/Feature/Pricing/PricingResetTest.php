<?php

namespace Tests\Feature\Pricing;

use App\Models\User;
use App\Services\Vehicle\Pricing\PricingResetService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * DEC-082: the destructive pricing reset is no longer a GET — GET only previews; the POST needs the permission, a local
 * environment and the typed confirmation. The reset itself is never run here.
 */
class PricingResetTest extends TestCase
{
    use DatabaseTransactions;

    public function test_get_only_previews_and_the_post_needs_the_typed_confirmation(): void
    {
        $this->mock(PricingResetService::class)->shouldNotReceive('run');
        $user = User::create(['username' => 'prs_'.uniqid(), 'password' => bcrypt('password'), 'user_type' => 'Emp', 'is_active' => 1]);
        $this->actingAs($user, backpack_guard_name());
        $this->get(route('pricing.reset'))->assertForbidden();

        $user->givePermissionTo(Permission::findOrCreate('PRC_RESET_MANAGE', 'web'));
        $this->actingAs($user->fresh(), backpack_guard_name());
        $this->get(route('pricing.reset', ['after' => '2026-08-20', 'confirm' => 1]))->assertOk()->assertSee('Dry preview');
        $this->post(route('pricing.reset.run'), ['after' => '2026-08-20', 'confirmation' => 'yes'])->assertSessionHasErrors('confirmation');

        $this->app['env'] = 'production';
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->post(route('pricing.reset.run'), ['after' => '2026-08-20', 'confirmation' => 'RESET'])->assertForbidden();
    }
}
