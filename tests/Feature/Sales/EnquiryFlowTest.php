<?php

namespace Tests\Feature\Sales;

use App\Models\CRM\Enquiry;
use App\Models\User;
use App\Models\Vehicle\Variant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Sales → Enquiry write flows over HTTP (to-do W3): create with validation, the virtual-call fast path, the DMS number
 * prefix rule, the duplicate check, the edit-only CRE requirements and the permission gates. List screens are covered
 * by the smoke sweep (AdminScreenSmokeTest).
 */
class EnquiryFlowTest extends TestCase
{
    use DatabaseTransactions;

    private function superadmin(): User
    {
        return User::whereHas('roles', fn ($q) => $q->where('name', 'superadmin'))->firstOrFail();
    }

    /** @return array<string, string> */
    private function payload(array $overrides = []): array
    {
        $variant = Variant::withoutGlobalScopes()->from('xlr8_vehicle_variant as v')->toBase()->join('xlr8_vehicle_model as m', 'm.code', '=', 'v.model_code')
            ->where('v.is_active', 1)->whereNull('v.deleted_at')->whereNotNull('m.segment_code')
            ->first(['v.code', 'v.model_code', 'm.segment_code']) ?? $this->markTestSkipped('no active variant');

        return array_merge([
            'enquiry_type' => 'Retail', 'source_code' => 'WALKIN', 'name' => 'Flow Test Customer',
            'mobile' => '9'.random_int(100000000, 999999999), 'gender' => 'Male', 'zipcode' => '334001', 'tehsil' => 'Bikaner',
            'district' => 'Bikaner', 'city' => 'Bikaner', 'territory' => 'Urban', 'segment_code' => $variant->segment_code,
            'model_code' => $variant->model_code, 'variant_code' => $variant->code, 'purchase_type_crm' => 'First Time',
        ], $overrides);
    }

    public function test_a_full_enquiry_is_created_with_the_vehicle_names_and_the_dms_prefix(): void
    {
        $this->actingAs($this->superadmin(), 'backpack');
        $data = $this->payload(['dms_enq_no' => 'n-12345']);

        $this->post('/admin/sales/enquiry', $data)->assertRedirect(backpack_url('sales/enquiry/xceler8'))->assertSessionHasNoErrors();

        $row = Enquiry::withoutGlobalScopes()->toBase()->where('mobile', $data['mobile'])->latest('id')->first();
        $this->assertNotNull($row);
        $this->assertSame('EN-12345', $row->dms_enq_no);
        $this->assertSame('Xceler8', $row->origin);
        $this->assertNotEmpty($row->segment, 'the segment name is resolved from its code');
    }

    public function test_missing_required_fields_are_reported_and_nothing_is_saved(): void
    {
        $this->actingAs($this->superadmin(), 'backpack');
        $data = $this->payload(['name' => '', 'variant_code' => '', 'zipcode' => '']);

        $this->from('/admin/sales/enquiry/create')->post('/admin/sales/enquiry', $data)
            ->assertRedirect('/admin/sales/enquiry/create')->assertSessionHasErrors(['name', 'variant_code', 'zipcode']);
        $this->assertFalse(Enquiry::withoutGlobalScopes()->toBase()->where('mobile', $data['mobile'])->exists());
    }

    public function test_a_reference_enquiry_needs_the_referee_and_goes_to_the_reference_list(): void
    {
        $this->actingAs($this->superadmin(), 'backpack');

        $this->from('/admin/sales/enquiry/create')->post('/admin/sales/enquiry', $this->payload(['source_code' => 'REFERENCE']))
            ->assertSessionHasErrors(['referred_by', 'referee_phone', 'referee_name']);

        $this->post('/admin/sales/enquiry', $this->payload(['source_code' => 'REFERENCE', 'referred_by' => 'Staff',
            'referee_phone' => '9876543210', 'referee_name' => 'Referee']))
            ->assertRedirect(backpack_url('sales/enquiry/reference'));
    }

    public function test_the_virtual_call_fast_path_needs_only_the_call_nature_and_mobile(): void
    {
        $this->actingAs($this->superadmin(), 'backpack');
        $mobile = '8'.random_int(100000000, 999999999);

        $this->post('/admin/sales/enquiry', ['call_nature' => 'Service', 'mobile' => $mobile])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertTrue(Enquiry::withoutGlobalScopes()->toBase()->where('mobile', $mobile)->exists());
    }

    public function test_the_duplicate_check_finds_an_enquiry_by_mobile_and_segment(): void
    {
        $this->actingAs($this->superadmin(), 'backpack');
        $data = $this->payload();
        $this->post('/admin/sales/enquiry', $data);

        $this->getJson('/admin/sales/enquiry/check-duplicate?'.http_build_query(['mobile' => $data['mobile'], 'segment_code' => $data['segment_code']]))
            ->assertOk()->assertJson(['exists' => true]);
        $this->getJson('/admin/sales/enquiry/check-duplicate?'.http_build_query(['mobile' => '9000000000', 'segment_code' => 'NOPE']))
            ->assertOk()->assertJson(['exists' => false]);
    }

    public function test_an_edit_requires_the_sc_and_cre_stage(): void
    {
        $this->actingAs($this->superadmin(), 'backpack');
        $data = $this->payload();
        $this->post('/admin/sales/enquiry', $data);
        $id = Enquiry::withoutGlobalScopes()->toBase()->where('mobile', $data['mobile'])->value('id');

        $this->from("/admin/sales/enquiry/{$id}/edit")->put("/admin/sales/enquiry/{$id}", $data)
            ->assertSessionHasErrors(['x8_sc_code', 'cre_customer_stage', 'cre_next_fup_date']);
    }

    public function test_creating_needs_the_create_permission(): void
    {
        $user = User::where('is_active', 1)->get()->first(fn (User $u) => ! $u->isSuperAdmin() && ! $u->can('SLS_ENQR_CREATE'))
            ?? $this->markTestSkipped('every active user can create enquiries');
        $this->actingAs($user, 'backpack');

        $this->post('/admin/sales/enquiry', $this->payload())->assertForbidden();
    }
}
