<?php

namespace Tests\Feature\Sales;

use App\Models\CRM\Enquiry;
use App\Models\CRM\Quotation;
use App\Models\CRM\QuoteAction;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Sales → Quotation edit flow over HTTP (to-do W3): a real change bumps the revision and logs a REVISED action, an
 * unchanged save does not, a booked quotation stays booked (BUG-096), the history page lists it, and editing needs
 * SLS_QUOT_EDIT. Create / pricing / hold rules are covered by Pricing\QuotationPricingTest.
 */
class QuotationFlowTest extends TestCase
{
    use DatabaseTransactions;

    private Enquiry $enquiry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enquiry = Enquiry::query()->withoutGlobalScopes()->whereNotNull('enquiry_no')->latest('id')->first()
            ?? $this->markTestSkipped('No enquiry in the test copy.');
    }

    private function superadmin(): User
    {
        return User::whereHas('roles', fn ($q) => $q->where('name', 'superadmin'))->firstOrFail();
    }

    private function quotation(string $status = 'raised'): Quotation
    {
        return Quotation::query()->forceCreate([
            'enquiry_no' => $this->enquiry->id, 'revision' => 0, 'status' => $status, 'onroad_price' => 1000000, 'invoice_price' => 900000,
            'standard_data' => ['enquiry_id' => $this->enquiry->id, 'enquiry_no' => $this->enquiry->enquiry_no, 'remarks' => 'first'],
        ]);
    }

    public function test_a_change_bumps_the_revision_and_logs_it_but_an_unchanged_save_does_not(): void
    {
        $this->actingAs($this->superadmin(), 'backpack');
        $quotation = $this->quotation();

        $this->put("/admin/sales/quotation/{$quotation->id}", ['enquiry_id' => $this->enquiry->id, 'remarks' => 'second'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $quotation->refresh();
        $this->assertSame(1, (int) $quotation->revision);
        $this->assertSame('second', $quotation->standard_data['remarks']);
        $this->assertSame(1, QuoteAction::query()->where('quotation_no', $quotation->id)->where('action', 'REVISED')->count());

        $this->put("/admin/sales/quotation/{$quotation->id}", ['enquiry_id' => $this->enquiry->id, 'remarks' => 'second']);
        $this->assertSame(1, (int) $quotation->fresh()->revision, 'nothing changed → same revision');
        $this->assertSame(1, QuoteAction::query()->where('quotation_no', $quotation->id)->count());
    }

    public function test_a_booked_quotation_stays_booked_after_an_edit(): void
    {
        $this->actingAs($this->superadmin(), 'backpack');
        $quotation = $this->quotation('booked');

        $this->put("/admin/sales/quotation/{$quotation->id}", ['enquiry_id' => $this->enquiry->id, 'remarks' => 'after booking']);

        $this->assertSame('booked', $quotation->fresh()->status);
    }

    public function test_the_history_page_opens_and_the_enquiry_id_is_required(): void
    {
        $this->actingAs($this->superadmin(), 'backpack');
        $quotation = $this->quotation();

        $this->get("/admin/sales/quotation/{$quotation->id}/history")->assertOk();
        $this->from("/admin/sales/quotation/{$quotation->id}/edit")->put("/admin/sales/quotation/{$quotation->id}", ['remarks' => 'x'])
            ->assertSessionHasErrors('enquiry_id');
    }

    public function test_editing_needs_the_edit_permission(): void
    {
        $quotation = $this->quotation();
        $user = User::where('is_active', 1)->get()->first(fn (User $u) => ! $u->isSuperAdmin() && ! $u->can('SLS_QUOT_EDIT'))
            ?? $this->markTestSkipped('every active user can edit quotations');
        $this->actingAs($user, 'backpack');

        $this->put("/admin/sales/quotation/{$quotation->id}", ['enquiry_id' => $this->enquiry->id])->assertForbidden();
        $this->assertSame(0, (int) $quotation->fresh()->revision);
    }
}
