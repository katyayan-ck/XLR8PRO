<?php

declare(strict_types=1);

namespace App\Services\Vehicle\Pricing\Engine;

use App\Models\Vehicle\Variant;
use App\Services\KeywordValueService;
use App\Services\Utils\SynonymService;
use App\Services\Vehicle\Pricing\Rules\RuleRange;

/**
 * Everything the pricing rules match a vehicle on, resolved once per vehicle (DEC-080): codes and names up the hierarchy,
 * permit / fuel / body codes, and the numbers the ranges test (wheels, seating, GVW, CC, motor kW).
 */
final class VehicleFacts
{
    private function __construct(
        public readonly Variant $variant,
        public readonly string $code,
        public readonly string $segment,
        public readonly string $subSegment,
        public readonly string $modelCode,
        /** @var list<string> the model's code and names, upper case (scope cells may use any of them) */
        public readonly array $modelNames,
        public readonly ?string $permit,
        public readonly ?string $fuel,
        public readonly ?string $bodyType,
        public readonly ?string $bodyMake,
        public readonly ?int $wheels,
        public readonly ?int $seating,
        public readonly ?float $gvw,
        public readonly ?float $cc,
        public readonly ?float $motorKw,
        public readonly ?string $transmission,
        public readonly ?string $shieldPack,
        public readonly bool $taxi,
    ) {}

    /** @param array<string, array<int, string>> $codes keyword => [id => code] (from ::keyvalueCodes()) */
    public static function of(Variant $v, array $codes): self
    {
        $model = $v->vehicleModel;
        $names = array_values(array_unique(array_filter(array_map(
            fn ($n) => strtoupper(trim((string) $n)),
            [$v->model_code, $model?->name, $model?->oem_name, $model?->custom_name]
        ))));

        return new self(
            variant: $v,
            code: strtoupper($v->code),
            segment: strtoupper((string) $v->segment_code),
            subSegment: strtoupper((string) $v->sub_segment_code),
            modelCode: strtoupper((string) $v->model_code),
            modelNames: $names,
            permit: $codes['PERMIT'][(int) $v->permit_id] ?? null,
            fuel: $codes['FUEL_TYPE'][(int) $v->fuel_type_id] ?? null,
            bodyType: $codes['BODY_TYPE'][(int) $v->body_type_id] ?? null,
            bodyMake: $codes['BODY_MAKE'][(int) $v->body_make_id] ?? null,
            wheels: $v->wheels,
            seating: $v->seating_capacity,
            gvw: $v->gvw !== null ? (float) $v->gvw : null,
            cc: is_numeric($v->cc_capacity) ? (float) $v->cc_capacity : null,
            motorKw: self::representative($v->motor),
            transmission: $v->transmission !== null ? strtoupper(trim($v->transmission)) : null,
            shieldPack: $v->shield_pack !== null && trim($v->shield_pack) !== '' ? strtoupper(trim($v->shield_pack)) : null,
            taxi: strtoupper((string) $v->taxi_price) === 'YES',
        );
    }

    /** @return array<string, array<int, string>> keyword => [key-value id => code], for every vehicle of a run */
    public static function keyvalueCodes(): array
    {
        $out = [];
        foreach (['PERMIT', 'FUEL_TYPE', 'BODY_TYPE', 'BODY_MAKE'] as $keyword) {
            foreach (array_keys(KeywordValueService::getEnum($keyword, false)) as $code) {
                if (($id = KeywordValueService::getValueId($keyword, (string) $code, false)) !== null) {
                    $out[$keyword][$id] = strtoupper((string) $code);
                }
            }
        }

        return $out;
    }

    public function isElectric(): bool
    {
        return $this->fuel !== null && (str_contains($this->fuel, 'ELECTRIC') || $this->fuel === 'EV');
    }

    public function hasGasKit(): bool
    {
        return $this->fuel !== null && (str_contains($this->fuel, 'CNG') || str_contains($this->fuel, 'LPG'));
    }

    public function isGoods(?string $permit = null): bool
    {
        return strtoupper((string) ($permit ?? $this->permit)) === 'GOODS';
    }

    /** The fuel as the rule sheets write it, through the Fuel synonyms (Electric → EV). */
    public function fuelFor(SynonymService $synonyms): ?string
    {
        return $this->fuel === null ? null : strtoupper((string) ($synonyms->resolve('Fuel', $this->fuel) ?? $this->fuel));
    }

    /** A number inside a written power band (">65KW" → 65.5) so a band can be matched against the rule bands. */
    private static function representative(mixed $motor): ?float
    {
        if ($motor === null || trim((string) $motor) === '') {
            return null;
        }
        try {
            $r = RuleRange::parse($motor);
        } catch (\InvalidArgumentException) {
            return null;
        }
        if ($r->min !== null && $r->max !== null) {
            return ($r->min + $r->max) / 2;
        }

        return $r->min !== null ? $r->min + 0.5 : ($r->max !== null ? $r->max - 0.5 : null);
    }
}
