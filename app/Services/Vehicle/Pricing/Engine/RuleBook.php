<?php

declare(strict_types=1);

namespace App\Services\Vehicle\Pricing\Engine;

use App\Models\Vehicle\Pricing\Addon;
use App\Models\Vehicle\Pricing\DealerCharge;
use App\Models\Vehicle\Pricing\Discount;
use App\Models\Vehicle\Pricing\InsAddon;
use App\Models\Vehicle\Pricing\InsAddonRate;
use App\Models\Vehicle\Pricing\InsBaseRule;
use App\Models\Vehicle\Pricing\InsDefault;
use App\Models\Vehicle\Pricing\InsIdvSlot;
use App\Models\Vehicle\Pricing\PermitMap;
use App\Models\Vehicle\Pricing\RtoRule;
use App\Models\Vehicle\Pricing\TcsConfig;
use App\Services\Utils\SynonymService;
use Illuminate\Support\Collection;

/**
 * Every live rule row the calculation reads, loaded once per run / chunk (DEC-080) — the rule tables hold hundreds of
 * rows, so matching in memory is far cheaper than a query per vehicle. Also carries the settings the math uses.
 */
final class RuleBook
{
    /** @var Collection<int, DealerCharge> */
    public Collection $dealerCharges;

    /** @var Collection<int, Addon> */
    public Collection $rsa;

    /** @var Collection<int, Addon> */
    public Collection $shield;

    /** @var Collection<int, Discount> */
    public Collection $exchange;

    /** @var Collection<int, Discount> */
    public Collection $corporate;

    /** @var Collection<int, Discount> loyalty schemes (DEC-083, like exchange) */
    public Collection $loyalty;

    /** @var Collection<int, RtoRule> */
    public Collection $rto;

    /** @var Collection<int, InsBaseRule> */
    public Collection $insurance;

    /** @var Collection<array-key, \Illuminate\Database\Eloquent\Collection<int, InsIdvSlot>> base rule id => slots */
    public Collection $idvSlots;

    /** @var Collection<array-key, \Illuminate\Database\Eloquent\Collection<int, InsAddonRate>> base rule id => rates */
    public Collection $addonRates;

    /** @var Collection<int, InsDefault> */
    public Collection $insuranceDefaults;

    /** @var Collection<int, PermitMap> */
    public Collection $permitMap;

    public TcsConfig $tcs;

    /** @var array<string, string> insurance add-on code => display name (add-on master, DEC-083) */
    public array $insuranceAddonNames = [];

    /** @var list<string> add-on codes of the default (frozen) insurance combo */
    public array $defaultInsuranceAddons = InsuranceCalculator::DEFAULT_ADDONS;

    /** @var array<string, array<int, string>> key-value id => code per keyword */
    public array $keyvalueCodes;

    public float $odDiscountPct;

    public float $insuranceGstPct;

    public float $goodsTpGstPct;

    public int $roundUpTo;

    /** COD in the default on-road total (setting pricing.dealer_charges.include_cod, DEC-080) */
    public bool $includeCod;

    public function __construct(public readonly SynonymService $synonyms)
    {
        $this->dealerCharges = DealerCharge::query()->where('is_active', true)->orderBy('id')->get();
        $addons = Addon::query()->where('is_active', true)->orderBy('id')->get();
        $this->rsa = $addons->where('addon_type', 'RSA')->values();
        $this->shield = $addons->where('addon_type', 'SHIELD')->values();
        $discounts = Discount::query()->where('is_active', true)->orderBy('id')->get();
        $this->exchange = $discounts->where('discount_type', 'EXCHANGE')->values();
        $this->corporate = $discounts->where('discount_type', 'CORPORATE')->values();
        $this->loyalty = $discounts->where('discount_type', 'LOYALTY')->values();
        $this->rto = RtoRule::query()->where('is_active', true)->orderBy('id')->get();
        $this->insurance = InsBaseRule::query()->where('is_active', true)->orderBy('id')->get();
        $ids = $this->insurance->pluck('id')->all();
        $this->idvSlots = InsIdvSlot::query()->whereIn('base_rule_id', $ids)->orderBy('year_no')->get()->groupBy('base_rule_id');
        $this->addonRates = InsAddonRate::query()->where('is_active', true)->whereIn('base_rule_id', $ids)->orderBy('id')->get()->groupBy('base_rule_id');
        $this->insuranceDefaults = InsDefault::query()->where('is_active', true)->orderBy('priority')->get();
        $this->permitMap = PermitMap::query()->where('is_active', true)->get();
        $addonMaster = InsAddon::query()->where('is_active', true)->orderBy('sort_order')->get(['code', 'name', 'is_default']);
        $this->insuranceAddonNames = $addonMaster->mapWithKeys(fn (InsAddon $a) => [strtoupper($a->code) => $a->name])->all();
        $this->defaultInsuranceAddons = $addonMaster->where('is_default', true)->map(fn (InsAddon $a) => strtoupper($a->code))->values()->all()
            ?: InsuranceCalculator::DEFAULT_ADDONS;
        $this->tcs = TcsConfig::current();
        $this->keyvalueCodes = VehicleFacts::keyvalueCodes();
        $this->odDiscountPct = (float) setting('pricing.insurance.od_discount_pct', 30);
        $this->insuranceGstPct = (float) setting('pricing.insurance.gst_pct', 18);
        $this->goodsTpGstPct = (float) setting('pricing.insurance.goods_tp_gst_pct', 12);
        $this->roundUpTo = max(1, (int) setting('pricing.rto.round_up_to', 1000));
        $this->includeCod = (bool) setting('pricing.dealer_charges.include_cod', false);
    }

    /** Vehicle permit + wheels → the RTO / insurance permits (a wheel-specific row beats a no-wheels row). */
    public function permits(string $vehiclePermit, ?int $wheels): ?PermitMap
    {
        $rows = $this->permitMap->filter(fn (PermitMap $p) => strtoupper($p->vehicle_permit) === strtoupper($vehiclePermit)
            && ($p->wheels === null || $p->wheels === $wheels));

        return $rows->sortBy(fn (PermitMap $p) => $p->wheels === null ? 1 : 0)->first();
    }

    /** A scope value as the rule sheets store it, through a synonym type (Permit, Fuel, Segment). */
    public function canonical(string $type, ?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return strtoupper((string) ($this->synonyms->resolve($type, $value) ?? $value));
    }
}
