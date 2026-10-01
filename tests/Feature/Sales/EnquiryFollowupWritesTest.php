<?php

namespace Tests\Feature\Sales;

use App\Models\CRM\CreFollowup;
use App\Models\CRM\Enquiry;
use App\Models\CRM\FinanceExchangeFollowup;
use App\Models\User;
use App\Models\Vehicle\Variant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * The enquiry screen's follow-up writes (W15 BT-005, booking-team code): a CRE follow-up is saved with the next count, the
 * planned date taken from the previous follow-up's next date and the deviation stage, after the OPEN_FOLLOW_UP placeholder
 * is removed for good; finance and exchange remarks are numbered per type. Written to pass before and after the move
 * from `DB::table()` to the models.
 */
class EnquiryFollowupWritesTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::whereHas('roles', fn ($q) => $q->where('name', 'superadmin'))->firstOrFail(), 'backpack');
    }

    /** @return array<string, string> */
    private function payload(array $overrides = []): array
    {
        $variant = Variant::query()->where('is_active', 1)->whereHas('vehicleModel', fn ($q) => $q->whereNotNull('segment_code'))->with('vehicleModel')->first()
            ?? $this->markTestSkipped('no active variant');

        return array_merge([
            'enquiry_type' => 'Retail', 'source_code' => 'WALKIN', 'name' => 'Follow-up Test Customer',
            'mobile' => '9'.random_int(100000000, 999999999), 'gender' => 'Male', 'zipcode' => '334001', 'tehsil' => 'Bikaner',
            'district' => 'Bikaner', 'city' => 'Bikaner', 'territory' => 'Urban', 'segment_code' => $variant->vehicleModel->segment_code,
            'model_code' => $variant->model_code, 'variant_code' => $variant->code, 'purchase_type_crm' => 'First Time',
        ], $overrides);
    }

    private function createEnquiry(array $overrides = []): Enquiry
    {
        $data = $this->payload($overrides);
        $this->post('/admin/sales/enquiry', $data)->assertSessionHasNoErrors();

        return Enquiry::query()->withoutGlobalScopes()->where('mobile', $data['mobile'])->latest('id')->firstOrFail();
    }

    public function test_a_new_enquiry_with_cre_fields_gets_its_first_cre_follow_up(): void
    {
        $enquiry = $this->createEnquiry(['cre_enq_stage' => 'HOT', 'cre_customer_stage' => 'INTERESTED', 'cre_fup_remarks' => 'first call',
            'cre_next_fup_date' => now('Asia/Kolkata')->addDays(3)->format('Y-m-d H:i')]);

        $row = CreFollowup::query()->where('x8_enq_no', (string) $enquiry->id)->sole();
        $this->assertSame(1, (int) $row->cre_fup_count);
        $this->assertSame('SAME_DAY', $row->cre_fup_deviation_stage);
        $this->assertSame('first call', $row->cre_fup_remarks);
        $this->assertNotNull($row->cre_next_fup_date);
    }

    public function test_an_update_removes_the_open_placeholder_for_good_and_continues_the_count(): void
    {
        $enquiry = $this->createEnquiry();
        $previousNext = now('Asia/Kolkata')->subDays(5)->format('Y-m-d H:i:s');
        CreFollowup::query()->insert([
            ['x8_enq_no' => 'XENQ-'.$enquiry->id, 'cre_fup_count' => 2, 'cre_fup_deviation_stage' => 'SAME_DAY', 'cre_next_fup_date' => $previousNext, 'created_at' => now(), 'updated_at' => now()],
            ['x8_enq_no' => (string) $enquiry->id, 'cre_fup_count' => 0, 'cre_fup_deviation_stage' => 'OPEN_FOLLOW_UP', 'cre_next_fup_date' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $consultant = 'TESTSC01';   // the edit form requires a consultant; any code is accepted
        $this->put("/admin/sales/enquiry/{$enquiry->id}", $this->payload([
            'mobile' => $enquiry->mobile, 'x8_sc_code' => $consultant, 'cre_enq_stage' => 'WARM', 'cre_customer_stage' => 'INTERESTED',
            'cre_fup_remarks' => 'second call', 'cre_next_fup_date' => now('Asia/Kolkata')->addDays(2)->format('Y-m-d H:i'),
        ]))->assertSessionHasNoErrors();

        $this->assertFalse(CreFollowup::withTrashed()->whereIn('x8_enq_no', [(string) $enquiry->id, 'XENQ-'.$enquiry->id])
            ->where('cre_fup_deviation_stage', 'OPEN_FOLLOW_UP')->exists(), 'the placeholder is deleted, not soft-deleted');
        $latest = CreFollowup::query()->whereIn('x8_enq_no', [(string) $enquiry->id, 'XENQ-'.$enquiry->id])->latest('id')->firstOrFail();
        $this->assertSame(3, (int) $latest->cre_fup_count);
        $this->assertSame($previousNext, (string) $latest->cre_planned_fup_date);
        $this->assertSame('3_TO_10_DAYS', $latest->cre_fup_deviation_stage);
    }

    public function test_finance_and_exchange_remarks_are_numbered_per_type(): void
    {
        $enquiry = $this->createEnquiry();
        $enqNo = (string) ($enquiry->enquiry_no ?: 'XENQ-'.$enquiry->id);   // the screens' fallback for Xceler8 enquiries
        FinanceExchangeFollowup::query()->insert(['enq_no' => $enqNo, 'remark_type' => 2, 'fup_count' => 4, 'remarks' => 'old', 'created_at' => now(), 'updated_at' => now()]);

        $this->post("/admin/sales/enquiry/{$enquiry->id}/exchange-update", ['remarks' => 'exchange note']);
        $this->put("/admin/sales/enquiry/finance/{$enquiry->id}/update", ['remarks' => 'finance note']);

        $exchange = FinanceExchangeFollowup::query()->where('enq_no', $enqNo)->where('remark_type', 2)->latest('id')->firstOrFail();
        $finance = FinanceExchangeFollowup::query()->where('enq_no', $enqNo)->where('remark_type', 1)->sole();
        $this->assertSame([5, 'exchange note'], [(int) $exchange->fup_count, $exchange->remarks]);
        $this->assertSame([1, 'finance note'], [(int) $finance->fup_count, $finance->remarks]);
    }
}
