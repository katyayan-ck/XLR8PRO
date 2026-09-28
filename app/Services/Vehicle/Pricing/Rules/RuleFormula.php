<?php

declare(strict_types=1);

namespace App\Services\Vehicle\Pricing\Rules;

/**
 * The small arithmetic the RTO / insurance rule sheets write in cells (DEC-078), evaluated safely (no eval()).
 *
 *   "(10% * 1.25 * 2) / 15"   → 0.016667        "12.5% of Tax"        → 0.125 × TAX
 *   "5% x OD"                 → 0.05 × OD        "15% x (OD + LPG)"   → 0.15 × (OD + LPG)
 *   "1162 x (Seat -1)"        → 1162 × (SEAT−1)  "150 Per Seat"       → 150 × SEAT
 *
 * Numbers, `%` (÷100), + − × (x, *) ÷ (/), parentheses, "of" / "per" (= ×) and the variables in VARIABLES
 * (case-insensitive; "Setat" is read as SEAT). Plain numbers are formulas too ("6521").
 *
 *   RuleFormula::evaluate('5% x OD', ['OD' => 12000]);    // 600.0
 *   RuleFormula::variables('1162 x (Seat -1)');          // ['SEAT']
 */
final class RuleFormula
{
    public const VARIABLES = ['OD', 'LPG', 'SEAT', 'IDV', 'INVOICE', 'TAX', 'ESR', 'TP'];

    private const ALIASES = ['SETAT' => 'SEAT', 'SEATS' => 'SEAT', 'INV' => 'INVOICE'];

    /** @var list<array{0: string, 1: string|float}> */
    private array $tokens;

    private int $pos = 0;

    /** @param array<string, float|int> $vars */
    private function __construct(string $formula, private readonly array $vars)
    {
        $this->tokens = self::tokenize($formula);
    }

    /**
     * @param  array<string, float|int>  $vars  variable => value (keys case-insensitive)
     *
     * @throws \InvalidArgumentException on a syntax error or a variable without a value
     */
    public static function evaluate(mixed $formula, array $vars = []): float
    {
        $parser = new self((string) $formula, array_change_key_case($vars, CASE_UPPER));
        $value = $parser->expression();
        if ($parser->pos < count($parser->tokens)) {
            throw new \InvalidArgumentException("Unexpected \"{$parser->tokens[$parser->pos][1]}\" in \"{$formula}\".");
        }

        return $value;
    }

    /**
     * Variables a formula uses (upper case), after checking its syntax.
     *
     * @return list<string>
     *
     * @throws \InvalidArgumentException
     */
    public static function variables(mixed $formula): array
    {
        $names = [];
        foreach (self::tokenize((string) $formula) as [$type, $value]) {
            if ($type === 'var') {
                $names[(string) $value] = true;
            }
        }
        self::evaluate($formula, array_fill_keys(array_keys($names), 1));

        return array_keys($names);
    }

    /** A plain number (no operator, no variable)? */
    public static function isNumber(mixed $value): bool
    {
        return is_int($value) || is_float($value) || (is_string($value) && is_numeric(trim($value)));
    }

    /** @return list<array{0: string, 1: string|float}> */
    private static function tokenize(string $formula): array
    {
        $s = strtoupper(trim($formula));
        if ($s === '') {
            throw new \InvalidArgumentException('Empty formula.');
        }
        preg_match_all('/\s*(\d+(?:\.\d+)?|[A-Z]+|[%()+\-*\/])/', $s, $m, PREG_OFFSET_CAPTURE);
        $consumed = implode('', array_map(fn ($x) => $x[0], $m[0]));
        if (preg_replace('/\s+/', '', $consumed) !== preg_replace('/\s+/', '', $s)) {
            throw new \InvalidArgumentException("\"{$formula}\" has characters a formula cannot use.");
        }
        $tokens = [];
        foreach ($m[1] as [$t]) {
            if (is_numeric($t)) {
                $tokens[] = ['num', (float) $t];
            } elseif (in_array($t, ['X', 'OF', 'PER'], true)) {
                $tokens[] = ['op', '*'];
            } elseif (ctype_alpha($t)) {
                $name = self::ALIASES[$t] ?? $t;
                if (! in_array($name, self::VARIABLES, true)) {
                    throw new \InvalidArgumentException("\"{$t}\" is not a known variable (".implode(', ', self::VARIABLES).').');
                }
                $tokens[] = ['var', $name];
            } else {
                $tokens[] = ['op', $t];
            }
        }

        return $tokens;
    }

    private function expression(): float
    {
        $value = $this->term();
        while ($this->peek('+') || $this->peek('-')) {
            $op = $this->tokens[$this->pos++][1];
            $right = $this->term();
            $value = $op === '+' ? $value + $right : $value - $right;
        }

        return $value;
    }

    private function term(): float
    {
        $value = $this->factor();
        while ($this->peek('*') || $this->peek('/')) {
            $op = $this->tokens[$this->pos++][1];
            $right = $this->factor();
            if ($op === '/' && $right == 0.0) {
                throw new \InvalidArgumentException('Division by zero.');
            }
            $value = $op === '*' ? $value * $right : $value / $right;
        }

        return $value;
    }

    private function factor(): float
    {
        $token = $this->tokens[$this->pos] ?? null;
        if ($token === null) {
            throw new \InvalidArgumentException('The formula ends too early.');
        }
        $this->pos++;
        if ($token === ['op', '-']) {
            return -$this->factor();
        }
        if ($token === ['op', '(']) {
            $value = $this->expression();
            if (! $this->peek(')')) {
                throw new \InvalidArgumentException('A closing ")" is missing.');
            }
            $this->pos++;

            return $this->percent($value);
        }
        if ($token[0] === 'num') {
            return $this->percent((float) $token[1]);
        }
        if ($token[0] === 'var') {
            if (! array_key_exists((string) $token[1], $this->vars)) {
                throw new \InvalidArgumentException("No value for {$token[1]}.");
            }

            return (float) $this->vars[(string) $token[1]];
        }

        throw new \InvalidArgumentException("Unexpected \"{$token[1]}\".");
    }

    private function percent(float $value): float
    {
        if ($this->peek('%')) {
            $this->pos++;

            return $value / 100;
        }

        return $value;
    }

    private function peek(string $op): bool
    {
        return ($this->tokens[$this->pos] ?? null) === ['op', $op];
    }
}
