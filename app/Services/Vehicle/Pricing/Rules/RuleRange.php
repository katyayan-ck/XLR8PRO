<?php

declare(strict_types=1);

namespace App\Services\Vehicle\Pricing\Rules;

/**
 * A scope range from the RTO / insurance rule sheets (DEC-078), parsed once and matched by the engine.
 *
 *   ""  / ANY / ALL / *          → any value
 *   "0-3000", "3001 - 16500"     → between, inclusive
 *   "1 to 7", "30 - 65 KW"       → between (units are ignored)
 *   ">1500", "Above 2000000"     → greater than
 *   "<4", "< 30KW", "Below 5"    → less than
 *   ">=5", "<=7"                 → inclusive bounds
 *   "4"                          → exactly 4
 *
 *   RuleRange::parse('1001-1500')->contains(1197);   // true
 */
final class RuleRange
{
    private function __construct(
        public readonly ?float $min,
        public readonly ?float $max,
        public readonly bool $minInclusive,
        public readonly bool $maxInclusive,
        public readonly string $text,
    ) {}

    /** @throws \InvalidArgumentException when the text is not a range */
    public static function parse(mixed $value): self
    {
        $text = trim(preg_replace('/\s+/', ' ', (string) $value) ?? '');
        $t = strtoupper($text);
        if (in_array($t, ['', 'ANY', 'ALL', '*'], true)) {
            return new self(null, null, true, true, $text);
        }
        // drop units and thousands separators: "30 - 65 KW", "1,00,000"
        $n = trim(str_replace(',', '', preg_replace('/\s*(KW|KG|CC|KMS?|TONS?|SEATS?|STR)\b/i', '', $t) ?? $t));
        $num = '(\d+(?:\.\d+)?)';

        if (preg_match("/^{$num}\s*(?:-|TO)\s*{$num}$/", $n, $m)) {
            return new self((float) $m[1], (float) $m[2], true, true, $text);
        }
        if (preg_match("/^(>=|=>|<=|=<|>|<|ABOVE|BELOW|UPTO|UP TO)\s*{$num}$/", $n, $m)) {
            $v = (float) $m[2];

            return match ($m[1]) {
                '>', 'ABOVE' => new self($v, null, false, true, $text),
                '>=', '=>' => new self($v, null, true, true, $text),
                '<', 'BELOW' => new self(null, $v, true, false, $text),
                default => new self(null, $v, true, true, $text), // <=, =<, UPTO
            };
        }
        if (preg_match("/^{$num}$/", $n, $m)) {
            return new self((float) $m[1], (float) $m[1], true, true, $text);
        }

        throw new \InvalidArgumentException("\"{$text}\" is not a range (use e.g. 0-3000, >1500, <4, 1 to 7 or ANY).");
    }

    public function isAny(): bool
    {
        return $this->min === null && $this->max === null;
    }

    /** A range whose lower bound is above its upper bound can never match (e.g. "1000000 - 200000"). */
    public function isInverted(): bool
    {
        return $this->min !== null && $this->max !== null && $this->min > $this->max;
    }

    public function contains(float|int|null $value): bool
    {
        if ($this->isAny()) {
            return true;
        }
        if ($value === null) {
            return false;
        }
        if ($this->min !== null && ($this->minInclusive ? $value < $this->min : $value <= $this->min)) {
            return false;
        }

        return ! ($this->max !== null && ($this->maxInclusive ? $value > $this->max : $value >= $this->max));
    }
}
