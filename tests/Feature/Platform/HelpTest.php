<?php

namespace Tests\Feature\Platform;

use App\Models\User;
use App\Models\Utilities\Help\HelpUsage;
use App\Services\Platform\Help\HelpUsageService;
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

    /** W16f: opens, missing screens, searches (masked), finished tours are logged; settings managers see the report; old rows purge. */
    public function test_help_usage_is_logged_reported_and_purged(): void
    {
        HelpUsage::query()->delete();
        $super = $this->superadmin();
        $this->actingAs($super, 'backpack');

        $this->getJson(route('utils.help.pane', ['route' => 'sales.booking.edit']))->assertOk();
        $this->getJson(route('utils.help.pane', ['route' => 'org.branch.index']))->assertOk();
        $this->getJson(route('utils.help.search', ['q' => 'booking']))->assertOk();
        $this->getJson(route('utils.help.search', ['q' => 'ABCDE1234F']))->assertOk()->assertJsonCount(0, 'results');
        $this->postJson(route('utils.help.track'), ['event' => 'TOUR_DONE', 'key' => 'sales/booking/edit'])->assertOk();
        $this->postJson(route('utils.help.track'), ['event' => 'OPEN', 'key' => 'x'])->assertStatus(422);   // only tours come from the browser

        $rows = HelpUsage::query()->orderBy('id')->get(['event', 'ref'])->map(fn ($r) => $r->event.':'.$r->ref)->all();
        $this->assertSame(['OPEN:sales/booking/edit', 'MISSING:org.branch.index', 'SEARCH:booking', 'SEARCH_EMPTY:xxxxxx234f', 'TOUR_DONE:sales/booking/edit'], $rows);

        $this->get(route('utils.help.index'))->assertOk()->assertSee('data-xl-help-usage', false)->assertSee('xxxxxx234f');
        $this->flushSession();
        $this->actingAs($this->bookingViewer(), 'backpack')->get(route('utils.help.index'))->assertOk()->assertDontSee('data-xl-help-usage', false);

        HelpUsage::query()->update(['created_at' => now()->subDays(181)]);
        $this->assertSame(5, app(HelpUsageService::class)->purge());
    }

    public function test_every_admin_page_carries_the_help_pane(): void
    {
        $this->actingAs($this->superadmin(), 'backpack')->get('/admin/dashboard')
            ->assertOk()->assertSee('name="xl-help"', false)->assertSee('js/xl-help.js', false)->assertSee('data-xl-help-open', false)
            ->assertDontSee('driver.js', false);   // no tour on this page → Driver.js is not loaded
    }

    /** W16c: a screen whose article has a tour loads Driver.js and tells the page (key, updated, tour) for the "new" dot. */
    public function test_a_screen_with_a_tour_loads_the_tour_runner_and_announces_its_article(): void
    {
        File::put($this->dir.'/dashboard.md', "---\ntitle: Dashboard\nroutes: [backpack.dashboard]\nupdated: 2026-10-03\ntour:\n  - { element: '.page-title', title: Hello, text: Your dashboard }\n---\nThe dashboard.\n");

        $page = $this->actingAs($this->superadmin(), 'backpack')->get('/admin/dashboard')->assertOk()->assertSee('driver.js@1.3.1', false);
        preg_match('/<meta name="xl-help" content="([^"]+)"/', $page->getContent(), $m);
        $meta = json_decode(html_entity_decode($m[1] ?? ''), true);
        $this->assertSame(['key' => 'dashboard', 'updated' => '2026-10-03', 'tour' => true], $meta['article'] ?? null);
    }
}
