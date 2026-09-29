<?php

namespace Tests\Feature\Api;

use App\Enums\ErrorCodeEnum;
use App\Exceptions\DomainException;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

/**
 * DEC-085 / BUG-208 (to-do U7): every exception on api/* answers with the standard envelope and the status Laravel
 * already sent; a 5xx shows a reference id and never the internals; messages come from resources/lang/en/errors.php.
 */
class ApiErrorEnvelopeTest extends TestCase
{
    public function test_an_unauthenticated_call_gets_the_envelope_with_the_same_status_and_message(): void
    {
        $this->freezeTime();

        $this->getJson('/api/v1/vehicle/pricing/ANY')
            ->assertStatus(401)
            ->assertExactJson([
                'http_status' => 401, 'success' => false, 'code' => 'AUTH_UNAUTHORIZED', 'message' => 'Unauthenticated.',
                'timestamp' => now()->toIso8601String(),
            ]);
    }

    public function test_unknown_routes_and_wrong_methods_get_the_envelope(): void
    {
        $this->getJson('/api/v1/no-such-endpoint')->assertStatus(404)
            ->assertJson(['success' => false, 'code' => 'RESOURCE_NOT_FOUND', 'message' => __('errors.RESOURCE_NOT_FOUND')])
            ->assertJsonMissingPath('trace');

        $this->deleteJson('/api/v1/vehicle/pricing/ANY')->assertStatus(405)
            ->assertJson(['success' => false, 'code' => 'REQUEST_METHOD_NOT_ALLOWED']);
    }

    public function test_a_crash_shows_a_reference_and_no_internals(): void
    {
        config(['app.debug' => false]);
        Route::middleware('api')->get('api/test-envelope-crash', fn () => throw new RuntimeException('SQLSTATE[42S02] secret_table'));

        $response = $this->getJson('/api/test-envelope-crash')->assertStatus(500)
            ->assertJson(['success' => false, 'code' => 'SYSTEM_ERROR', 'message' => 'An unexpected error occurred'])
            ->assertJsonMissingPath('debug');

        $this->assertMatchesRegularExpression('/^[A-Z0-9]{8}$/', (string) $response->json('error_ref'));
        $this->assertStringNotContainsString('secret_table', (string) $response->getContent());
    }

    public function test_a_domain_exception_uses_its_code_status_and_message(): void
    {
        Route::middleware('api')->get('api/test-envelope-domain', fn () => throw new DomainException('', ErrorCodeEnum::POST_OCCUPIED));

        $this->getJson('/api/test-envelope-domain')->assertStatus(422)
            ->assertJson(['success' => false, 'code' => 'POST_OCCUPIED', 'message' => __('errors.POST_OCCUPIED')]);
    }

    public function test_every_code_has_a_message_in_the_language_file(): void
    {
        foreach (ErrorCodeEnum::cases() as $code) {
            $this->assertTrue(trans()->has('errors.'.$code->value), "{$code->value} has no line in resources/lang/en/errors.php");
        }
        $this->assertSame(422, ErrorCodeEnum::VALIDATION_FAILED->statusCode());
    }

    public function test_web_requests_keep_their_branded_pages(): void
    {
        $this->get('/no-such-page-anywhere')->assertStatus(404)->assertHeader('content-type', 'text/html; charset=utf-8');
    }
}
