<?php

declare(strict_types=1);

namespace App\Services\Platform\Notify;

use App\Events\Platform\NotificationSent;
use App\Jobs\Platform\SendPushNotification;
use App\Models\User;
use App\Models\Utilities\Noty\Alert;
use App\Models\Utilities\Noty\Notification;
use App\Models\Utilities\Noty\NotificationDispatch;
use App\Models\Utilities\Noty\NotificationsMaster;
use App\Services\Platform\Comms\CommsRouter;
use App\Services\Platform\Settings\SettingsService;
use App\Services\Platform\Templates\TemplateService;
use App\Support\Result;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Notification service (FRS §2) — the only writer of inbox rows. Kinds N and M live in
 * noty_notification (column `kind`), kind A in noty_alert; one dispatch master per send.
 * In-app rows are written synchronously (inside the caller's transaction); push and the other
 * channels are queued. Callers never insert into notification tables or call Firebase.
 */
final class NotifyService
{
    public const READ = 'READ';

    public const UNREAD = 'UNREAD';

    public const ARCHIVE = 'ARCHIVE';

    public function __construct(private readonly SettingsService $settings) {}

    public function to(int $userId): PendingNotification
    {
        return new PendingNotification($this, Audience::users($userId));
    }

    /** @param  list<int>  $userIds */
    public function toMany(array $userIds): PendingNotification
    {
        return new PendingNotification($this, Audience::users($userIds));
    }

    public function audience(Audience $audience): PendingNotification
    {
        return new PendingNotification($this, $audience);
    }

    public function dispatch(PendingNotification $n): Result
    {
        if (! in_array($n->kind, config('platform.notify.kinds'), true)) {
            return Result::fail('INVALID_KIND', "Unknown notification kind {$n->kind}.");
        }

        if ($n->idempotencyKey !== null && ($existing = NotificationDispatch::query()->where('idempotency_key', $n->idempotencyKey)->first())) {
            return Result::ok(['dispatch_id' => $existing->id, 'sent' => 0, 'duplicate' => true]);
        }

        $actorId = $n->actorId ?? auth(backpack_guard_name())->id() ?? auth()->id();
        $audience = $n->audience;
        if (! $n->notifySelf && $actorId) {
            $audience->except($actorId);
        }
        $recipients = $audience->resolve();
        if ($recipients === []) {
            Log::info('[Notify] empty audience', ['title' => $n->title, 'ref' => [$n->refType, $n->refId]]);

            return Result::ok(['dispatch_id' => null, 'sent' => 0]);
        }

        [$title, $body] = $this->render($n, $actorId);
        $channels = $n->channels ?: array_fill_keys(config('platform.notify.default_channels'), true);
        $deepLink = $this->deepLink($n->refType, $n->refId);

        $dispatch = DB::transaction(function () use ($n, $title, $body, $channels, $actorId, $recipients, $deepLink) {
            $dispatch = NotificationDispatch::create([
                'kind' => $n->kind, 'title' => $title, 'body' => $body, 'ref_type' => $n->refType, 'ref_id' => $n->refId,
                'channels' => $channels, 'data' => $n->data, 'template' => $n->template, 'idempotency_key' => $n->idempotencyKey,
                'sender_id' => $actorId, 'recipient_count' => count($recipients),
            ]);

            $payload = ['kind' => $n->kind, 'type' => $n->refType ?? 'SYSTEM', 'id' => $n->refId, 'deep_link' => $deepLink] + $n->data;
            $rows = [];
            foreach ($recipients as $userId) {
                $common = [
                    'user_id' => $userId, 'sender_id' => $actorId, 'title' => $title, 'description' => $body ?? '',
                    'reference_type' => $n->refType, 'reference_id' => $n->refId, 'payload' => $payload,
                    'metadata' => $n->data ?: null, 'dispatch_id' => $dispatch->id,
                ];
                $rows[] = $n->kind === 'A'
                    ? ['alert', Alert::create($common + ['severity' => (string) ($n->data['severity'] ?? 'warning')])->id]
                    : ['notification', Notification::create($common + ['type' => $n->refType ?? 'SYSTEM', 'kind' => $n->kind, 'priority' => $n->priority, 'category' => $n->data['category'] ?? null])->id];
            }
            $dispatch->setRelation('inboxRows', collect($rows));

            return $dispatch;
        });

        foreach (array_unique($recipients) as $userId) {
            NotificationsMaster::query()->firstOrCreate(['user_id' => $userId])->recalculate();
        }

        if (isset($channels['FCM']) && ! $this->inQuietHours()) {
            foreach ($dispatch->getRelation('inboxRows') as [$table, $id]) {
                SendPushNotification::dispatch($table, $id)->afterCommit();
            }
        }
        $this->fanOut($n, $channels, $recipients, $title, $body);

        NotificationSent::dispatch($dispatch->id, $n->kind, $n->refType, $n->refId, $recipients);

        return Result::ok(['dispatch_id' => $dispatch->id, 'sent' => count($recipients)]);
    }

    /**
     * Inbox counters (FRS NOT-06).
     *
     * @return array{notifications: array{total: int, unread: int, read: int}, alerts: array{total: int, unread: int, read: int}, messages: array{total: int, unread: int, read: int}}
     */
    public function counts(int $userId): array
    {
        $count = function ($query): array {
            $total = (clone $query)->count();
            $unread = (clone $query)->where('is_read', false)->count();

            return ['total' => $total, 'unread' => $unread, 'read' => $total - $unread];
        };
        $live = fn ($model) => $model::query()->where('user_id', $userId)->whereNull('archived_at');

        return [
            'notifications' => $count($live(Notification::class)->where('kind', 'N')),
            'alerts' => $count($live(Alert::class)),
            'messages' => $count($live(Notification::class)->where('kind', 'M')),
        ];
    }

    /**
     * Inbox page, newest first (FRS NOT-07). State: UNREAD | READ | ARCHIVE | ALL.
     */
    public function list(int $userId, string $kind = 'N', string $state = 'ALL', int $perPage = 20): LengthAwarePaginator
    {
        $kind = strtoupper($kind);
        $query = $kind === 'A' ? Alert::query() : Notification::query()->where('kind', $kind);
        $query->where('user_id', $userId);

        match (strtoupper($state)) {
            self::UNREAD => $query->whereNull('archived_at')->where('is_read', false),
            self::READ => $query->whereNull('archived_at')->where('is_read', true),
            self::ARCHIVE => $query->whereNotNull('archived_at'),
            default => $query->whereNull('archived_at'),
        };

        return $query->latest('id')->paginate($perPage);
    }

    /** Mark one inbox row (FRS NOT-08). */
    public function mark(int $userId, string $kind, int $inboxId, string $state): Result
    {
        $model = strtoupper($kind) === 'A' ? Alert::class : Notification::class;
        $row = $model::query()->where('user_id', $userId)->find($inboxId);
        if (! $row) {
            return Result::fail('NOT_FOUND', 'Notification not found.');
        }
        $this->applyState($row, strtoupper($state));
        NotificationsMaster::query()->firstOrCreate(['user_id' => $userId])->recalculate();

        return Result::ok();
    }

    /** Mark everything of one kind (or all kinds) read. */
    public function markAll(int $userId, ?string $kind = null): Result
    {
        $kind = $kind ? strtoupper($kind) : null;
        if ($kind === null || $kind === 'A') {
            Alert::query()->where('user_id', $userId)->where('is_read', false)->update(['is_read' => true, 'read_at' => now()]);
        }
        if ($kind !== 'A') {
            Notification::query()->where('user_id', $userId)->where('is_read', false)
                ->when($kind, fn ($q) => $q->where('kind', $kind))->update(['is_read' => true, 'read_at' => now()]);
        }
        NotificationsMaster::query()->firstOrCreate(['user_id' => $userId])->recalculate();

        return Result::ok();
    }

    /** The user opened the record: mark its notifications read or archived (FRS NOT-09). */
    public function purgeFor(int $userId, string $refType, int $refId, string $state = self::READ): Result
    {
        $changes = strtoupper($state) === self::ARCHIVE ? ['archived_at' => now(), 'is_read' => true, 'read_at' => now()] : ['is_read' => true, 'read_at' => now()];
        $n = 0;
        foreach ([Notification::class, Alert::class] as $model) {
            $n += $model::query()->where('user_id', $userId)->where('reference_type', strtoupper($refType))->where('reference_id', $refId)
                ->where(fn ($q) => $q->where('is_read', false)->orWhereNull('archived_at'))->update($changes);
        }
        NotificationsMaster::query()->firstOrCreate(['user_id' => $userId])->recalculate();

        return Result::ok(['updated' => $n]);
    }

    public function deepLink(?string $refType, ?int $refId): ?string
    {
        $pattern = $refType ? config("platform.entities.{$refType}.url") : null;

        return $pattern ? backpack_url(str_replace('{id}', (string) $refId, $pattern)) : null;
    }

    /** @return array{0: string, 1: ?string} title and body with {placeholders} filled; never raw HTML (NOT-12). */
    private function render(PendingNotification $n, ?int $actorId): array
    {
        $title = $n->title;
        $body = $n->body;

        if ($n->template !== null && class_exists(TemplateService::class)) {
            $rendered = app(TemplateService::class)->render($n->template, 'PUSH', $n->vars);
            if ($rendered->ok) {
                $title = (string) ($rendered->get('subject') ?: $title);
                $body = (string) ($rendered->get('text') ?: $body);
            }
        }

        $vars = ['id' => $n->refId, 'brand' => $this->settings->get('brand.name', 'BMPL'),
            'actor' => $actorId ? (User::query()->find($actorId)?->display_name ?? '') : 'System'] + $n->vars;
        $fill = fn (?string $text) => $text === null ? null : strip_tags(preg_replace_callback('/\{(\w+)\}/', fn ($m) => array_key_exists($m[1], $vars) && is_scalar($vars[$m[1]]) ? (string) $vars[$m[1]] : $m[0], $text));

        return [$fill($title) ?? '', $fill($body)];
    }

    private function applyState(Notification|Alert $row, string $state): void
    {
        $changes = match ($state) {
            // READ / UNREAD also restore an archived row to the inbox
            self::READ => ['is_read' => true, 'read_at' => $row->read_at ?? now(), 'archived_at' => null],
            self::UNREAD => ['is_read' => false, 'read_at' => null, 'archived_at' => null],
            self::ARCHIVE => ['archived_at' => now(), 'is_read' => true, 'read_at' => $row->read_at ?? now()],
            default => [],
        };
        $row->forceFill($changes)->save();
    }

    private function inQuietHours(): bool
    {
        $window = (string) $this->settings->get('notify.quiet_hours', '');
        if (! preg_match('/^(\d{2}:\d{2})-(\d{2}:\d{2})$/', $window, $m)) {
            return false;
        }
        $now = now()->format('H:i');

        return $m[1] <= $m[2] ? ($now >= $m[1] && $now < $m[2]) : ($now >= $m[1] || $now < $m[2]);
    }

    /**
     * EMAIL / SMS / WHATSAPP channels: forwarded to the comms plane (FRS Part B) with the channel's
     * options; recipients are resolved to person codes by the channel services.
     *
     * @param  array<string, mixed>  $channels
     * @param  list<int>  $recipients
     */
    private function fanOut(PendingNotification $n, array $channels, array $recipients, string $title, ?string $body): void
    {
        $router = CommsRouter::class;
        if (! class_exists($router)) {
            return;
        }
        foreach (['EMAIL', 'SMS', 'WHATSAPP'] as $channel) {
            if (! isset($channels[$channel])) {
                continue;
            }
            $options = is_array($channels[$channel]) ? $channels[$channel] : [];
            app($router)->fromNotify($channel, $recipients, $options + [
                'subject' => $title, 'text' => $body, 'ref_type' => $n->refType, 'ref_id' => $n->refId,
                'idempotency_key' => $n->idempotencyKey ? "{$n->idempotencyKey}.{$channel}" : null,
            ]);
        }
    }
}
