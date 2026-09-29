<?php

namespace Tests\Feature\Utils;

use App\Support\ErrorRef;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Go-live to-do U8: users see branded error pages (logo, plain message, a way back) — never a framework page or a stack
 * trace; a 500 shows a reference id that is also in the log context.
 */
class ErrorPagesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.debug' => false]);
        ErrorRef::reset();
        Route::get('/__test/boom', fn () => throw new \RuntimeException('secret internals: SQLSTATE password=xyz'));
        Route::get('/__test/denied', fn () => abort(403, 'You do not have permission to view the pricing process.'));
    }

    public function test_404_is_branded(): void
    {
        $this->get('/__no_such_page__')->assertNotFound()->assertSee('Page not found')->assertSee('xl-errors.css', false);
    }

    public function test_403_shows_the_reason_given(): void
    {
        $this->get('/__test/denied')->assertForbidden()->assertSee('Access denied')->assertSee('permission to view the pricing process');
    }

    public function test_500_hides_internals_and_shows_a_reference(): void
    {
        $response = $this->get('/__test/boom')->assertStatus(500)->assertSee('Something went wrong')->assertDontSee('SQLSTATE')->assertDontSee('password=xyz');
        $response->assertSee(ErrorRef::get());
    }

    public function test_json_requests_still_get_json(): void
    {
        $this->getJson('/__test/boom')->assertStatus(500)->assertJsonMissing(['message' => 'secret internals: SQLSTATE password=xyz']);
    }
}
