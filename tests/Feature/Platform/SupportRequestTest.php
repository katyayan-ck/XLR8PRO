<?php

namespace Tests\Feature\Platform;

use App\Models\User;
use App\Models\Utilities\Support\SupportRequest;
use App\Models\Utilities\Ticket\Ticket;
use App\Services\Platform\Help\SupportRequestService;
use App\Services\Platform\Ticket\TicketService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Platform\Concerns\PlatformFixtures;
use Tests\TestCase;
use ZipArchive;

/**
 * Support requests (DEC-094, W16e; FRS §5 / §8.4–8.6): a request opens a SUP_* ticket owned by the least-loaded support
 * admin, keeps a masked diagnostic zip that only the requester, support admins and assignees may download, assigns
 * only support executives, and purges the zip after the retention period.
 */
class SupportRequestTest extends TestCase
{
    use DatabaseTransactions;
    use PlatformFixtures;

    /** 1×1 PNG */
    private const PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    /** @var list<User> requester, free admin, executive, outsider */
    private array $u;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->u = $this->staffWithDesignations(4)->map(fn ($r) => User::findOrFail($r->id))->all();
        [, $admin, $exec] = $this->u;
        $admin->givePermissionTo('UTL_SUPP_ADMIN');
        $exec->givePermissionTo('UTL_SUPP_EXEC');
        // every other support admin already has an open support ticket, so the new admin (none open) is least loaded
        foreach (app(SupportRequestService::class)->holders('UTL_SUPP_ADMIN') as $other) {
            if ($other !== $admin->id) {
                app(TicketService::class)->open(['category' => 'SUP_HOWTO', 'title' => 'busy', 'requester_id' => $other, 'owner_id' => $other], $other);
            }
        }
    }

    private function send(array $overrides = []): TestResponse
    {
        return $this->actingAs($this->u[0], 'backpack')->postJson(route('utils.support.store'), $overrides + [
            'category' => 'SUP_NOT_WORKING', 'urgent' => false, 'subject' => 'Bookings grid empty',
            'description' => 'Customer 9876543210 PAN ABCDE1234F cannot be found', 'route' => 'sales.booking.index', 'diagnostics' => true,
            'snapshot' => ['page' => ['url' => '/admin/sales/booking', 'title' => 'Bookings'],
                'actions' => [['type' => 'submit', 'fields' => ['mobile', 'pan_no']], ['type' => 'message', 'text' => 'Aadhaar 2345 6789 0123 not valid']],
                'network' => [['method' => 'GET', 'url' => '/admin/x', 'status' => 500, 'error' => 'mail ravi@example.com']], 'errors' => []],
            'screenshot' => self::PNG,
        ]);
    }

    public function test_a_request_opens_a_ticket_for_the_least_loaded_admin_with_a_masked_bundle(): void
    {
        $response = $this->send()->assertCreated()->assertJsonPath('ok', true);
        $ticket = Ticket::query()->findOrFail($response->json('data.ticket_id'));
        $this->assertSame(['SUP_NOT_WORKING', 'P3', $this->u[1]->id], [$ticket->category, $ticket->priority, (int) $ticket->owner_id]);
        $this->assertStringNotContainsString('ABCDE1234F', (string) $ticket->details);

        $request = SupportRequest::query()->findOrFail($response->json('data.id'));
        $zip = new ZipArchive;
        $this->assertTrue($zip->open(Storage::disk('local')->path($request->bundle_path)) === true);
        $names = array_map(fn ($i) => $zip->getNameIndex($i), range(0, $zip->numFiles - 1));
        $this->assertEqualsCanonicalizing(['page.json', 'actions.json', 'network.json', 'errors.json', 'server.json', 'user.json', 'screenshot.png'], $names);
        $text = implode("\n", array_map(fn ($n) => $n === 'screenshot.png' ? '' : $zip->getFromName($n), $names));
        $zip->close();
        foreach (['2345 6789 0123', 'ravi@example.com'] as $raw) {
            $this->assertStringNotContainsString($raw, $text, "{$raw} is masked in the bundle");
        }
        $this->assertStringContainsString('XXXXXXXX0123', $text);
    }

    public function test_only_the_requester_admins_and_assignees_download_and_only_executives_are_assigned(): void
    {
        [$requester, $admin, $exec, $outsider] = $this->u;
        $id = $this->send()->assertCreated()->json('data.id');
        $url = route('utils.support.download', ['id' => $id]);

        $this->actingAs($requester, 'backpack')->get($url)->assertOk();
        $this->flushSession();
        $this->actingAs($outsider, 'backpack')->get($url)->assertForbidden();
        $this->flushSession();
        $this->actingAs($exec, 'backpack')->get($url)->assertForbidden();

        $this->flushSession();
        $this->actingAs($admin, 'backpack')->get($url)->assertOk();
        $this->from(route('utils.support.index'))->post(route('utils.support.assign', ['id' => $id]), ['executives' => [$outsider->id]])->assertSessionHas('error');
        $this->from(route('utils.support.index'))->post(route('utils.support.assign', ['id' => $id]), ['executives' => [$exec->id]])->assertSessionHas('success');
        $this->get(route('utils.support.index'))->assertOk()->assertSee(SupportRequest::query()->findOrFail($id)->ticket->number);

        $this->flushSession();
        $this->actingAs($exec, 'backpack')->get($url)->assertOk();
    }

    public function test_the_bundle_is_purged_after_the_retention_period_and_bad_input_is_refused(): void
    {
        $this->send(['category' => 'NOT_A_CATEGORY'])->assertStatus(422);

        $id = $this->send()->json('data.id');
        SupportRequest::query()->whereKey($id)->update(['created_at' => now()->subDays(91)]);
        $this->assertSame(1, app(SupportRequestService::class)->purge());

        $this->actingAs($this->u[0], 'backpack')->get(route('utils.support.download', ['id' => $id]))->assertStatus(410);
        $this->assertNotNull(SupportRequest::query()->findOrFail($id)->ticket, 'the ticket stays');
    }
}
