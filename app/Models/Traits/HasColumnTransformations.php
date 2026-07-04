<?php

namespace App\Models\Traits;

use Illuminate\Support\Str;

/**
 * Trait HasColumnTransformations
 *
 * Automatically transform column values on create/update (write) and
 * optionally on attribute access (read) via Eloquent model events and
 * a custom accessor fallback.
 *
 * ──────────────────────────────────────────────────────────────────────────
 * USAGE IN MODEL
 * ──────────────────────────────────────────────────────────────────────────
 *
 *   use HasColumnTransformations;
 *
 *   // Simple single transformation
 *   protected array $columnTransformations = [
 *       'code'   => 'uppercase_alphanumeric_dash',
 *       'slug'   => 'lowercase_alphanumeric_dash',
 *       'name'   => 'title_case',
 *       'email'  => 'lowercase',
 *       'phone'  => 'numeric',
 *   ];
 *
 *   // Pipeline: apply transformations in order (array of strings)
 *   protected array $columnTransformations = [
 *       'name' => ['trim_spaces', 'title_case'],
 *   ];
 *
 *   // Custom regex transformation
 *   protected array $columnTransformations = [
 *       'custom_field' => ['regex' => '/[^A-Z0-9]/', 'replacement' => ''],
 *   ];
 *
 *   // Custom callback transformation
 *   protected array $columnTransformations = [
 *       'bio' => ['callback' => 'strip_tags'],
 *   ];
 *
 *   // Apply transformations only on write (default behaviour)
 *   protected array $transformOnRead = false; // default
 *
 *   // Apply transformations also on read (getters)
 *   protected array $transformOnRead = true;
 *
 * ──────────────────────────────────────────────────────────────────────────
 * AVAILABLE BUILT-IN TRANSFORMATIONS
 * ──────────────────────────────────────────────────────────────────────────
 *
 *  Uppercase group
 *      uppercase                         – strtoupper, preserves all chars
 *      uppercase_alphanumeric            – strip non-alphanumeric, uppercase
 *      uppercase_alphanumeric_dash       – keep A-Z 0-9 -, uppercase
 *      uppercase_alphanumeric_underscore – keep A-Z 0-9 _, uppercase
 *      uppercase_alphanumeric_dash_underscore
 *
 *  Lowercase group
 *      lowercase                         – strtolower, preserves all chars
 *      lowercase_alphanumeric
 *      lowercase_alphanumeric_dash
 *      lowercase_alphanumeric_underscore
 *      lowercase_alphanumeric_dash_underscore
 *      lowercase_alphanumeric_dash_dot   – useful for file names / domains
 *
 *  Case group
 *      title_case                        – mb_convert_case MB_CASE_TITLE
 *      sentence_case                     – ucfirst(strtolower)
 *      capitalize_first                  – ucfirst only
 *
 *  Alphanumeric filters
 *      alphanumeric                      – keep A-Za-z0-9
 *      numeric                           – keep 0-9
 *      alpha                             – keep A-Za-z
 *
 *  Slug / identifier formats
 *      slug                              – Str::slug (URL-safe lowercase)
 *      snake_case                        – Str::snake
 *      kebab_case                        – Str::kebab
 *      camel_case                        – Str::camel
 *      pascal_case                       – Str::studly
 *
 *  Whitespace / trimming
 *      trim                              – PHP trim()
 *      trim_spaces                       – collapse internal whitespace + trim
 *
 *  Formatting helpers
 *      strip_tags                        – remove HTML/PHP tags
 *      truncate:{n}                      – truncate to n characters (e.g. truncate:255)
 *      pad_left:{n}:{char}               – str_pad left  (e.g. pad_left:6:0 → "000042")
 *      pad_right:{n}:{char}              – str_pad right
 *      mask_email                        – j***@example.com
 *      mask_phone                        – keep last 4 digits, mask rest
 *      base64_encode / base64_decode     – encode / decode base64
 *      json_encode / json_decode         – encode array→JSON / decode JSON→array
 *      md5 / sha1 / sha256               – one-way hash (irreversible)
 *
 * ──────────────────────────────────────────────────────────────────────────
 */
trait HasColumnTransformations
{
    // -----------------------------------------------------------------------
    // Boot
    // -----------------------------------------------------------------------

