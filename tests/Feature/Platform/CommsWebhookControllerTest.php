<?php

namespace Tests\Feature\Platform;

use App\Services\Platform\Settings\SettingsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Inbound comms webhooks: signature and idempotency (DEC-064).
 */
class CommsWebhookControllerTest extends TestCase
{
    use DatabaseTransactions;

    private function postEvent(array $body, ?string $signature)
    {
        $json = json_encode($body);
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
        if ($signature !== null) {
            $server['HTTP_X_SIGNATURE'] = $signature;
        }

        return $this->call('POST', '/api/webhooks/comms/sms', [], [], [], $server, $json);
    }

    public function test_returns_401_when_the_signature_is_wrong(): void
    {
        app(SettingsService::class)->set('comms.webhook_secret', 'secret-1');

        $this->postEvent(['event_id' => 'sig-1', 'type' => 'dlr', 'message_id' => 'x'], 'bad')->assertUnauthorized();
    }

    public function test_repeated_event_is_accepted_once(): void
    {
        app(SettingsService::class)->set('comms.webhook_secret', 'secret-1');
        $body = ['event_id' => 'dup-1', 'type' => 'dlr', 'message_id' => 'none', 'status' => 'DELIVERED'];
        $signature = hash_hmac('sha256', json_encode($body), 'secret-1');

        $this->postEvent($body, $signature)->assertOk();

        $this->postEvent($body, $signature)->assertOk()->assertJson(['duplicate' => true]);
    }

    public function test_returns_404_for_an_unknown_channel(): void
    {
        $this->call('POST', '/api/webhooks/comms/fax', [], [], [], ['CONTENT_TYPE' => 'application/json'], '{}')->assertNotFound();
    }
}
