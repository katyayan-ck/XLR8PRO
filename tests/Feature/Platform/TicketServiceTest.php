<?php

namespace Tests\Feature\Platform;

use App\Models\Utilities\Noty\Alert;
use App\Models\Utilities\Ticket\Ticket;
use App\Services\Platform\Ticket\TicketService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Platform\Concerns\PlatformFixtures;
use Tests\TestCase;

/**
 * Ticket numbering, legal edges and the SLA clock (FRS §6, DEC-062).
 */
class TicketServiceTest extends TestCase
{
    use DatabaseTransactions, PlatformFixtures;

    private TicketService $tickets;

    /** @var list<int> requester, assignee */
    private array $users;

    protected function setUp(): void
    {
        parent::setUp();
        $this->users = $this->staffWithDesignations(2)->pluck('id')->all();
        $this->tickets = app(TicketService::class);
    }

    private function open(string $priority = 'P2'): Ticket
    {
        $result = $this->tickets->open(['title' => 'Quote PDF 500', 'category' => 'BUG', 'priority' => $priority], $this->users[0]);

        return Ticket::query()->findOrFail($result->get('id'));
    }

    public function test_number_follows_the_branch_fy_sequence_format_and_increments(): void
    {
        $first = $this->open();
        $second = $this->open();

        $this->assertMatchesRegularExpression('#^TCK/[A-Z0-9_-]+/\d\d-\d\d/\d{5}$#', $first->number);
        $this->assertSame($first->seq + 1, $second->seq);
    }

    public function test_due_time_comes_from_the_priority_setting(): void
    {
        $this->freezeSecond();

        $this->assertTrue($this->open('P2')->due_at->equalTo(now()->addHours(8)));
    }

    public function test_waiting_on_the_requester_pauses_the_clock(): void
    {
        $this->freezeSecond();
        $ticket = $this->open();
        $admin = $this->superAdmin()->id;
        $this->tickets->transition($ticket->id, $admin, 'ACKNOWLEDGED');
        $this->tickets->transition($ticket->id, $admin, 'WAITING_USER', 'need a screenshot');
        $due = $ticket->fresh()->due_at;

        $this->travel(90)->minutes();
        $this->tickets->transition($ticket->id, $admin, 'INPROGRESS');

        $this->assertTrue($ticket->fresh()->due_at->equalTo($due->copy()->addMinutes(90)));
        $this->assertTrue(Alert::query()->where('user_id', $this->users[0])->where('reference_id', $ticket->id)->exists());
    }

    public function test_requester_cannot_resolve_and_force_close_needs_a_reason(): void
    {
        $ticket = $this->open();
        $admin = $this->superAdmin()->id;
        $this->tickets->transition($ticket->id, $admin, 'ACKNOWLEDGED');

        $this->assertSame('FORBIDDEN_TRANSITION', $this->tickets->transition($ticket->id, $this->users[0], 'RESOLVED')->code);
        $this->assertSame('REASON_REQUIRED', $this->tickets->transition($ticket->id, $admin, 'CLOSED')->code);
    }

    public function test_breach_is_flagged_once(): void
    {
        $ticket = $this->open('P1');
        $ticket->forceFill(['due_at' => now()->subHour()])->save();

        $this->assertGreaterThanOrEqual(1, $this->tickets->flagBreaches());
        $this->assertNotNull($ticket->fresh()->breached_at);
        $this->assertSame(0, $this->tickets->flagBreaches());
    }
}
