<?php

namespace Tests\Feature\Api;

use App\Models\IAM\DeviceSession;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * BUG-217: `sort_by` / `sort_order` on the v1 notification and alert lists went straight into orderBy(); an unknown
 * column or direction was a 500. Now only the list's own columns and asc / desc are used; anything else sorts newest first.
 */
class NotificationListSortTest extends TestCase
{
    use DatabaseTransactions;

    /** @return array<string, string> */
    private function headers(): array
    {
        $user = User::create(['username' => 'noty_'.uniqid(), 'password' => bcrypt('password'), 'user_type' => 'Emp', 'is_active' => 1]);
        $session = DeviceSession::query()->create(['user_id' => $user->id, 'device_id' => 'zq-'.uniqid(), 'device_name' => 'test', 'platform' => 'android', 'last_active_at' => now()]);

        return ['Authorization' => 'Bearer '.$user->createToken('test', ['device_id:'.$session->device_id])->plainTextToken];
    }

    public function test_unknown_sort_values_fall_back_instead_of_failing(): void
    {
        $headers = $this->headers();

        foreach (['/api/v1/notifications', '/api/v1/notifications/unread', '/api/v1/alerts'] as $url) {
            $this->getJson($url.'?sort_by=no_such_column&sort_order=sideways', $headers)
                ->assertOk()->assertJsonPath('success', true);
        }
    }

    public function test_a_listed_column_and_direction_are_accepted(): void
    {
        $this->getJson('/api/v1/notifications?sort_by=priority&sort_order=asc', $this->headers())
            ->assertOk()->assertJsonStructure(['data' => ['notifications', 'pagination' => ['current_page', 'per_page', 'total', 'last_page']]]);
    }
}
