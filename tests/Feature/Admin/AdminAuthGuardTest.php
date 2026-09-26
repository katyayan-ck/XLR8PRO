<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * BUG-055: during admin requests auth()/@can/Gate must resolve the backpack-authenticated user,
 * and Spatie permissions (guard `web`) must keep working after the default guard switches.
 */
class AdminAuthGuardTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(array_merge((array) config('backpack.base.web_middleware', 'web'), (array) config('backpack.base.middleware_key', 'admin')))
            ->get('admin/__guard-probe', fn () => response()->json([
                'auth_id' => auth()->id(),
                'gate' => Gate::allows('admin.dashboard'),
                'backpack_can' => backpack_user()->can('admin.dashboard'),
            ]));
    }

    public function test_admin_requests_resolve_the_backpack_user_for_auth_and_gate(): void
    {
        $user = User::where('is_active', 1)->get()
            ->first(fn (User $u) => ! $u->isSuperAdmin() && $u->hasPermissionTo('admin.dashboard'));
        if (! $user) {
            $this->markTestSkipped('No non-superadmin user with admin.dashboard.');
        }

        $this->actingAs($user, 'backpack')
            ->get('/admin/__guard-probe')
            ->assertOk()
            ->assertExactJson(['auth_id' => $user->id, 'gate' => true, 'backpack_can' => true]);
    }
}
