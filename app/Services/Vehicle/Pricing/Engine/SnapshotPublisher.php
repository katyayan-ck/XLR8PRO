<?php

declare(strict_types=1);

namespace App\Services\Vehicle\Pricing\Engine;

use App\Models\Vehicle\Pricing\Snapshot;
use App\Models\Vehicle\Variant;
use Illuminate\Support\Facades\DB;

/**
 * Writes one vehicle's snapshots in one transaction (DEC-080): a snapshot with the same key (vehicle, channel, VIN type,
 * permit, WEF) is updated in place, otherwise inserted; every other live snapshot of the vehicle is expired at the WEF
 * (a newer WEF, or a permit / channel that no longer applies). Snapshots are never deleted.
 */
final class SnapshotPublisher
{
    /**
     * @param  list<array{channel: string, vin_type: string, permit: string, rto_permit: string, insu_permit: string, payload: array<string, mixed>}>  $snapshots
     * @return int snapshots written
     */
    public function publish(Variant $variant, array $snapshots, string $wefDate, int $sessionId): int
    {
        return DB::transaction(function () use ($variant, $snapshots, $wefDate, $sessionId) {
            $kept = [];
            $now = now()->toIso8601String();
            foreach ($snapshots as $s) {
                $key = ['model_code' => $variant->code, 'channel' => $s['channel'], 'vin_type' => $s['vin_type'], 'permit' => $s['permit'], 'wef_date' => $wefDate];
                $row = Snapshot::query()->where($key)->first() ?? new Snapshot($key);
                $row->fill([
                    'import_session_id' => $sessionId, 'variant_code' => $variant->code, 'rto_permit' => $s['rto_permit'], 'insu_permit' => $s['insu_permit'],
                    'price_list' => $s['payload']['price_list'] ?? null, 'vehicle_permit' => $s['payload']['vehicle_permit'] ?? null,
                    'payload' => ['published_at' => $now] + $s['payload'], 'is_active' => true, 'expired_on' => null,
                ])->save();
                $kept[] = $row->id;
            }
            Snapshot::query()->where('model_code', $variant->code)->where('is_active', true)->whereNotIn('id', $kept)
                ->get()->each(fn (Snapshot $old) => $old->forceFill(['is_active' => false, 'expired_on' => $wefDate])->save());

            return count($kept);
        });
    }
}
