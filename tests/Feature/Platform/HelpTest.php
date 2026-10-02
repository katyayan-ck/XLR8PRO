<?php

namespace Tests\Feature\Platform;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * F1 help engine (DEC-094, W16b): route → article, overview fallback, missing fallback, `::: can` sections per user,
 * article permissions, search without hidden text, escaped raw HTML, and the pane wiring on every admin page.
 */
class HelpTest extends TestCase
{
    use DatabaseTransactions;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'xl-help-'.uniqid();
        File::ensureDirectoryExists($this->dir.'/sales/booking');
        File::put($this->dir.'/sales/booking/edit.md', <<<'MD'
            ---
            title: Booking — edit
            routes: [sales.booking.edit]
            module: SLS
            permissions: [SLS_BKNG_VIEW]
            updated: 2026-10-01
            tour:
              - { element: '[data-xl-tour=customer]', title: Customer, text: Who is buying }
            ---
            ## What this screen is for
            Change a booking's customer and vehicle.

            ::: can SLS_BKNG_ORDER_APPROVE
            ### For approvers
            Approve the zebra order here.
            :::

            <script>alert(1)</script>
            MD);
        File::put($this->dir.'/sales/booking/_process.md', "---\ntitle: Booking overview\nroute_prefix: sales.booking\nmodule: SLS\n---\nAll about bookings.\n");
        File::put($this->dir.'/secret.md', "---\ntitle: Settings secrets\npermissions: [UTL_SETTINGS_MANAGE]\n---\nOnly for managers.\n");
        config(['platform.help.path' => $this->dir]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    private function superadmin(): User
    {
        return User::role('superadmin')->where('is_active', 1)->firstOrFail();
    }

    private function bookingViewer(): User
    {
        $user = User::query()->where('is_active', 1)->where('user_type', 'Emp')->get()
            ->first(fn (User $u) => ! $u->isSuperAdmin() && ! $u->can('SLS_BKNG_ORDER_APPROVE') && ! $u->can('UTL_SETTINGS_MANAGE'))
            ?? $this->markTestSkipped('Needs a non-superadmin staff user.');
        $user->givePermissionTo('SLS_BKNG_VIEW');

        return $user;
    }

    public function test_the_pane_serves_the_screen_article_with_sections_per_permission(): void
    {
        $this->actingAs($this->superadmin(), 'backpack')->getJson(route('utils.help.pane', ['route' => 'sales.booking.edit']))
            ->assertOk()->assertJsonPath('missing', false)->assertJsonPath('title', 'Booking — edit')
            ->assertJsonPath('tour.0.title', 'Customer')
            ->assertJsonFragment(['key' => 'sales/booking/edit'])
            ->assertSee('For approvers');

        $this->flushSession();
        $viewer = $this->actingAs($this->bookingViewer(), 'backpack')->getJson(route('utils.help.pane', ['route' => 'sales.booking.edit']))->assertOk();
        $this->assertStringContainsString('What this screen is for', $viewer->json('html'));
        $this->assertStringNotContainsString('For approvers', $viewer->json('html'), 'a ::: can section is hidden without the permission');
        $this->assertStringNotContainsString('<script>', $viewer->json('html'), 'raw HTML in an article is escaped');
    }

    public function test_an_unlisted_route_falls_back_to_the_overview_then_to_missing(): void
    {
        $this->actingAs($this->superadmin(), 'backpack');

        $this->getJson(route('utils.help.pane', ['route' => 'sales.booking.show']))->assertJsonPath('title', 'Booking overview');
        $this->getJson(route('utils.help.pane', ['route' => 'org.branch.index']))->assertJsonPath('missing', true);
    }

    public function test_article_permissions_and_search_follow_the_user(): void
    {
        $this->actingAs($this->bookingViewer(), 'backpack');

        $this->get(route('utils.help.show', ['key' => 'secret']))->assertNotFound();
        $this->get(route('utils.help.show', ['key' => 'sales/booking/edit']))->assertOk()->assertSee('Booking — edit');
        $this->getJson(route('utils.help.search', ['q' => 'booking']))->assertOk()->assertJsonFragment(['title' => 'Booking — edit']);
        $this->getJson(route('utils.help.search', ['q' => 'zebra']))->assertOk()->assertJsonCount(0, 'results');
        $this->getJson(route('utils.help.search', ['q' => 'managers']))->assertOk()->assertJsonCount(0, 'results');
        $this->get(route('utils.help.index'))->assertOk()->assertSee('Booking — edit')->assertDontSee('Settings secrets');
    }

    public function test_every_admin_page_carries_the_help_pane(): void
    {
        $this->actingAs($this->superadmin(), 'backpack')->get('/admin/dashboard')
            ->assertOk()->assertSee('name="xl-help"', false)->assertSee('js/xl-help.js', false)->assertSee('data-xl-help-open', false);
    }
}
