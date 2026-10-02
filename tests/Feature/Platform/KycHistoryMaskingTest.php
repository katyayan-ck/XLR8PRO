<?php

namespace Tests\Feature\Platform;

use App\Models\Utilities\Privacy\KycMaskBackup;
use App\Services\Platform\Privacy\KycHistoryMaskingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use OwenIt\Auditing\Models\Audit;
use Tests\TestCase;

/**
 * D26 (DEC-095 #19): Aadhaar / PAN copies in history rows are masked by key (also inside nested JSON), other numbers
 * are left alone, the originals are kept encrypted, and --restore puts them back exactly.
 */
class KycHistoryMaskingTest extends TestCase
{
    use DatabaseTransactions;

    private function auditRow(array $newValues): Audit
    {
        $audit = new Audit;
        $audit->forceFill([
            'event' => 'updated', 'auditable_type' => 'App\\Models\\Module\\Booking\\Booking', 'auditable_id' => 990001,
            'old_values' => [], 'new_values' => $newValues,   // the model casts them to JSON
        ])->save();

        return $audit;
    }

    private function newValues(Audit $audit): array
    {
        return json_decode((string) Audit::query()->toBase()->where('id', $audit->id)->value('new_values'), true);
    }

    public function test_history_copies_are_masked_reversibly_and_other_numbers_stay(): void
    {
        $service = app(KycHistoryMaskingService::class);
        $flat = $this->auditRow(['adhar_no' => '1234-5678-9012', 'pan_no' => 'ABCDE1234F', 'trc_number' => '123456789012', 'gstn' => '08ABCDE1234F1Z5']);
        $nested = $this->auditRow(['extra_data' => json_encode(['adhar_no' => '432143214321', 'application_no' => '111122223333'])]);
        $originalFlat = Audit::query()->toBase()->where('id', $flat->id)->value('new_values');

        $service->run(apply: false);
        $this->assertSame($originalFlat, Audit::query()->toBase()->where('id', $flat->id)->value('new_values'), 'a report changes nothing');

        $service->run(apply: true);
        $this->assertSame(['adhar_no' => 'XXXXXXXX9012', 'pan_no' => 'XXXXXX234F', 'trc_number' => '123456789012', 'gstn' => '08ABCDE1234F1Z5'], $this->newValues($flat));
        $this->assertSame(['adhar_no' => 'XXXXXXXX4321', 'application_no' => '111122223333'], json_decode($this->newValues($nested)['extra_data'], true));
        $backup = KycMaskBackup::query()->where('source_table', 'audits')->where('source_id', $flat->id)->sole();
        $this->assertStringNotContainsString('1234-5678-9012', $backup->original_encrypted, 'the original is stored encrypted');

        $this->assertSame(0, $service->run(apply: true)['cells'], 'a second run finds nothing left');

        $service->restore();
        $this->assertSame($originalFlat, Audit::query()->toBase()->where('id', $flat->id)->value('new_values'));
        $this->assertSame('432143214321', json_decode($this->newValues($nested)['extra_data'], true)['adhar_no']);
        $this->assertFalse(KycMaskBackup::query()->where('source_id', $flat->id)->exists());
    }
}
