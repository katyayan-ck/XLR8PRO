<?php

namespace Tests\Unit\Services\Sales;

use App\Models\CRM\Quotation;
use App\Models\Module\Booking\Booking;
use App\Models\User;
use App\Services\Sales\Booking\BookingOtfService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
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

    public function test_apply_always_crashes_because_final_data_is_not_a_real_column(): void
    {
        // BUG-104 (known-bugs-report.md, CRITICAL): xlr8_booking_master has
        // no final_data column at all (confirmed via SHOW COLUMNS, and
        // reproduced against a real pre-existing booking row, not just a
        // test fixture). $booking->final_data is set unconditionally near
        // the top of apply() (mirroring the original otfSave()), so EVERY
        // call - regardless of what else is submitted - throws a real
        // QueryException on save(). This test locks in that (documented,
        // pre-existing, not introduced by this extraction) behavior rather
        // than asserting a success path that cannot occur.
        $booking = $this->makeBooking();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches("/Unknown column 'final_data'/");

        $this->service->apply($booking, ['pan_no' => 'ABCDE1234F'], null);
    }

    public function test_generate_votf_number_throws_when_branch_code_missing(): void
    {
        // Related to BUG-104: xlr8_booking_master also has no branch_code
        // column (mass-assigning it in makeBooking() silently no-ops, and
        // direct-property-assignment + save() would independently crash
        // the same way final_data does) - so in this schema, every booking
        // reads back branch_code = null and generateVotfNumber() always
        // takes this path. Covered here rather than a happy-path test,
        // since no booking can carry a real branch_code in this database.
        $booking = $this->makeBooking(['branch_code' => 'TESTBR']);

        $this->expectException(\InvalidArgumentException::class);

        $this->service->generateVotfNumber($booking);
    }
}
