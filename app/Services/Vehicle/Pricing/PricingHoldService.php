<?php

declare(strict_types=1);

namespace App\Services\Vehicle\Pricing;

use App\Models\Vehicle\Pricing\Hold;
use App\Models\Vehicle\Pricing\ImportSession;

/**
 * Price-list holds (DEC-073). While a list is held its vehicles are not calculated and quotations / bookings for them
 * are frozen until it is reopened. Lists: one per price-list sheet plus TAXI (the Passenger snapshots of taxi
 * vehicles) and ALL. Holds are set at Start or at the hold check and reopened when the process completes.
 */
class PricingHoldService
{
    public const LISTS = ['ALL' => 'All lists', 'PV' => 'PV', 'CV' => 'CV', 'BEV' => 'BEV', 'LMM' => 'LMM', 'LMM_TZU' => 'LMM TZU', 'CSD' => 'CSD', 'TAXI' => 'Taxi (Passenger permit)'];

    /** @param list<string> $lists */
    public function hold(array $lists, ?ImportSession $session = null, ?string $reason = null, ?int $userId = null): void
    {
        foreach ($this->normalise($lists) as $list) {
            $hold = Hold::putOnHold($list, $reason ?? ($session ? "Pricing process #{$session->id}" : null), $userId);
            if ($session && $hold->import_session_id !== $session->id) {
                $hold->forceFill(['import_session_id' => $session->id])->save();
            }
        }
    }

    /** @param list<string> $lists */
    public function reopen(array $lists, ?string $reason = null, ?int $userId = null): void
    {
        foreach ($this->normalise($lists) as $list) {
            Hold::reopen($list, $reason, $userId);
        }
    }

    /** @return list<string> lists currently on hold */
    public function heldLists(): array
    {
        return Hold::query()->held()->orderBy('scope')->pluck('scope')->map(fn ($s) => strtoupper((string) $s))->all();
    }

    /**
     * Is a vehicle's price held?
     *
     * @param  string  $list  the vehicle's price list (PV, CV, BEV, LMM, LMM_TZU)
     */
    public function isHeld(string $list, string $channel = 'normal', ?string $permit = null, bool $taxi = false): bool
    {
        $held = array_flip($this->heldLists());
        if ($held === []) {
            return false;
        }

        return isset($held['ALL'])
            || isset($held[strtoupper($list)])
            || ($channel === 'csd' && isset($held['CSD']))
            || ($taxi && strtoupper((string) $permit) === 'PASSENGER' && isset($held['TAXI']));
    }

    /**
     * @param  list<string>  $lists
     * @return list<string>
     */
    private function normalise(array $lists): array
    {
        $out = [];
        foreach ($lists as $list) {
            $code = strtoupper(str_replace(' ', '_', trim((string) $list)));
            if (isset(self::LISTS[$code])) {
                $out[] = $code;
            }
        }

        return array_values(array_unique($out));
    }
}
