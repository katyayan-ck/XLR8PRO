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
 * admin and keeps a masked diagnostic zip that only the support team (admins, owner, assignees, snoopers) may see on
 * the ticket page and download — never the requester. Support tickets take only support executives as assignees; the
 * zip is purged after the retention period.
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

    /** Owner 03-10: the requester neither sees, downloads nor removes the diagnostics; nothing about them is in the chat. */
    public function test_the_requester_cannot_see_download_or_remove_the_diagnostics(): void
    {
        $requester = $this->u[0];
        $id = $this->send()->assertCreated()->json('data.id');
        $ticketId = SupportRequest::query()->findOrFail($id)->ticket_id;

        $this->actingAs($requester, 'backpack')->get(route('utils.support.download', ['id' => $id]))->assertForbidden();
        $this->get(route('utils.tickets.show', ['id' => $ticketId]))->assertOk()
            ->assertDontSee('data-xl-diagnostics', false)->assertDontSee('data:image/png;base64,', false)
            ->assertDontSee('Diagnostics attached')->assertDontSee('Remove this remark?');
    }

    /** The support team (admins, owner, assignees, snoopers) sees the diagnostics on the ticket page; assignees are executives only. */
    public function test_the_support_team_sees_the_diagnostics_on_the_ticket_and_only_executives_are_assigned(): void
    {
        [, $admin, $exec, $outsider] = $this->u;
        $id = $this->send()->assertCreated()->json('data.id');
        $ticketId = SupportRequest::query()->findOrFail($id)->ticket_id;
        $download = route('utils.support.download', ['id' => $id]);
        $page = route('utils.tickets.show', ['id' => $ticketId]);

        $this->flushSession();   // send() signed in as the requester
        $this->actingAs($admin, 'backpack')->get($page)->assertOk()
            ->assertSee('data-xl-diagnostics', false)->assertSee('data:image/png;base64,', false)
            ->assertSee('XXXXXXXX0123')->assertDontSee('2345 6789 0123')->assertSee('sales.booking.index');
        $this->get($download)->assertOk();

        $tickets = app(TicketService::class);
        $this->assertSame('SUPPORT_NOT_EXECUTIVE', $tickets->update($ticketId, ['owner_id' => $admin->id, 'assignees' => [$outsider->id]], $admin->id)->code);
        $this->assertTrue($tickets->update($ticketId, ['owner_id' => $admin->id, 'assignees' => [$exec->id], 'snoopers' => [$outsider->id]], $admin->id)->ok);

        foreach ([$exec, $outsider] as $member) {   // assignee, snooper
            $this->flushSession();
            $this->actingAs($member, 'backpack')->get($page)->assertOk()->assertSee('data-xl-diagnostics', false);
            $this->get($download)->assertOk();
        }
    }

    public function test_the_bundle_is_purged_after_the_retention_period_and_bad_input_is_refused(): void
    {
        $this->send(['category' => 'NOT_A_CATEGORY'])->assertStatus(422);

        $id = $this->send()->json('data.id');
        SupportRequest::query()->whereKey($id)->update(['created_at' => now()->subDays(91)]);
        $this->assertSame(1, app(SupportRequestService::class)->purge());

        $this->flushSession();
        $this->actingAs($this->u[1], 'backpack')->get(route('utils.support.download', ['id' => $id]))->assertStatus(410);
        $this->assertNotNull(SupportRequest::query()->findOrFail($id)->ticket, 'the ticket stays');
    }
}
