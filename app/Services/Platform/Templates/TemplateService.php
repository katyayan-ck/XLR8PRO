<?php

declare(strict_types=1);

namespace App\Services\Platform\Templates;

use App\Models\Approval\ApprovalRequest;
use App\Models\Comms\CommTemplate;
use App\Models\Comms\CommTemplateVersion;
use App\Models\User;
use App\Services\Platform\Approval\ApprovalService;
use App\Services\Platform\Chat\ChatService;
use App\Services\Platform\Settings\SettingsService;
use App\Support\Result;
use Illuminate\Support\Facades\DB;

/**
 * Template Engine (FRS §12) — the only source of customer-facing copy and the only writer of
 * comm_template / comm_template_version. Runtime sends use the ACTIVE version only.
 *
 * Placeholders are `{{name}}`. In HTML bodies values are HTML-escaped (vars can never break out of
 * the template); in text / SMS / WhatsApp bodies they are inserted as plain text.
 */
final class TemplateService
{
    public const APPROVAL_TOPIC = 'COMMS.TEMPLATE';

    private const EDITABLE = ['subject', 'body_html', 'body_text', 'wa_components', 'variables', 'sample_vars', 'provider_template_id', 'dlt_entity_id', 'dlt_header'];

    public function __construct(private readonly SettingsService $settings, private readonly ChatService $chat) {}

    /**
     * ACTIVE version for code × channel × locale (TPL-01), falling back to en-IN unless strict.
     *
     * @return Result data: {template: CommTemplate, version: CommTemplateVersion, warnings: list<string>}
     */
    public function get(string $code, string $channel, ?string $locale = null): Result
    {
        $channel = strtoupper($channel);
        $locale = $locale ?: 'en-IN';
        $warnings = [];
        $template = CommTemplate::query()->with('active')->where('code', $code)->where('channel', $channel)->where('locale', $locale)->first();
        if ((! $template || ! $template->active) && $locale !== 'en-IN') {
            if ($this->settings->flag('templates.strict_locale')) {
                return Result::fail('TEMPLATE_NOT_ACTIVE', "No ACTIVE {$channel} template {$code} for {$locale}.");
            }
            $warnings[] = "Locale {$locale} missing for {$code}; used en-IN.";
            $template = CommTemplate::query()->with('active')->where('code', $code)->where('channel', $channel)->where('locale', 'en-IN')->first();
        }
        if (! $template || ! $template->active) {
            return Result::fail('TEMPLATE_NOT_ACTIVE', "No ACTIVE {$channel} template {$code}.");
        }

        return Result::ok(['template' => $template, 'version' => $template->active, 'warnings' => $warnings]);
    }

    /**
     * Render the ACTIVE version (TPL-02). Missing required vars and unknown placeholders are
     * errors (TPL-03); `missing` / `unknown` are returned for the caller or preview.
     *
     * @param  array<string, mixed>  $vars
     * @return Result data: {subject, html, text, wa_payload, missing, unknown, warnings, template_code, template_version, category, pii}
     */
    public function render(string $code, string $channel, array $vars = [], ?string $locale = null): Result
    {
        $found = $this->get($code, $channel, $locale);
        if (! $found->ok) {
            return $found;
        }

        return $this->renderVersion($found->get('version'), $vars, false, $found->get('warnings'));
    }

