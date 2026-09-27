<?php

namespace Tests\Feature\Platform;

use App\Models\Module\Booking\Booking;
use App\Models\Module\Booking\XlRto;
use App\Models\User;
use App\Services\Platform\Docs\DocsService;
use App\Services\Platform\Settings\SettingsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * DEC-069: booking proofs are Docs documents — one-file slots on the record, served through the access-checked
 * download route, visible to whoever may view bookings.
 */
class BookingProofDocsTest extends TestCase
{
    use DatabaseTransactions;

    private function rto(): XlRto
    {
        $booking = Booking::create(['pending_remark' => '', 'booking_amount' => 10000]);

        return XlRto::create(['bid' => $booking->id]);
    }

    private function superAdmin(): User
    {
        return User::role('superadmin')->firstOrFail();
    }

    public function test_replacing_a_proof_supersedes_the_previous_file(): void
    {
        $this->actingAs($this->superAdmin(), 'backpack');
        $rto = $this->rto();

        $rto->replaceDocument('trc_copy', UploadedFile::fake()->create('trc-v1.pdf', 20, 'application/pdf'));
        $rto->replaceDocument('trc_copy', UploadedFile::fake()->create('trc-v2.pdf', 20, 'application/pdf'));

        $this->assertCount(1, $rto->documentsList('trc_copy'));
        $this->assertSame('trc-v2.pdf', $rto->documentFor('trc_copy')['name']);
        $this->assertStringContainsString('inline=1', $rto->documentUrl('trc_copy'));
    }

    public function test_a_disallowed_file_type_is_a_validation_error_on_the_named_field(): void
    {
        $this->actingAs($this->superAdmin(), 'backpack');
        app(SettingsService::class)->set('docs.allowed_mimes', 'pdf');

        try {
            $this->rto()->replaceDocument('trc_copy', UploadedFile::fake()->create('trc.exe', 5), [], 'trc_copy');
            $this->fail('Expected a validation error.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('trc_copy', $e->errors());
        }
    }

    public function test_booking_viewers_can_preview_a_proof_inline(): void
    {
        $this->actingAs($this->superAdmin(), 'backpack');
        $rto = $this->rto();
        $rto->replaceDocument('trc_copy', UploadedFile::fake()->create('trc.pdf', 20, 'application/pdf'));

        $response = $this->get($rto->documentUrl('trc_copy'));

        $response->assertOk();
        $this->assertStringStartsWith('inline;', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_users_without_booking_access_cannot_open_a_proof(): void
    {
        $viewer = User::query()->where('is_active', 1)->get()
            ->first(fn (User $u) => ! $u->isSuperAdmin() && ! $u->can('SLS_BKNG_VIEW') && ! $u->can('UTL_DOCS_MANAGE'));
        if (! $viewer) {
            $this->markTestSkipped('Needs a user without booking access.');
        }
        $this->actingAs($this->superAdmin(), 'backpack');
        $rto = $this->rto();
        $rto->replaceDocument('trc_copy', UploadedFile::fake()->create('trc.pdf', 20, 'application/pdf'));
        $docId = $rto->documentFor('trc_copy')['id'];

        $this->assertFalse(app(DocsService::class)->canView($docId, $viewer->id));

        $this->app['auth']->guard('backpack')->logout();
        $this->actingAs($viewer, 'backpack');
        $this->get(route('utils.docs.download', ['id' => $docId]))->assertForbidden();
    }
}