    protected static function bootHasColumnTransformations(): void
    {
        static::creating(function ($model) {
            $model->applyColumnTransformations();
        });

        static::updating(function ($model) {
            $model->applyColumnTransformations();
        });
    }

    // -----------------------------------------------------------------------
    // Core: apply all transformations to dirty / all attributes
    // -----------------------------------------------------------------------

    /**
     * Run all $columnTransformations rules against the current model attributes.
     */
    public function applyColumnTransformations(): void
    {
        if (empty($this->columnTransformations)) {
            return;
        }

        foreach ($this->columnTransformations as $column => $transformation) {
            // Skip columns that are neither fillable nor unguarded
            if (! $this->isColumnWritable($column)) {
                continue;
            }

            $rawValue = $this->attributes[$column] ?? null;

            // Only transform non-null, non-empty-string values
            // (preserve explicit null; preserve empty string as-is unless 'trim' is set)
            if ($rawValue === null) {
                continue;
            }

            $this->attributes[$column] = $this->runTransformation((string) $rawValue, $transformation);
        }
    }

    /**
     * Optional read-time transformation via getAttribute override.
     * Activated by setting `protected bool $transformOnRead = true;` on the model.
     */
    public function getAttribute($key)
    {
        $value = parent::getAttribute($key);

        if (
            ($this->transformOnRead ?? false) &&
            $value !== null &&
            isset($this->columnTransformations[$key])
        ) {
            return $this->runTransformation((string) $value, $this->columnTransformations[$key]);
        }

        return $value;
    }

    // -----------------------------------------------------------------------
    // Transformation pipeline runner
    // -----------------------------------------------------------------------

    /**
     * Execute a transformation definition against a value.
     *
     * $definition can be:
     *   - string  'uppercase'
     *   - string  'truncate:100'
     *   - array   ['trim_spaces', 'title_case']          (pipeline)
     *   - array   ['regex' => '/.../', 'replacement' => '...']
     *   - array   ['callback' => callable]
     */
    protected function runTransformation(string $value, mixed $definition): string
    {
        // Ordered pipeline: array of string transformation names
        if (is_array($definition) && array_is_list($definition)) {
            foreach ($definition as $step) {
                $value = $this->applyNamedTransformation($value, $step);
            }
            return $value;
        }

        // Associative array: custom regex or callback
        if (is_array($definition)) {
            return $this->applyCustomTransformation($value, $definition);
        }

        // Single named transformation (may contain parameters after colon)
        return $this->applyNamedTransformation($value, $definition);
    }

    // -----------------------------------------------------------------------
    // Named transformation dispatcher
    // -----------------------------------------------------------------------

    protected function applyNamedTransformation(string $value, string $transformation): string
    {
        // Parameterised transformations: e.g. "truncate:255", "pad_left:6:0"
        if (str_contains($transformation, ':')) {
            return $this->applyParameterizedTransformation($value, $transformation);
        }

        return match ($transformation) {
            // ── Uppercase ──────────────────────────────────────────────────
            'uppercase'                            => strtoupper($value),
            'uppercase_alphanumeric'               => $this->uppercaseAlphanumeric($value),
            'uppercase_alphanumeric_dash'          => $this->uppercaseAlphanumericDash($value),
            'uppercase_alphanumeric_underscore'    => $this->uppercaseAlphanumericUnderscore($value),
            'uppercase_alphanumeric_dash_underscore' => $this->uppercaseAlphanumericDashUnderscore($value),

            // ── Lowercase ──────────────────────────────────────────────────
            'lowercase'                            => strtolower($value),
            'lowercase_alphanumeric'               => $this->lowercaseAlphanumeric($value),
            'lowercase_alphanumeric_dash'          => $this->lowercaseAlphanumericDash($value),
            'lowercase_alphanumeric_underscore'    => $this->lowercaseAlphanumericUnderscore($value),
            'lowercase_alphanumeric_dash_underscore' => $this->lowercaseAlphanumericDashUnderscore($value),
            'lowercase_alphanumeric_dash_dot'      => $this->lowercaseAlphanumericDashDot($value),

            // ── Case ───────────────────────────────────────────────────────
            'title_case'      => $this->titleCase($value),
            'sentence_case'   => $this->sentenceCase($value),
            'capitalize_first' => ucfirst($value),

            // ── Alphanumeric filters ───────────────────────────────────────
            'alphanumeric'    => $this->alphanumeric($value),
            'numeric'         => $this->numeric($value),
            'alpha'           => $this->alpha($value),

            // ── Slug / identifier formats ──────────────────────────────────
            'slug'        => Str::slug($value),
            'snake_case'  => Str::snake($value),
            'kebab_case'  => Str::kebab($value),
            'camel_case'  => Str::camel($value),
            'pascal_case' => Str::studly($value),

            // ── Whitespace ─────────────────────────────────────────────────
            'trim'        => trim($value),
            'trim_spaces' => $this->trimSpaces($value),

            // ── Formatting helpers ─────────────────────────────────────────
            'strip_tags'    => strip_tags($value),
            'mask_email'    => $this->maskEmail($value),
            'mask_phone'    => $this->maskPhone($value),
            'base64_encode' => base64_encode($value),
            'base64_decode' => base64_decode($value, strict: false) ?: $value,
            'json_encode'   => json_encode($value, JSON_UNESCAPED_UNICODE),
            'json_decode'   => $this->jsonDecodeToString($value),
            'md5'           => md5($value),
            'sha1'          => sha1($value),
            'sha256'        => hash('sha256', $value),

            // ── Passthrough ────────────────────────────────────────────────
            default => $value,
        };
    }