    /**
     * Render any version (preview: never fails on missing / unknown; they are highlighted).
     *
     * @param  array<string, mixed>  $vars
     * @param  list<string>  $warnings
     */
    public function renderVersion(CommTemplateVersion $version, array $vars, bool $preview = false, array $warnings = []): Result
    {
        $template = $version->template;
        $schema = collect((array) $version->variables)->keyBy('name');
        $vars = ($preview ? array_merge((array) $version->sample_vars, $vars) : $vars) + ['brand' => $this->settings->get('brand.name', 'BMPL')];
        $missing = $schema->filter(fn ($v) => ! empty($v['required']) && (! array_key_exists($v['name'], $vars) || $vars[$v['name']] === null || $vars[$v['name']] === ''))->keys()->values()->all();
        $unknown = [];

        $fill = function (?string $text, bool $html) use ($vars, $schema, $preview, &$unknown): ?string {
            if ($text === null) {
                return null;
            }

            return preg_replace_callback('/\{\{\s*([A-Za-z_][A-Za-z0-9_.]*)\s*\}\}/', function ($m) use ($vars, $schema, $html, $preview, &$unknown) {
                $name = $m[1];
                if (! array_key_exists($name, $vars) && ! $schema->has($name)) {
                    $unknown[] = $name;

                    return $preview ? '⟦'.$name.'?⟧' : $m[0];
                }
                $value = $vars[$name] ?? '';
                $value = is_scalar($value) ? (string) $value : json_encode($value);
                if ($value === '' && $preview) {
                    return '⟦'.$name.'⟧';
                }

                return $html ? e($value) : $value;
            }, $text);
        };

        $subject = $fill($version->subject, false);
        $htmlBody = $fill($version->body_html, true);
        $textBody = $fill($version->body_text, false);
        $wa = $template->channel === 'WHATSAPP' ? [
            'provider_template_id' => $version->provider_template_id,
            'components' => $version->wa_components,
            'params' => $schema->keys()->map(fn ($name) => (string) ($vars[$name] ?? ''))->all(),
            'body' => $textBody,
        ] : null;
        $unknown = array_values(array_unique($unknown));

        if (! $preview && ($missing !== [] || $unknown !== [])) {
            return Result::fail('RENDER_ERROR', trim(($missing ? 'Missing: '.implode(', ', $missing).'. ' : '').($unknown ? 'Unknown placeholder: '.implode(', ', $unknown).'.' : '')), compact('missing', 'unknown'));
        }

        return Result::ok([
            'subject' => $subject, 'html' => $htmlBody, 'text' => $textBody ?? ($htmlBody ? trim(strip_tags($htmlBody)) : null),
            'wa_payload' => $wa, 'missing' => $missing, 'unknown' => $unknown, 'warnings' => $warnings,
            'template_code' => $template->code, 'template_version' => $version->version, 'category' => $template->category,
            'dlt' => ['entity_id' => $version->dlt_entity_id, 'header' => $version->dlt_header, 'template_id' => $version->provider_template_id],
            'pii' => $schema->filter(fn ($v) => ! empty($v['pii']))->keys()->values()->all(),
        ]);
    }

    /**
     * Create / update the current draft of a family (TPL-04). ACTIVE versions are never touched: a
     * change forks a new DRAFT (copying the latest version when no draft exists).
     *
     * @param  array<string, mixed>  $payload  family fields (channel, name, category, locale, description) + version fields
     */
    public function saveDraft(string $code, array $payload, ?int $actorId = null): Result
    {
        $channel = strtoupper((string) ($payload['channel'] ?? ''));
        $locale = (string) ($payload['locale'] ?? 'en-IN');
        if (! preg_match('/^[a-z0-9][a-z0-9._\-]*$/', $code)) {
            return Result::fail('INVALID_CODE', 'Template codes are lower-case dotted keys, e.g. quote.customer.send.');
        }
        if (! in_array($channel, CommTemplate::CHANNELS, true)) {
            return Result::fail('INVALID_CHANNEL', 'Unknown channel.');
        }
        $category = strtoupper((string) ($payload['category'] ?? 'TRANSACTIONAL'));
        if (! in_array($category, CommTemplate::CATEGORIES, true)) {
            return Result::fail('INVALID_CATEGORY', 'Unknown category.');
        }
        if (isset($payload['variables']) && ($error = $this->variablesError($payload['variables'])) !== null) {
            return Result::fail('INVALID_VARIABLES', $error);
        }

        $version = DB::transaction(function () use ($code, $channel, $locale, $category, $payload) {
            $template = CommTemplate::query()->firstOrCreate(
                ['code' => $code, 'channel' => $channel, 'locale' => $locale],
                ['name' => $payload['name'] ?? $code, 'category' => $category, 'brand' => $payload['brand'] ?? 'BMPL', 'description' => $payload['description'] ?? null],
            );
            $template->fill(array_filter(['name' => $payload['name'] ?? null, 'category' => $category, 'description' => $payload['description'] ?? null], fn ($v) => $v !== null))->save();

            $draft = CommTemplateVersion::query()->where('template_id', $template->id)->where('status', 'DRAFT')->latest('version')->first();
            if (! $draft) {
                $base = CommTemplateVersion::query()->where('template_id', $template->id)->latest('version')->first();
                $draft = new CommTemplateVersion(['template_id' => $template->id, 'status' => 'DRAFT', 'version' => (int) ($base?->version ?? 0) + 1]);
                if ($base) {
                    $draft->fill($base->only(self::EDITABLE));
                }
            }
            $draft->fill(array_intersect_key($payload, array_flip(self::EDITABLE)));
            $draft->save();

            return $draft;
        });

        return Result::ok(['template_id' => $version->template_id, 'version_id' => $version->id, 'version' => $version->version]);
    }

