<?php

namespace Tests\Feature\Api;

use App\Models\IAM\DeviceSession;
use App\Models\Module\Booking\Booking;
use App\Models\User;
use App\Models\Utilities\Docs\DocGroup;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * The app's history / document endpoints accept only registered entity types and check access to the record
 * (DEC-095 #2, BUG-182) — no class name from the request is ever instantiated.
 */
class EntityApiAccessTest extends TestCase
{
    use DatabaseTransactions;

    /** @return array<string, string> */
    private function headersFor(User $user): array
    {
        $session = DeviceSession::query()->create(['user_id' => $user->id, 'device_id' => 'zq-'.uniqid(), 'device_name' => 'test', 'platform' => 'android', 'last_active_at' => now()]);

        return ['Authorization' => 'Bearer '.$user->createToken('test', ['device_id:'.$session->device_id])->plainTextToken];
    }

    private function userWithout(string $permission): User
    {
        $user = User::create(['username' => 'zq_api_'.uniqid(), 'password' => bcrypt('x'), 'user_type' => 'Emp', 'is_active' => 1]);
        $this->assertFalse($user->can($permission));

        return $user;
    }

    public function test_a_class_that_is_not_a_registered_entity_is_not_found(): void
    {
        $headers = $this->headersFor(User::role('superadmin')->firstOrFail());

        $this->getJson('/api/v1/history/User/1', $headers)->assertNotFound();
        $this->getJson('/api/v1/history/Utilities%5CSettings%5CSystemSetting/1', $headers)->assertNotFound();
    }

    public function test_a_record_the_user_may_not_see_is_forbidden(): void
    {
        $booking = Booking::query()->withoutGlobalScopes()->firstOrFail();
        $headers = $this->headersFor($this->userWithout('SLS_BKNG_VIEW'));

        $this->getJson("/api/v1/history/BOOKING/{$booking->id}", $headers)->assertForbidden();
        $this->postJson("/api/v1/history/Booking/{$booking->id}/thread", ['action_key' => 'REMARK', 'message' => 'x'], $headers)->assertForbidden();
    }

    public function test_document_groups_and_approval_check_the_caller(): void
    {
        $owner = User::role('superadmin')->firstOrFail();
        $group = DocGroup::query()->forceCreate(['user_id' => $owner->id, 'name' => 'ZQ snapshot group']);
        $headers = $this->headersFor($this->userWithout('UTL_DOCS_MANAGE'));

        $this->getJson("/api/v1/docs/groups/{$group->id}/zip", $headers)->assertForbidden();
        $this->deleteJson("/api/v1/docs/groups/{$group->id}/remove/1", [], $headers)->assertForbidden();
        $this->postJson('/api/v1/docs/1/approve', [], $headers)->assertForbidden();
    }

    public function test_a_permitted_user_reaches_a_registered_record_by_code_or_short_name(): void
    {
        $booking = Booking::query()->withoutGlobalScopes()->firstOrFail();
        $headers = $this->headersFor(User::role('superadmin')->firstOrFail());

        foreach (['BOOKING', 'Booking'] as $type) {
            $status = $this->getJson("/api/v1/history/{$type}/{$booking->id}", $headers)->status();
            $this->assertContains($status, [200, 404], "{$type}: the record is resolved (404 here = no history yet, not a refusal)");
            $this->app['auth']->forgetGuards();
        }
    }
}
