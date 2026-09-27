<?php

declare(strict_types=1);

namespace App\Services\Platform\Settings;

use App\Events\Platform\SettingsChanged;
use App\Models\Utilities\Settings\SystemSetting;
use App\Models\Utilities\Settings\SystemSettingAudit;
use App\Support\Result;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * Site Settings (FRS §1) — the runtime configuration plane and the only write path for settings.
 *
 * - Keys are dotted (`sla.ticket.p1_hours`). Global values live in `xlr8_utils_system_setting`;
 *   scoped overrides (COMPANY → BRANCH → DESK, most specific wins) in `xlr8_utils_setting_scope`.
 * - Defaults come from `config('platform.settings')` so a missing row never breaks a caller.
 * - Types: string, int, decimal, bool, json, encrypted (stored with Crypt, never listed or logged).
 * - Every write is validated, audited (old → new, actor), cache-busted and emits SettingsChanged.
 */
final class SettingsService
{
    public const SCOPES = ['DESK', 'BRANCH', 'COMPANY'];

    /** DB type names used by existing rows ⇄ FRS type names. */
    private const TYPE_ALIASES = ['integer' => 'int', 'float' => 'decimal', 'boolean' => 'bool', 'array' => 'json'];

    public function get(string $key, mixed $default = null): mixed
    {
        $row = $this->row($key);
        if ($row === null) {
            return $this->configDefault($key, $default);
        }

        return $this->cast($row->value, $this->typeFor($key, $row), $this->configDefault($key, $default));
    }

    /**
     * Effective value for an org context: the most specific scoped override, else global.
     *
     * @param  string|array{desk?: ?string, branch?: ?string, company?: ?string}  $scope  a branch code or a scope map
     */
    public function getFor(string|array $scope, string $key, mixed $default = null): mixed
    {
        $scope = is_string($scope) ? ['branch' => $scope] : array_change_key_case($scope);
        $type = $this->typeFor($key, $this->row($key));

        foreach (self::SCOPES as $scopeType) {
            $code = $scope[strtolower($scopeType)] ?? null;
            if ($code === null || $code === '') {
                continue;
            }
            $override = Cache::rememberForever("setting.scope.{$key}.{$scopeType}.{$code}", fn () => DB::table('xlr8_utils_setting_scope')
                ->where('setting_key', $key)->where('scope_type', $scopeType)->where('scope_code', $code)->value('value') ?? '__none__');
            if ($override !== '__none__') {
                return $this->cast($override, $type, $default);
            }
        }

        return $this->get($key, $default);
    }

    public function flag(string $key, bool $default = false): bool
    {
        return (bool) $this->get($key, $default);
    }

    /**
     * Set a value globally, or for one scope (`COMPANY`, `BRANCH`, `DESK` + code).
     */
    public function set(string $key, mixed $value, ?string $scopeType = null, ?string $scopeCode = null, ?int $actorId = null): Result
    {
        $actorId ??= auth(backpack_guard_name())->id() ?? auth()->id();
        $row = $this->ensureRow($key);
        $type = $this->typeFor($key, $row);
        if ($this->typeOf($row->type) !== $type) {
            $row->forceFill(['type' => $type])->save();
        }

        if (! $row->iseditable) {
            return Result::fail('READ_ONLY', "Setting {$key} is read-only.");
        }
        $error = $this->validationError($row, $type, $value);
        if ($error !== null) {
            return Result::fail('INVALID_VALUE', $error);
        }
        $stored = $this->serialise($value, $type);

        try {
            return DB::transaction(function () use ($key, $row, $type, $stored, $scopeType, $scopeCode, $actorId) {
                if ($scopeType === null) {
                    $old = $row->value;
                    $row->value = $stored;
                    $row->save();
                } else {
                    $scopeType = strtoupper($scopeType);
                    if (! in_array($scopeType, self::SCOPES, true) || ! $scopeCode) {
                        return Result::fail('INVALID_SCOPE', 'Scope must be COMPANY, BRANCH or DESK with a code.');
                    }
                    $old = DB::table('xlr8_utils_setting_scope')->where(['setting_key' => $key, 'scope_type' => $scopeType, 'scope_code' => $scopeCode])->value('value');
                    DB::table('xlr8_utils_setting_scope')->updateOrInsert(
                        ['setting_key' => $key, 'scope_type' => $scopeType, 'scope_code' => $scopeCode],
                        ['value' => $stored, 'updated_by' => $actorId, 'updated_at' => now(), 'created_at' => now()]
                    );
                    Cache::forget("setting.scope.{$key}.{$scopeType}.{$scopeCode}");
                }

                $secret = $type === 'encrypted';
                SystemSettingAudit::create([
                    'setting_id' => $row->id,
                    'user_id' => $actorId,
                    'action' => $scopeType ? "update:{$scopeType}:{$scopeCode}" : 'update',
                    'old_value' => $secret ? '***' : $old,
                    'new_value' => $secret ? '***' : $stored,
                    'ip_address' => request()?->ip(),
                    'user_agent' => substr((string) request()?->userAgent(), 0, 250),
                ]);
                SystemSetting::flushCache($key);

                SettingsChanged::dispatch($key, $secret ? null : $this->cast($old, $type, null), $secret ? null : $this->cast($stored, $type, null), $scopeType, $scopeCode, $actorId);

                return Result::ok(['key' => $key]);
            });
        } catch (Throwable $e) {
            report($e);

            return Result::fail('WRITE_FAILED', $e->getMessage());
        }
    }