    /**
     * Submit a draft for approval (TPL-05) on topic COMMS.TEMPLATE. When no rule covers the topic,
     * a UTL_TPL_ACTIVATE holder may approve directly with approveDirect() (DEC-064).
     */
    public function submit(int $versionId, ?int $actorId = null): Result
    {
        $actorId ??= $this->actor();
        $version = CommTemplateVersion::query()->with('template')->find($versionId);
        if (! $version || $version->status !== 'DRAFT') {
            return Result::fail('NOT_DRAFT', 'Only a DRAFT can be submitted.');
        }
        $check = $this->renderVersion($version, (array) $version->sample_vars, true);
        if ($check->get('unknown')) {
            return Result::fail('RENDER_ERROR', 'Unknown placeholder(s): '.implode(', ', $check->get('unknown')).'. Declare them as variables.');
        }
        $opened = app(ApprovalService::class)->open(['type' => 'TEMPLATE', 'id' => $version->template_id], self::APPROVAL_TOPIC, null,
            ['asked' => 1, 'value_type' => 'FLAG', 'remark' => "{$version->template->code} v{$version->version} ({$version->template->channel})"], $actorId);
        if (! $opened->ok) {
            return Result::fail($opened->code, $opened->message.($opened->code === 'NO_RULE' ? ' A template activator may approve it directly.' : ''));
        }
        $version->update(['status' => 'IN_REVIEW', 'approval_request_id' => $opened->get('id')]);
        if ($opened->get('status') === 'ACCEPTED') {
            $this->onApprovalAccepted((int) $opened->get('id'));
        }
        $this->chat->event($version->template, 'SUBMITTED', "v{$version->version} submitted for approval #{$opened->get('id')}", [], $actorId);

        return Result::ok(['approval_id' => $opened->get('id'), 'status' => $version->fresh()->status]);
    }

    /** Approval listener: an ACCEPTED COMMS.TEMPLATE request approves its version. */
    public function onApprovalAccepted(int $approvalRequestId): void
    {
        $request = ApprovalRequest::query()->find($approvalRequestId);
        if (! $request || $request->topic_code !== self::APPROVAL_TOPIC || $request->status !== ApprovalService::ACCEPTED || (float) $request->effective_value <= 0) {
            return;
        }
        $version = CommTemplateVersion::query()->with('template')->where('approval_request_id', $request->id)->where('status', 'IN_REVIEW')->first();
        if ($version) {
            $version->update(['status' => 'APPROVED', 'approved_by' => $request->effective_actor_id, 'approved_at' => now()]);
            $this->chat->event($version->template, 'APPROVED', "v{$version->version} approved (approval #{$request->id})", [], $request->effective_actor_id);
        }
    }

    /** Direct approval by a template activator when no approval rule covers COMMS.TEMPLATE (DEC-064). */
    public function approveDirect(int $versionId, ?int $actorId = null): Result
    {
        $actorId ??= $this->actor();
        if (! User::query()->find($actorId)?->can('UTL_TPL_ACTIVATE')) {
            return Result::fail('FORBIDDEN', 'Only a template activator may approve directly.');
        }
        $version = CommTemplateVersion::query()->with('template')->find($versionId);
        if (! $version || ! in_array($version->status, ['DRAFT', 'IN_REVIEW'], true)) {
            return Result::fail('INVALID_STATE', 'Only a DRAFT or IN_REVIEW version can be approved.');
        }
        $version->update(['status' => 'APPROVED', 'approved_by' => $actorId, 'approved_at' => now()]);
        $this->chat->event($version->template, 'APPROVED', "v{$version->version} approved directly", ['direct' => true], $actorId);

        return Result::ok();
    }

