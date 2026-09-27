<?php

namespace Tests\Feature\Platform;

use App\Models\Approval\ApprovalRequest;
use App\Models\Approval\ApprovalRule;
use App\Models\Approval\ApprovalTopic;
use App\Services\Platform\Approval\ApprovalService;
use App\Services\Platform\Approval\Entities\ApprovalRuleService;
use App\Services\Platform\Approval\RuleService;
use App\Services\Platform\Approval\TopicService;
use App\Services\Platform\Settings\SettingsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Platform\Concerns\PlatformFixtures;
use Tests\TestCase;

/**
 * Approval engine decisions (FRS §7.5) and rule precedence (FRS §8.3, DEC-063).
 */
class ApprovalServiceTest extends TestCase
{
    use DatabaseTransactions, PlatformFixtures;

    /** @var list<int> requester, L2, L3, L4, L5 user ids */
    private array $users;

    private ApprovalService $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $staff = $this->staffWithDesignations(5);
        $this->users = $staff->pluck('id')->all();
        $this->approvalRule('extra_disc', $staff->map(fn ($s, $i) => [$s->designation_code, [3000, 5000, 8000, 12000, 20000][$i]])->all(), ['model' => 'NEXON']);
        app(SettingsService::class)->set('approval.auto_accept_own_power', false);
        $this->engine = app(ApprovalService::class);
    }

    private function open(float $ask = 8000): ApprovalRequest
    {
        $result = $this->engine->open(['type' => 'QUOTE', 'id' => 8821], 'DISCOUNT.EXTRA', 'extra_disc', ['asked' => $ask, 'scope' => ['model' => 'NEXON']], $this->users[0]);
        $this->assertTrue($result->ok, $result->message);

        return ApprovalRequest::query()->findOrFail($result->get('id'));
    }

    public function test_highest_level_counter_wins_over_a_larger_lower_level_counter(): void
    {
        $request = $this->open();
        $this->engine->counter($request->id, $this->users[1], 5000);
        $this->engine->counter($request->id, $this->users[3], 3000);

        $effective = $this->engine->effective($request->id);

        $this->assertSame([4, 3000.0], [$effective['level'], $effective['value']]);
    }

    public function test_latest_counter_at_the_same_level_replaces_the_earlier_one(): void
    {
        $request = $this->open();
        $this->engine->counter($request->id, $this->users[3], 3000);
        $this->engine->counter($request->id, $this->users[3], 4000);

        $this->assertSame(4000.0, $this->engine->effective($request->id)['value']);
    }

    public function test_zero_counter_from_the_top_level_is_the_effective_grant(): void
    {
        $request = $this->open();
        $this->engine->counter($request->id, $this->users[3], 4000);
        $this->engine->counter($request->id, $this->users[4], 0);

        $this->assertSame([5, 0.0], [$this->engine->effective($request->id)['level'], $this->engine->effective($request->id)['value']]);
    }

    public function test_revising_the_ask_leaves_older_counters_stale(): void
    {
        $request = $this->open();
        $this->engine->counter($request->id, $this->users[3], 4000);

        $this->engine->reviseAsk($request->id, $this->users[0], 7000);

        $this->assertNull($this->engine->effective($request->id));
        $this->assertSame('NO_GRANT', $this->engine->close($request->id, $this->users[0], 'ACCEPTED')->code);
    }

    public function test_counter_above_the_level_maximum_is_rejected(): void
    {
        $request = $this->open();

        $this->assertSame('EXCEEDS_POWER', $this->engine->counter($request->id, $this->users[1], 6000)->code);
    }

    public function test_only_the_requester_may_revise_or_close(): void
    {
        $request = $this->open();
        $this->engine->counter($request->id, $this->users[2], 7000);

        $this->assertSame('FORBIDDEN', $this->engine->reviseAsk($request->id, $this->users[2], 1)->code);
        $this->assertSame('FORBIDDEN', $this->engine->close($request->id, $this->users[2], 'ACCEPTED')->code);
        $this->assertSame(7000.0, $this->engine->close($request->id, $this->users[0], 'ACCEPTED')->get('effective')['value']);
    }

    public function test_open_request_keeps_its_snapshot_after_the_rule_changes(): void
    {
        $request = $this->open();
        $rule = ApprovalRule::query()->findOrFail($request->snapshot['rule']['id']);

        app(ApprovalRuleService::class)->update($rule, ['levels' => [['level_no' => 1, 'designation_code' => $request->snapshot['levels'][0]['designation_code'], 'max_value' => 1]]]);

        $this->assertTrue($this->engine->counter($request->id, $this->users[2], 7000)->ok);
    }

    public function test_ask_within_own_power_is_auto_accepted_when_the_setting_is_on(): void
    {
        app(SettingsService::class)->set('approval.auto_accept_own_power', true);

        $result = $this->engine->open(null, 'DISCOUNT.EXTRA', 'extra_disc', ['asked' => 2000, 'scope' => ['model' => 'NEXON']], $this->users[0]);

        $this->assertSame(['ACCEPTED', true], [$result->get('status'), $result->get('auto_accepted')]);
    }

    public function test_rule_precedence_prefers_item_then_specific_scope_then_latest(): void
    {
        $resolved = app(TopicService::class)->resolve('extra_disc');
        $rules = app(RuleService::class);
        $designation = [[$this->staffWithDesignations(1)->first()->designation_code, 1]];
        $main = $this->approvalRule('DISCOUNT', $designation);
        $segmentBranch = $this->approvalRule('extra_disc', $designation, ['segment' => 'SUV', 'branch' => 'JAIPUR']);

        $this->assertNotSame($main->id, $rules->match($resolved, ['model' => 'NEXON'])?->id);
        $this->assertSame($segmentBranch->id, $rules->match($resolved, ['segment' => 'SUV', 'branch' => 'JAIPUR'])?->id);
        $this->assertNotSame($segmentBranch->id, $rules->match($resolved, ['segment' => 'SUV', 'branch' => 'JAIPUR', 'model' => 'NEXON'])?->id);
        $this->assertSame($main->id, $rules->match(app(TopicService::class)->resolve('DISCOUNT'), [])?->id);
    }

    public function test_rule_with_an_unknown_designation_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        app(ApprovalRuleService::class)->create(['topic_id' => ApprovalTopic::query()->where('item_key', 'extra_disc')->value('id'),
            'is_active' => true, 'levels' => [['level_no' => 1, 'designation_code' => 'NOT_A_DESIGNATION', 'max_value' => 1]]]);
    }
}
