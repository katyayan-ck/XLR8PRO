<?php

namespace Tests\Feature\Sales;

use App\Models\Module\Booking\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Sales → Booking over HTTP (to-do W3): every write route refuses a user without Sales permissions, the create form's
 * validation, and the KYC / DMS steps wired to their services (the services themselves have unit tests in
 * tests/Unit/Services/Sales).
 */
class BookingFlowTest extends TestCase
{
    use DatabaseTransactions;

    private function superadmin(): User
    {
        return User::whereHas('roles', fn ($q) => $q->where('name', 'superadmin'))->firstOrFail();
    }

    private function booking(): Booking
    {
        return Booking::query()->withoutGlobalScopes()->latest('id')->first() ?? $this->markTestSkipped('No booking in the test copy.');
    }

    public function test_every_booking_write_route_refuses_a_user_without_sales_permissions(): void
    {
        $user = User::where('is_active', 1)->get()->first(fn (User $u) => ! $u->isSuperAdmin()
            && $u->getAllPermissions()->filter(fn ($p) => str_starts_with((string) $p->name, 'SLS_BKNG'))->isEmpty())
            ?? $this->markTestSkipped('no active user without booking permissions');
        $id = $this->booking()->id;
        $this->actingAs($user, 'backpack');

        $open = [];
        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            $method = collect($route->methods())->first(fn ($m) => in_array($m, ['POST', 'PUT', 'DELETE'], true));
            if ($method === null || ! str_starts_with($uri, 'admin/sales/booking') || str_ends_with($uri, '/search')) {
                continue;
            }
            $url = '/'.preg_replace('/\{[^}]+\}/', (string) $id, $uri);
            $status = $this->call($method, $url)->getStatusCode();
            if ($status !== 403) {
                $open[] = "{$method} {$url} → {$status}";
            }
        }

        $this->assertSame([], $open, 'write routes reachable without permission');
    }

    public function test_the_create_form_reports_the_first_missing_field_and_saves_nothing(): void
    {
        $this->actingAs($this->superadmin(), 'backpack');
        $count = Booking::query()->withoutGlobalScopes()->count();

        // store() flashes the first message as `error` (not the error bag)
        $this->from('/admin/sales/booking/create')->post('/admin/sales/booking', [])
            ->assertRedirect('/admin/sales/booking/create')->assertSessionHas('error');
        $this->assertSame($count, Booking::query()->withoutGlobalScopes()->count());
    }

    public function test_kyc_checks_the_identifiers_and_saves_through_the_service(): void
    {
        $this->actingAs($this->superadmin(), 'backpack');
        $booking = $this->booking();

        $this->from('/admin/sales/booking/pending-kyc')->put("/admin/sales/booking/{$booking->id}/kyc-update", ['pan_no' => 'BAD', 'adhar_no' => '123'])
            ->assertSessionHasErrors(['pan_no', 'adhar_no']);

        $this->put("/admin/sales/booking/{$booking->id}/kyc-update", ['pan_no' => 'ABCPE1234F', 'adhar_no' => '234123412346', 'gst_not_required' => 1])
            ->assertRedirect(route('sales.booking.pending-kyc'))->assertSessionHasNoErrors();
        $this->assertSame('ABCPE1234F', strtoupper((string) $booking->fresh()->pan_no));
    }

    public function test_dms_numbers_must_follow_the_oem_formats(): void
    {
        $this->actingAs($this->superadmin(), 'backpack');
        $booking = $this->booking();

        $this->from('/admin/sales/booking/pending-dms')->put("/admin/sales/booking/{$booking->id}/dms-update",
            ['dms_no' => 'B-123', 'dms_otf' => 'OTF1', 'otf_date' => '2026-09-30', 'hidden_otf_date' => '2026-09-30'])
            ->assertSessionHasErrors(['dms_no', 'dms_otf', 'otf_date']);
    }
}
