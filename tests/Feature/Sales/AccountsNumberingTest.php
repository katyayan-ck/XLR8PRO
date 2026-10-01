<?php

namespace Tests\Feature\Sales;

use App\Http\Controllers\Admin\Accounts\JournalVoucher\JournalVoucherCrudController;
use App\Http\Controllers\Admin\Accounts\Receipt\ReceiptCrudController;
use App\Models\Module\Booking\Bookingamount;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Journal-voucher and receipt numbers (`{prefix}{location}{FY}{nn}`) continue from the newest number of the same series
 * across every row — soft-deleted rows and rows outside the signed-in user's data scope included — so two users can
 * never issue the same number (W15 BT-006; written to pass before and after the move from `DB::table()` to the model).
 */
class AccountsNumberingTest extends TestCase
{
    use DatabaseTransactions;

    private function fy(): string
    {
        $now = Carbon::now('Asia/Kolkata');

        return 'F'.substr((string) (($now->month >= 4 ? $now->year : $now->year - 1) + 1), -2);
    }

    /** A row of the series that a scoped user cannot see: soft-deleted and on a booking that does not exist. */
    private function hiddenRow(string $number): void
    {
        Bookingamount::query()->insert(['type_number' => $number, 'date' => now()->toDateString(), 'bid' => 999999999, 'amount' => '1', 'deleted_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    }

    private function generate(object $controller, string $method, array $args): string
    {
        $call = new \ReflectionMethod($controller, $method);

        return $call->invoke($controller, ...$args);
    }

    public function test_the_voucher_number_continues_the_series_even_from_rows_the_user_cannot_see(): void
    {
        $this->actingAs(User::findOrFail(40), 'backpack');
        $this->hiddenRow('JVZQ'.$this->fy().'07');

        $this->assertSame('JVZQ'.$this->fy().'08', $this->generate(app(JournalVoucherCrudController::class), 'generateVoucherNumber', ['ZQ']));
        $this->assertSame('JVZR'.$this->fy().'01', $this->generate(app(JournalVoucherCrudController::class), 'generateVoucherNumber', ['ZR']), 'a new series starts at 01');
    }

    public function test_the_receipt_number_continues_the_series_even_from_rows_the_user_cannot_see(): void
    {
        $this->actingAs(User::findOrFail(40), 'backpack');
        $this->hiddenRow('GENZQ'.$this->fy().'11');

        $this->assertSame('GENZQ'.$this->fy().'12', $this->generate(app(ReceiptCrudController::class), 'generateReceiptNumber', [0, 'ZQ']));
    }
}