    /** Activate an APPROVED version (TPL-06); the previous ACTIVE becomes RETIRED (kept for replay). */
    public function activate(int $versionId, ?int $actorId = null): Result
    {
        $actorId ??= $this->actor();
        $version = CommTemplateVersion::query()->with('template')->find($versionId);
        if (! $version || $version->status !== 'APPROVED') {
            return Result::fail('NOT_APPROVED', 'Only an APPROVED version can be activated.');
        }
        DB::transaction(function () use ($version) {
            CommTemplateVersion::query()->where('template_id', $version->template_id)->where('status', 'ACTIVE')->update(['status' => 'RETIRED', 'retired_at' => now()]);
            $version->update(['status' => 'ACTIVE', 'activated_at' => now()]);
        });
        $this->chat->event($version->template, 'ACTIVATED', "v{$version->version} is now live", [], $actorId);

        return Result::ok(['version' => $version->version]);
    }

    /**
     * System-owned copy seeded by migrations (notify.generic, otp.sms …): created ACTIVE and marked
     * is_system. Idempotent — an existing family is left untouched (DEC-064).
     *
     * @param  array<string, mixed>  $payload  channel, category, name + version fields
     */
    public function seedSystem(string $code, array $payload): Result
    {
        $channel = strtoupper((string) $payload['channel']);
        if (CommTemplate::withTrashed()->where('code', $code)->where('channel', $channel)->where('locale', $payload['locale'] ?? 'en-IN')->exists()) {
            return Result::ok(['skipped' => true]);
        }
        $draft = $this->saveDraft($code, $payload);
        if (! $draft->ok) {
            return $draft;
        }
        CommTemplate::query()->whereKey($draft->get('template_id'))->update(['is_system' => true]);
        CommTemplateVersion::query()->whereKey($draft->get('version_id'))->update(['status' => 'ACTIVE', 'approved_at' => now(), 'activated_at' => now()]);

        return Result::ok(['template_id' => $draft->get('template_id')]);
    }

    /** Usage ledger (FRS §12.2). */
    public function recordUse(string $code, string $channel, int $version): void
    {
        CommTemplateVersion::query()->whereHas('template', fn ($q) => $q->where('code', $code)->where('channel', strtoupper($channel)))
            ->where('version', $version)->increment('usage_count', 1, ['last_used_at' => now()]);
    }

    /**
     * Export families with their latest version as JSON (promotion across environments).
     *
     * @param  list<string>  $codes
     * @return list<array<string, mixed>>
     */
    public function export(array $codes = []): array
    {
        return CommTemplate::query()->when($codes !== [], fn ($q) => $q->whereIn('code', $codes))->with('versions')->get()
            ->map(fn (CommTemplate $t) => $t->only(['code', 'channel', 'locale', 'brand', 'category', 'name', 'description'])
                + ['version' => $t->versions->first()?->only(self::EDITABLE)])->values()->all();
    }

    /**
     * Import as DRAFTs only (activation is per environment).
     *
     * @param  list<array<string, mixed>>  $items
     */
    public function import(array $items, ?int $actorId = null): Result
    {
        $done = 0;
        $errors = [];
        foreach ($items as $item) {
            $result = $this->saveDraft((string) ($item['code'] ?? ''), (array) ($item['version'] ?? []) + $item, $actorId);
            $result->ok ? $done++ : $errors[] = ($item['code'] ?? '?').': '.$result->message;
        }

        return Result::ok(['drafts' => $done, 'errors' => $errors]);
    }

    /** Diff of two versions' editable fields: field => [a, b] where they differ. */
    public function diff(int $versionA, int $versionB): array
    {
        $a = CommTemplateVersion::query()->findOrFail($versionA)->only(self::EDITABLE);
        $b = CommTemplateVersion::query()->findOrFail($versionB)->only(self::EDITABLE);
        $out = [];
        foreach (self::EDITABLE as $field) {
            if ($a[$field] != $b[$field]) {
                $out[$field] = [$a[$field], $b[$field]];
            }
        }

        return $out;
    }

    private function variablesError(mixed $variables): ?string
    {
        if (! is_array($variables)) {
            return 'Variables must be a list.';
        }
        foreach ($variables as $v) {
            if (! is_array($v) || ! preg_match('/^[A-Za-z_][A-Za-z0-9_.]*$/', (string) ($v['name'] ?? ''))) {
                return 'Every variable needs a name (letters, digits, _ and .).';
            }
        }

        return null;
    }

    private function actor(): ?int
    {
        return auth(backpack_guard_name())->id() ?? auth()->id();
    }
}
