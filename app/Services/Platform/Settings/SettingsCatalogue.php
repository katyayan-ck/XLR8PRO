<?php

declare(strict_types=1);

namespace App\Services\Platform\Settings;

use App\Models\User;
use App\Support\Result;
use Illuminate\Support\Facades\Validator;

/**
 * The categorised Settings interface (DEC-091, to-do W13): which tabs a user may open, the keys of each section with
 * their current values, and saving a section. The layout comes from `config/settings_ui.php`; values, types, defaults
 * and every write stay with SettingsService (the only writer). Keys stored or seeded but not in the layout are shown in
 * the "Other" tab, except the superseded legacy keys in `settings_ui.hidden`.
 */
final class SettingsCatalogue
{
    /** @var array<string, array<string, mixed>>|null key => admin-list row */
    private ?array $rows = null;

    public function __construct(private readonly SettingsService $settings) {}

    /**
     * Tabs the user may open, with their sections and resolved keys (label, type, value, input, overrides …).
     *
     * @return array<string, array{label: string, icon: string, sections: array<string, array{label: string, keys: list<array<string, mixed>>}>}>
     */
    public function tabsFor(User $user): array
    {
        $tabs = [];
        foreach ($this->layout() as $tab => $def) {
            if (! $this->allowed($user, $def['permissions'])) {
                continue;
            }
            $sections = [];
            foreach ($def['sections'] as $section => $sectionDef) {
                $sections[$section] = ['label' => $sectionDef['label'], 'keys' => array_map(
                    fn (string $key) => $this->describe($key, $sectionDef['keys'][$key]),
                    array_keys($sectionDef['keys']),
                )];
            }
            $tabs[$tab] = ['label' => $def['label'], 'icon' => $def['icon'], 'sections' => $sections];
        }

        return $tabs;
    }

    /** True when the user may open at least one tab. */
    public function canOpen(User $user): bool
    {
        return $this->tabsFor($user) !== [];
    }

    /** The tab a key is shown in ('other' for unlisted keys). */
    public function tabOf(string $key): string
    {
        foreach (config('settings_ui.tabs', []) as $tab => $def) {
            foreach ($def['sections'] as $section) {
                if (array_key_exists($key, $section['keys'])) {
                    return $tab;
                }
            }
        }

        return 'other';
    }

    /** True when the user may change this key (its tab's permissions). */
    public function canEdit(User $user, string $key): bool
    {
        $def = $this->layout()[$this->tabOf($key)] ?? null;

        return $def !== null && $this->allowed($user, $def['permissions']);
    }

    /**
     * Validate and save one section's values. Only changed values are written; a blank secret keeps the stored one;
     * images and read-only keys are skipped (images upload separately).
     *
     * @param  array<string, mixed>  $input  key => submitted value (switches: '1' / '0')
     * @return Result ok with data {saved: list<string>}; failure with data {errors: array<string, list<string>>}
     */
    public function saveSection(User $user, string $tab, string $section, array $input): Result
    {
        $def = $this->layout()[$tab] ?? null;
        $keys = $def['sections'][$section]['keys'] ?? null;
        if ($def === null || $keys === null) {
            return Result::fail('SETTINGS_NOT_FOUND', __('errors.SETTINGS_NOT_FOUND'));
        }
        if (! $this->allowed($user, $def['permissions'])) {
            return Result::fail('AUTH_FORBIDDEN', __('errors.AUTH_FORBIDDEN'));
        }

        $values = [];
        $rules = [];
        $names = [];
        foreach ($keys as $key => $opt) {
            $field = str_replace('.', '__', $key);
            $input_type = $opt['input'];
            if (in_array($input_type, ['image', 'readonly'], true)) {
                continue;
            }
            $value = $input[$key] ?? ($input_type === 'switch' ? '0' : null);
            if ($input_type === 'secret' && ($value === null || $value === '')) {
                continue;   // blank = keep the stored secret
            }
            $values[$field] = $value;
            $rules[$field] = $this->rules($opt);
            $names[$field] = $this->describe($key, $opt)['label'];
        }

        $validator = Validator::make($values, $rules, [], $names);
        if ($validator->fails()) {
            $errors = [];
            foreach ($validator->errors()->messages() as $field => $messages) {
                $errors[str_replace('__', '.', $field)] = $messages;
            }

            return Result::fail('VALIDATION_FAILED', __('errors.VALIDATION_FAILED'), ['errors' => $errors]);
        }

        $saved = [];
        foreach ($values as $field => $value) {
            $key = str_replace('__', '.', $field);
            $opt = $keys[$key];
            $current = $this->describe($key, $opt)['value'];
            $new = match ($opt['input']) {
                'switch' => $value === '1' || $value === 1 || $value === true || $value === 'on',
                'number' => (int) $value,
                'json' => json_decode((string) $value, true),
                default => (string) ($value ?? ''),
            };
            if ($opt['input'] !== 'secret' && $new === $current) {
                continue;
            }
            $result = $this->settings->set($key, $opt['input'] === 'json' ? (string) $value : $new, null, null, (int) $user->id);
            if (! $result->ok) {
                return Result::fail($result->code, $result->message, ['errors' => [$key => [$result->message]]]);
            }
            $saved[] = $key;
        }
        $this->rows = null;

        return Result::ok(['saved' => $saved]);
    }

