<?php

namespace Tests\Unit\Services\Sales;

use App\Models\CRM\Quotation;
use App\Models\Module\Booking\Booking;
use App\Models\User;
use App\Services\Sales\Booking\BookingOtfService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BookingOtfServiceTest extends TestCase
{
    use DatabaseTransactions;

    private BookingOtfService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BookingOtfService::class);

        $user = User::create([
            'username' => 'otf_test_'.uniqid(),
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

    private function makeQuotation(array $standardData = []): Quotation
    {
        return Quotation::create([
            'standard_data' => array_merge([
                'ex_showroom_price' => 500000,
            ], $standardData),
        ]);
    }

    public function test_resolve_otf_form_data_returns_null_when_quotation_not_found(): void
    {
        $booking = $this->makeBooking(['quotation_id' => 999999]);

        $result = $this->service->resolveOtfFormData($booking);

        $this->assertNull($result);
    }

    public function test_resolve_otf_form_data_merges_quotation_and_final_data(): void
    {
        // final_data can't be persisted through makeBooking() (BUG-104:
        // it isn't a real column - see the dedicated apply() test below),
        // so it's set as a direct, unsaved in-memory attribute here purely
        // to exercise resolveOtfFormData()'s read-side merge logic.
        $quotation = $this->makeQuotation(['loan_amount' => 100000]);
        $booking = $this->makeBooking(['quotation_id' => $quotation->id]);
        $booking->final_data = json_encode(['ex_showroom_price' => 550000]);

        $result = $this->service->resolveOtfFormData($booking);

        $this->assertNotNull($result);
        // final_data (550000) overrides quotation's ex_showroom_price (500000)
        $this->assertEquals(550000, $result['otfData']['ex_showroom_price']);
        $this->assertArrayHasKey('net_settlement_amount', $result['otfData']);
    }

    public function test_apply_saves_final_data(): void
    {
        // Regression for BUG-104: final_data was not a real column, so every OTF
        // save crashed. Migration 2026_09_26_120000 adds it.
        $booking = $this->makeBooking();

        $saved = $this->service->apply($booking, ['pan_no' => 'ABCDE1234F'], null);

        $this->assertIsArray(json_decode((string) $saved->fresh()->final_data, true));
    }

    public function test_generate_votf_number_uses_the_selected_branch(): void
    {
        // Since 27-09 the OTF form makes the user pick the branch; the controller passes it in.
        $votf = $this->service->generateVotfNumber($this->makeBooking(), ' jpr ');

        $this->assertMatchesRegularExpression('#^\d{2}/JPR\d{4}/\d{4}$#', $votf);
    }

    public function test_apply_rejects_a_votf_number_another_booking_holds(): void
    {
        // BUG-097: the number is previewed and saved in separate requests; the save must not create a duplicate.
        $votf = '27/TST'.random_int(1000, 9999).'/'.random_int(1000, 9999);
        $this->service->apply($this->makeBooking(), ['votf_no' => $votf], null);

        try {
            $this->service->apply($this->makeBooking(), ['votf_no' => $votf], null);
            $this->fail('Expected a validation error for the duplicate VOTF number.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('votf_no', $e->errors());
        }
    }

    public function test_apply_lets_a_booking_save_its_own_votf_number_again(): void
    {
        $votf = '27/TST'.random_int(1000, 9999).'/'.random_int(1000, 9999);
        $booking = $this->makeBooking();
        $this->service->apply($booking, ['votf_no' => $votf], null);

        $saved = $this->service->apply($booking->fresh(), ['votf_no' => $votf, 'pan_no' => 'ABCDE1234F'], null);

        $this->assertSame($votf, json_decode((string) $saved->fresh()->final_data, true)['votf_no']);
    }

    public function test_generate_votf_number_throws_when_branch_code_missing(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->generateVotfNumber($this->makeBooking(), '   ');
    }
}
