<?php

declare(strict_types=1);

namespace App\Services\Vehicle\Pricing\Engine;

/** A vehicle the calculation cannot price (no rule matches, no price…) — reported, nothing published for it. */
final class PricingFailure extends \RuntimeException {}