    /** @return array<string, array{label: string, icon: string, permissions: list<string>, sections: array<string, array{label: string, keys: array<string, array<string, mixed>>}>}> */
    private function layout(): array
    {
        $tabs = config('settings_ui.tabs', []);
        $listed = [];
        foreach ($tabs as $def) {
            foreach ($def['sections'] as $section) {
                $listed += array_flip(array_keys($section['keys']));
            }
        }

        $hidden = array_flip((array) config('settings_ui.hidden', []));
        $other = [];
        foreach (array_keys($this->rows()) as $key) {
            if (! isset($listed[$key]) && ! isset($hidden[$key])) {
                $type = $this->rows()[$key]['type'];
                $other[$key] = ['input' => match ($type) {
                    'bool' => 'switch', 'int', 'decimal' => 'number', 'encrypted' => 'secret', 'image' => 'image', 'json' => 'json',
                    default => 'text',
                }];
            }
        }
        if ($other !== []) {
            $tabs['other'] = config('settings_ui.other') + ['sections' => ['other' => ['label' => 'Other settings', 'keys' => $other]]];
        }

        return $tabs;
    }

    /** @return array<string, mixed> */
    private function describe(string $key, array $opt): array
    {
        $row = $this->rows()[$key] ?? ['key' => $key, 'label' => $key, 'type' => 'string', 'value' => null, 'editable' => true, 'updated_at' => null, 'overrides' => []];

        return $row + ['input' => $opt['input'], 'options' => $opt['options'] ?? [], 'min' => $opt['min'] ?? null,
            'max' => $opt['max'] ?? null, 'help' => $opt['help'] ?? null,
            'default_image' => isset($opt['default']) ? asset($opt['default']) : null];
    }

    /** @return list<mixed> */
    private function rules(array $opt): array
    {
        return match ($opt['input']) {
            'switch' => ['required', 'in:0,1,on,true'],
            'number' => array_filter(['nullable', 'integer', isset($opt['min']) ? 'min:'.$opt['min'] : null, isset($opt['max']) ? 'max:'.$opt['max'] : null]),
            'url' => ['nullable', 'url', 'max:255'],
            'email' => ['nullable', 'email', 'max:190'],
            'select' => ['nullable', 'in:'.implode(',', array_keys($opt['options'] ?? []))],
            'json' => ['nullable', 'json'],
            'textarea' => ['nullable', 'string', 'max:5000'],
            'secret' => ['string', 'max:500'],
            default => ['nullable', 'string', 'max:500'],
        };
    }

    /** @return array<string, array<string, mixed>> */
    private function rows(): array
    {
        if ($this->rows === null) {
            $this->rows = [];
            foreach ($this->settings->adminList() as $group) {
                foreach ($group as $row) {
                    $this->rows[$row['key']] = $row;
                }
            }
        }

        return $this->rows;
    }

    /** @param  list<string>  $permissions */
    private function allowed(User $user, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }
}