    // -----------------------------------------------------------------------
    // Parameterised transformations  (name:arg1:arg2)
    // -----------------------------------------------------------------------

    protected function applyParameterizedTransformation(string $value, string $definition): string
    {
        $parts = explode(':', $definition);
        $name  = array_shift($parts);

        return match ($name) {
            'truncate'  => mb_substr($value, 0, (int) ($parts[0] ?? 255)),
            'pad_left'  => str_pad($value, (int) ($parts[0] ?? 0), $parts[1] ?? ' ', STR_PAD_LEFT),
            'pad_right' => str_pad($value, (int) ($parts[0] ?? 0), $parts[1] ?? ' ', STR_PAD_RIGHT),
            'pad_both'  => str_pad($value, (int) ($parts[0] ?? 0), $parts[1] ?? ' ', STR_PAD_BOTH),
            'prefix'    => implode(':', $parts) . $value,
            'suffix'    => $value . implode(':', $parts),
            'replace'   => str_replace($parts[0] ?? '', $parts[1] ?? '', $value),
            default     => $value,
        };
    }

    // -----------------------------------------------------------------------
    // Custom array-based transformations
    // -----------------------------------------------------------------------

    protected function applyCustomTransformation(string $value, array $config): string
    {
        // Regex replacement
        if (isset($config['regex'], $config['replacement'])) {
            $result = preg_replace($config['regex'], $config['replacement'], $value);
            return $result ?? $value;
        }

        // Callable / Closure
        if (isset($config['callback']) && is_callable($config['callback'])) {
            $result = call_user_func($config['callback'], $value);
            return is_string($result) ? $result : (string) $result;
        }

        // Multiple regex replacements  [['regex' => '...', 'replacement' => '...'], ...]
        if (isset($config[0]) && is_array($config[0])) {
            foreach ($config as $step) {
                if (isset($step['regex'], $step['replacement'])) {
                    $value = preg_replace($step['regex'], $step['replacement'], $value) ?? $value;
                }
            }
            return $value;
        }

        return $value;
    }

    // -----------------------------------------------------------------------
    // Column writability check
    // -----------------------------------------------------------------------

    /**
     * Returns true when a column is safe to write:
     *   - model has no guarded columns ($guarded = [])
     *   - OR the column is explicitly fillable
     *   - AND the column is not explicitly guarded
     */
    protected function isColumnWritable(string $column): bool
    {
        // Totally unguarded model
        if ($this->getGuarded() === []) {
            return true;
        }

        // Explicitly guarded
        if (in_array($column, $this->getGuarded(), true) || $column === '*') {
            return false;
        }

        return $this->isFillable($column);
    }

    // -----------------------------------------------------------------------
    // Uppercase helpers
    // -----------------------------------------------------------------------

