<?php

namespace App\Jobs\Vehicle\Pricing;

use App\Models\Vehicle\Pricing\MasterImport;
use App\Support\PricingMaster\MasterRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * One pricing-master import (DEC-083): reads the stored workbook through the master's definition (chunked, one
 * transaction per chunk) and records progress / the result on its MasterImport row. The observer queues the automatic
 * recalculation of whatever the import changed.
 */
class ImportPricingMasterJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(public int $importId) {}

    public function handle(): void
    {
        $import = MasterImport::query()->findOrFail($this->importId);
        $definition = MasterRegistry::get($import->master);
        $import->forceFill(['status' => 'running', 'message' => 'Reading the workbook…'])->save();
        if ($import->created_by) {
            auth(backpack_guard_name())->onceUsingId($import->created_by);   // audit stamps name the uploader
        }
        $result = $definition->import(Storage::disk('local')->path($import->path), $import->wef_date?->toDateString(),
            function (int $done) use ($import): void {
                $import->forceFill(['message' => number_format($done).' row(s) processed…'])->save();
            });
        $import->forceFill(['status' => 'done', 'result' => $result, 'message' => $this->summary($result)])->save();
    }

    public function failed(Throwable $e): void
    {
        Log::error('[Pricing] master import failed', ['import_id' => $this->importId, 'error' => $e->getMessage()]);
        MasterImport::query()->whereKey($this->importId)->update(['status' => 'failed', 'message' => mb_substr('Import failed: '.$e->getMessage(), 0, 250)]);
    }

    /** @param array<string, mixed> $result */
    private function summary(array $result): string
    {
        $parts = [];
        foreach (['rows' => 'rows', 'created' => 'created', 'updated' => 'updated', 'written' => 'written', 'expired' => 'replaced', 'rejected' => 'rejected'] as $key => $label) {
            if (isset($result[$key]) && is_numeric($result[$key])) {
                $parts[] = number_format((int) $result[$key]).' '.$label;
            }
        }

        return $parts === [] ? 'Import finished.' : 'Import finished: '.implode(', ', $parts).'.';
    }
}