    /** Reset a global setting to its default (`default_value`, else the config seed). */
    public function reset(string $key, ?int $actorId = null): Result
    {
        $row = $this->ensureRow($key);
        $default = $row->default_value !== null ? $this->cast($row->default_value, $this->typeFor($key, $row), null) : ($this->seed($key)['value'] ?? null);

        return $this->set($key, $default, null, null, $actorId);
    }

    /** Remove a scoped override (the scope falls back to the next level). */
    public function clearScope(string $key, string $scopeType, string $scopeCode): Result
    {
        DB::table('xlr8_utils_setting_scope')->where(['setting_key' => $key, 'scope_type' => strtoupper($scopeType), 'scope_code' => $scopeCode])->delete();
        Cache::forget("setting.scope.{$key}.".strtoupper($scopeType).".{$scopeCode}");

        return Result::ok();
    }

    /**
     * Admin list: every setting (rows + config seeds), grouped by the key prefix, with masked secrets.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function adminList(?string $search = null): array
    {
        $rows = SystemSetting::query()->orderBy('key')->get()->keyBy('key');
        $keys = collect($rows->keys())->merge(array_keys(config('platform.settings', [])))->unique()->sort()->values();

        return $keys
            ->filter(fn ($key) => $search === null || $search === '' || str_contains(strtolower($key.' '.($rows[$key]->label ?? '')), strtolower($search)))
            ->map(function (string $key) use ($rows) {
                $row = $rows[$key] ?? null;
                $type = $this->typeFor($key, $row);
                $value = $row ? $this->cast($row->value, $type, $this->seed($key)['value'] ?? null) : ($this->seed($key)['value'] ?? null);

                return [
                    'key' => $key,
                    'label' => $row->label ?? $this->seed($key)['label'] ?? $key,
                    'type' => $type,
                    'value' => $type === 'encrypted' ? ($row?->value ? '••••••' : '') : $value,
                    'editable' => $row ? (bool) $row->iseditable : true,
                    'updated_at' => $row?->updated_at,
                    'overrides' => DB::table('xlr8_utils_setting_scope')->where('setting_key', $key)->get(['scope_type', 'scope_code', 'value'])->all(),
                ];
            })
            ->groupBy(fn (array $s) => strstr($s['key'], '.', true) ?: 'general')
            ->map(fn ($group) => $group->values()->all())
            ->all();
    }

    /** Warm every setting into the cache (deploy step, `settings:cache`). */
    public function warm(): int
    {
        $keys = SystemSetting::query()->pluck('key');
        $keys->each(fn ($key) => $this->get($key));

        return $keys->count();
    }

