<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Platform\Privacy\KycHistoryMaskingService;
use Illuminate\Console\Command;

/**
 * D26 (DEC-095 #19): masks the Aadhaar / PAN copies in history rows (booking timeline + change log); the KYC record
 * itself is not touched. Without --apply it only reports. --apply keeps every original encrypted first; --restore puts
 * them back. Runs locally; on UAT / production only with the owner's approval (non-local data change).
 *
 *   php artisan privacy:mask-kyc-history             report
 *   php artisan privacy:mask-kyc-history --apply     mask (originals backed up, encrypted)
 *   php artisan privacy:mask-kyc-history --restore   undo
 */
class MaskKycHistory extends Command
{
    protected $signature = 'privacy:mask-kyc-history {--apply : mask the values (default: report only)} {--restore : put the backed-up originals back}';

    protected $description = 'Mask Aadhaar / PAN copies in the booking timeline and change log (D26), reversibly';

    public function handle(KycHistoryMaskingService $service): int
    {
        if ($this->option('restore')) {
            $result = $service->restore();
            $this->info("{$result['restored']} cell(s) restored.");
            if ($result['failed'] > 0) {
                $this->error("{$result['failed']} backup(s) could not be decrypted with this APP_KEY and were left in place.");

                return self::FAILURE;
            }

            return self::SUCCESS;
        }

        $apply = (bool) $this->option('apply');
        $report = $service->run($apply);
        $this->info($apply ? 'Masked (originals backed up, encrypted).' : 'Report only — add --apply to mask.');
        $this->table(['Table', 'Cells'], collect($report['tables'])->map(fn (int $n, string $t) => [$t, $n])->values()->all());
        $this->line(sprintf('Values: %d Aadhaar, %d PAN, %d other (left as they are).', $report['values']['aadhaar'], $report['values']['pan'], $report['values']['other']));

        return self::SUCCESS;
    }
}
