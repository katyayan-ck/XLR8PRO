<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

/**
 * DEC-095 #8 (D13, BUG-056 / BUG-062): menu items whose screen is not built yet open the "coming soon" page; no rendered
 * menu link points at a URL without a route.
 */
class MenuLinksTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::whereHas('roles', fn ($q) => $q->where('name', 'superadmin'))->firstOrFail(), 'backpack');
    }

    public function test_the_coming_soon_page_names_the_feature_and_escapes_it(): void
    {
        $this->get('/admin/coming-soon?feature=Dummy+Bookings')->assertOk()->assertSee('Dummy Bookings')->assertSee(__('utils.coming_soon.title'));

        $this->get('/admin/coming-soon?feature='.urlencode('<script>x</script>'))->assertOk()
            ->assertDontSee('<script>x</script>', false);
    }

    public function test_every_admin_link_in_the_menu_has_a_route(): void
    {
        $html = preg_replace('/<!--.*?--!?>/s', '', $this->get('/admin/dashboard')->assertOk()->getContent());   // commented-out items are not shown
        preg_match_all('/<a\s[^>]*class="dropdown-item(?![^"]*dropdown-toggle)[^"]*"[^>]*href="([^"]*)"/', $html, $m);   // toggles open sub-menus
        $this->assertNotEmpty($m[1], 'the menu rendered');

        $dead = [];
        foreach (array_unique($m[1]) as $href) {
            if ($href === '#') {
                $dead[] = '#';

                continue;
            }
            $path = parse_url(html_entity_decode($href), PHP_URL_PATH) ?? '';
            if (! str_starts_with($path, '/admin')) {
                continue;
            }
            try {
                app('router')->getRoutes()->match(Request::create($path));
            } catch (NotFoundHttpException) {
                $dead[] = $path;
            } catch (MethodNotAllowedHttpException) {
                // the URL exists for another method
            }
        }

        $this->assertSame([], $dead, 'menu links without a route');
    }
}
