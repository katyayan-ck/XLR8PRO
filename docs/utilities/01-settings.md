# 1. Settings

Runtime configuration and feature flags, changeable from **Utilities → Settings** without a deploy.
Service `App\Services\Platform\Settings\SettingsService` · facade `Settings` · helpers `setting()`, `feature()`.

## Concepts
- **Keys are dotted:** `sla.ticket.p1_hours`, `docs.max_upload_kb`, `quote.csd_enabled`.
- **Types:** `string`, `int`, `decimal`, `bool`, `json`, `encrypted` (stored with `Crypt`, shown masked, never logged),
  `image` (DEC-083: the file is media on the setting row, collection `setting_image`, public disk; the value is its URL).
- **Defaults** come from the seed pack in `config/platform.php` → `settings`. A declared seed always wins over
  the fallback you pass to `get()`. An undeclared key takes its type from the first value you `set()`.
- **Scopes:** a value can be overridden for a `COMPANY`, `BRANCH` or `DESK` (code). Most specific wins:
  DESK → BRANCH → COMPANY → global.
- Every write is validated, audited (old → new, who), clears the cache and fires `SettingsChanged`.

## API
| Call | Returns | Notes |
|---|---|---|
| `Settings::get($key, $default = null)` | mixed (typed) | global value |
| `Settings::getFor($scope, $key, $default = null)` | mixed | `$scope` = branch code, or `['desk' => 'D1', 'branch' => 'JPR', 'company' => 'BMPL']` |
| `Settings::flag($key, $default = false)` | bool | feature flags |
| `Settings::set($key, $value, $scopeType = null, $scopeCode = null, $actorId = null)` | Result | `READ_ONLY`, `INVALID_VALUE`, `INVALID_SCOPE` |
| `Settings::setImage($key, UploadedFile $file, $actorId = null)` | Result | image settings only (`NOT_IMAGE`, `UPLOAD_FAILED`); stores the file, sets the value to its URL |
| `Settings::reset($key)` | Result | back to `default_value` / seed |
| `Settings::clearScope($key, $scopeType, $scopeCode)` | Result | remove one override |

Data-scoping settings (DEC-071): `scope.enabled` (bool, master switch) and `scope.unassigned_rows` (`visible` / `hidden`) —
see `docs/domains/iam-auth.md` → "Data scoping".
| `Settings::adminList($search)` | array | what the admin screen shows |
| `setting($key, $default)` / `feature($key)` | helpers | same as `get` / `flag` |

Blade: `@setting('brand.name')` prints a value; `@feature('quote.csd_enabled') … @else … @endfeature`.

Artisan: `php artisan settings:cache` (warm, run on deploy), `php artisan settings:clear`.

## Use cases

**1. Read a limit in a service**
```php
$maxKb = (int) Settings::get('docs.max_upload_kb', 10240);
```

**2. Branch-specific value** (e.g. a longer SLA for one branch)
```php
Settings::set('sla.ticket.p2_hours', 12, 'BRANCH', 'JPR');
$hours = Settings::getFor('JPR', 'sla.ticket.p2_hours');        // 12 for Jaipur, global value elsewhere
$hours = Settings::getFor(['desk' => 'JPR-D2', 'branch' => 'JPR'], 'sla.ticket.p2_hours');
```

**3. Feature flag that hides part of a screen**
```blade
@feature('quote.csd_enabled')
    @include('admin.sales.quotation.partials.csd')
@endfeature
```
Turn it on or off on **Utilities → Settings**; no deploy.

**4. Add a new setting properly** — declare it in the seed pack so it has a type, label and default:
```php
// config/platform.php → 'settings'
'quote.max_discount_pct' => ['value' => 5, 'type' => 'decimal', 'label' => 'Max discount without approval (%)'],
```

**5. Secrets** (API keys, webhook secrets): type `encrypted`. The screen masks them; leaving the field blank keeps
the old value.
```php
Settings::set('whatsapp.webhook_secret', $secret);   // stored encrypted, audit shows ***
```

**6. An uploaded image** — the site logo `branding.logo` (DEC-083): Settings shows a preview + upload (route
`utils.settings.image`); "Use the built-in image" resets it. Read it with `site_logo_url($fallback)` — used by both
admin menu layouts (the logo links to the dashboard), the login page and the quotation / OTF prints (fallback: their
current image).
```php
Settings::setImage('branding.logo', $request->file('file'));
<img src="{{ site_logo_url('images/bikaner_logo.png') }}" alt="…">
```

**7. React to a change**
```php
Event::listen(\App\Events\Platform\SettingsChanged::class, fn ($e) => $e->key === 'display.date_format' && cache()->forget('x'));
```

## Screens & permissions
`/admin/utils/settings` — `UTL_SETTINGS_VIEW` to see, `UTL_SETTINGS_MANAGE` to change / reset / add overrides.

## Events & testing
`SettingsChanged` (key, old, new, scope, actor; values hidden for encrypted keys). See [15-testing.md](15-testing.md); every code is in
[16-reference.md](16-reference.md).

## Gotchas
- Never `config("platform.settings.{$key}")` — dotted keys are read as nesting. Use the service.
- Don't cache settings yourself; the service caches and busts on write.
- The site date format is `display.date_format` (+ `display.time_format`) — see [ui-kit.md](ui-kit.md).