    public function clearCache(): void
    {
        SystemSetting::flushAllCache();
        DB::table('xlr8_utils_setting_scope')->get(['setting_key', 'scope_type', 'scope_code'])
            ->each(fn ($s) => Cache::forget("setting.scope.{$s->setting_key}.{$s->scope_type}.{$s->scope_code}"));
    }

    private function row(string $key): ?SystemSetting
    {
        return Cache::rememberForever("setting.row.{$key}", fn () => SystemSetting::query()->where('key', $key)->first()) ?: null;
    }

    /** The row for a key, created from the config seed when it does not exist yet. */
    private function ensureRow(string $key): SystemSetting
    {
        Cache::forget("setting.row.{$key}");
        $seed = $this->seed($key);
        $type = $seed['type'] ?? 'string';
        $row = SystemSetting::query()->firstOrCreate(['key' => $key], [
            'label' => $seed['label'] ?? $key,
            'type' => $type,
            'value' => array_key_exists('value', $seed) ? $this->serialise($seed['value'], $type) : null,
            'is_visible' => true,
            'iseditable' => true,
        ]);
        if ($row->wasRecentlyCreated && array_key_exists('value', $seed)) {
            $row->forceFill(['default_value' => $this->serialise($seed['value'], $type)])->save();
        }

        return $row;
    }

    /**
     * Seed of a key from config('platform.settings'). Keys are dotted, so they are read by exact
     * key — `config("platform.settings.{$key}")` would treat the dots as nesting and miss.
     *
     * @return array{value?: mixed, type?: string, label?: string}
     */
    private function seed(string $key): array
    {
        return (array) (config('platform.settings', [])[$key] ?? []);
    }

    /** Declared type: the row's own type, except that a seed type wins over the column default `string`. */
    private function typeFor(string $key, ?SystemSetting $row): string
    {
        $rowType = $row ? $this->typeOf($row->type) : null;
        $seedType = isset($this->seed($key)['type']) ? $this->typeOf($this->seed($key)['type']) : null;

        return $seedType !== null && ($rowType === null || $rowType === 'string') ? $seedType : ($rowType ?? 'string');
    }

    private function typeOf(?string $type): string
    {
        $type = strtolower((string) $type) ?: 'string';

        return self::TYPE_ALIASES[$type] ?? $type;
    }

    private function cast(mixed $value, string $type, mixed $default): mixed
    {
        if ($value === null || $value === '') {
            return $type === 'string' && $value === '' ? '' : $default;
        }

        return match ($type) {
            'int' => (int) $value,
            'decimal' => (float) $value,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'json' => is_array($value) ? $value : (json_decode((string) $value, true) ?? $default),
            'encrypted' => rescue(fn () => Crypt::decryptString((string) $value), $default, false),
            default => (string) $value,
        };
    }

    private function serialise(mixed $value, string $type): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0',
            'json' => is_string($value) ? $value : json_encode($value),
            'encrypted' => $value === '' ? '' : Crypt::encryptString((string) $value),
            default => is_scalar($value) ? (string) $value : json_encode($value),
        };
    }

    private function validationError(SystemSetting $row, string $type, mixed $value): ?string
    {
        $rules = match ($type) {
            'int' => ['nullable', 'integer'],
            'decimal' => ['nullable', 'numeric'],
            'bool' => ['nullable', 'boolean'],
            'json' => ['nullable'],
            default => ['nullable', 'string'],
        };
        if ($type === 'bool' && is_string($value)) {
            $value = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $value;
        }
        if ($type === 'json' && is_string($value) && $value !== '' && json_decode($value) === null) {
            return 'Must be valid JSON.';
        }
        if ($row->validation_rules) {
            $rules = array_merge($rules, explode('|', (string) $row->validation_rules));
        }
        $validator = Validator::make(['value' => $value], ['value' => $rules], [], ['value' => $row->label ?: $row->key]);

        return $validator->fails() ? implode(' ', $validator->errors()->all()) : null;
    }

    /** A declared seed (config/platform.php) wins over the caller's fallback, which only covers undeclared keys. */
    private function configDefault(string $key, mixed $default): mixed
    {
        $seed = $this->seed($key);

        return array_key_exists('value', $seed) ? $seed['value'] : $default;
    }
}