    protected function uppercaseAlphanumeric(string $value): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $value));
    }

    protected function uppercaseAlphanumericDash(string $value): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9\-]/', '', $value));
    }

    protected function uppercaseAlphanumericUnderscore(string $value): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9_]/', '', $value));
    }

    protected function uppercaseAlphanumericDashUnderscore(string $value): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9\-_]/', '', $value));
    }

    // -----------------------------------------------------------------------
    // Lowercase helpers
    // -----------------------------------------------------------------------

    protected function lowercaseAlphanumeric(string $value): string
    {
        return strtolower(preg_replace('/[^A-Za-z0-9]/', '', $value));
    }

    protected function lowercaseAlphanumericDash(string $value): string
    {
        $cleaned = preg_replace('/[^A-Za-z0-9\-]/', '', $value);
        $cleaned = preg_replace('/-+/', '-', $cleaned);
        return strtolower(trim($cleaned, '-'));
    }

    protected function lowercaseAlphanumericUnderscore(string $value): string
    {
        $cleaned = preg_replace('/[^A-Za-z0-9_]/', '', $value);
        $cleaned = preg_replace('/_+/', '_', $cleaned);
        return strtolower(trim($cleaned, '_'));
    }

    protected function lowercaseAlphanumericDashUnderscore(string $value): string
    {
        return strtolower(preg_replace('/[^A-Za-z0-9\-_]/', '', $value));
    }

    protected function lowercaseAlphanumericDashDot(string $value): string
    {
        return strtolower(preg_replace('/[^A-Za-z0-9\-.]/', '', $value));
    }

    // -----------------------------------------------------------------------
    // Case helpers
    // -----------------------------------------------------------------------

    protected function titleCase(string $value): string
    {
        return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
    }

    protected function sentenceCase(string $value): string
    {
        return ucfirst(strtolower($value));
    }

    // -----------------------------------------------------------------------
    // Alphanumeric filter helpers
    // -----------------------------------------------------------------------

    protected function alphanumeric(string $value): string
    {
        return preg_replace('/[^A-Za-z0-9]/', '', $value);
    }

    protected function numeric(string $value): string
    {
        return preg_replace('/[^0-9]/', '', $value);
    }

    protected function alpha(string $value): string
    {
        return preg_replace('/[^A-Za-z]/', '', $value);
    }

    // -----------------------------------------------------------------------
    // Whitespace helpers
    // -----------------------------------------------------------------------

    protected function trimSpaces(string $value): string
    {
        return preg_replace('/\s+/', ' ', trim($value));
    }

    // -----------------------------------------------------------------------
    // Masking helpers
    // -----------------------------------------------------------------------

    protected function maskEmail(string $value): string
    {
        if (! str_contains($value, '@')) {
            return $value;
        }
        [$local, $domain] = explode('@', $value, 2);
        $visible = mb_substr($local, 0, 1);
        $masked  = str_repeat('*', max(1, mb_strlen($local) - 1));
        return $visible . $masked . '@' . $domain;
    }

    protected function maskPhone(string $value): string
    {
        $digitsOnly = preg_replace('/\D/', '', $value);
        $len        = strlen($digitsOnly);
        if ($len < 4) {
            return $value;
        }
        $last4  = substr($digitsOnly, -4);
        $masked = str_repeat('*', $len - 4);
        return $masked . $last4;
    }

    // -----------------------------------------------------------------------
    // JSON helper
    // -----------------------------------------------------------------------

    protected function jsonDecodeToString(string $value): string
    {
        $decoded = json_decode($value, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $value;
        }
        return is_string($decoded) ? $decoded : json_encode($decoded, JSON_UNESCAPED_UNICODE);
    }

    // -----------------------------------------------------------------------
    // Public utility: transform a value manually (useful in seeders / imports)
    // -----------------------------------------------------------------------

    /**
     * Manually transform a value using any rule defined in this trait.
     *
     * Example:
     *   $model->transformRaw('  Hello WORLD  ', ['trim_spaces', 'title_case']);
     *   // → "Hello World"
     */
    public function transformRaw(string $value, mixed $transformation): string
    {
        return $this->runTransformation($value, $transformation);
    }
}
