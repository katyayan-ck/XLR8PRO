<?php

namespace Tests\Unit\Services\Sales;

use App\Models\Module\Booking\Booking;
use App\Models\User;
use App\Services\Sales\Booking\BookingRtoService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class BookingRtoServiceTest extends TestCase
{
    use DatabaseTransactions;

    private BookingRtoService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BookingRtoService::class);

        $user = User::create([
            'username' => 'rto_test_'.uniqid(),
            'password' => bcrypt('password'),
            'user_type' => 'Emp',
            'is_active' => 1,
        ]);
        $this->app['auth']->guard('backpack')->setUser($user);
    }

    private function makeBooking(array $overrides = []): Booking
    {
        return Booking::create(array_merge([
            'pending_remark' => '',
            'booking_amount' => 10000,
        ], $overrides));
    }

    private function basePayload(): array
    {
        return [
            'trade_used' => '1',
            'sale_type' => '1',
            'permit' => '1',
            'body_type' => '1',
            'registration_type' => '0',
            'reg_no_type' => '1',
            'trc_number' => null,
            'bank_ref_no' => null,
            'application_no' => null,
            'tax_payment_ref_no' => null,
            'vehicle_reg_no' => null,
        ];
    }

    public function test_apply_saves_a_literal_zero_vehicle_reg_no_correctly(): void
    {
        // Regression guard: the original code used Laravel's Request::filled(),
        // which treats "0" as present. A naive PHP empty() check would
        // incorrectly treat "0" as not-filled and mark the field missing.
        $booking = $this->makeBooking();

        $rto = $this->service->apply($booking->id, array_merge(
            $this->basePayload(),
            ['vehicle_reg_no' => '0']
        ), null, null);

        $this->assertSame('0', $rto->vh_rgn_no);
    }

    public function test_apply_sets_status_1_when_no_rule_matches(): void
    {
        $booking = $this->makeBooking();

        $rto = $this->service->apply($booking->id, array_merge(
            $this->basePayload(),
            ['sale_type' => '2', 'permit' => '999']
        ), null, null);

        $this->assertSame(1, $rto->status);
    }

    public function test_apply_sets_status_1_when_a_matching_rules_required_file_is_missing(): void
    {
        $booking = $this->makeBooking();

        // sale_type=1/permit=1/body_type=1/reg_no_type=1 matches a real
        // XlRtoRules row requiring app_no/tax_pay/veh_reg/tax_copy - only
        // filling the text fields, no tax_receipt_copy file.
        $rto = $this->service->apply($booking->id, array_merge($this->basePayload(), [
            'application_no' => 'APP1234567',
            'tax_payment_ref_no' => 'TAX12345678',
            'vehicle_reg_no' => 'MH12AB1234',
        ]), null, null);

        $this->assertSame(1, $rto->status);
    }

    public function test_apply_sets_status_2_when_every_matching_rules_requirement_is_satisfied(): void
    {
        $booking = $this->makeBooking();
        $file = UploadedFile::fake()->create('tax.pdf', 50, 'application/pdf');

        $rto = $this->service->apply($booking->id, array_merge($this->basePayload(), [
            'application_no' => 'APP1234567',
            'tax_payment_ref_no' => 'TAX12345678',
            'vehicle_reg_no' => 'MH12AB1234',
        ]), null, $file);

        $this->assertSame(2, $rto->status);
        $this->assertTrue($rto->getMedia('tax_receipt_copy')->isNotEmpty());
    }

    public function test_apply_lets_an_existing_file_satisfy_the_requirement_on_a_later_call(): void
    {
        $booking = $this->makeBooking();
        $file = UploadedFile::fake()->create('tax.pdf', 50, 'application/pdf');

        // First call uploads the file and reaches status 2.
        $this->service->apply($booking->id, array_merge($this->basePayload(), [
            'application_no' => 'APP1234567',
            'tax_payment_ref_no' => 'TAX12345678',
            'vehicle_reg_no' => 'MH12AB1234',
        ]), null, $file);

        // Second call submits no new file - the existing one should still satisfy the rule.
        $rto = $this->service->apply($booking->id, array_merge($this->basePayload(), [
            'application_no' => 'APP1234567',
            'tax_payment_ref_no' => 'TAX12345678',
            'vehicle_reg_no' => 'MH12AB1234',
        ]), null, null);

        $this->assertSame(2, $rto->status);
    }

    public function test_resolve_edit_data_returns_expected_shape(): void
    {
        $booking = $this->makeBooking();

        $result = $this->service->resolveEditData($booking);

        $this->assertEqualsCanonicalizing(['rto', 'data', 'dsaname'], array_keys($result));
        $this->assertNull($result['rto']);
    }
}
