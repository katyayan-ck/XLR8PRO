<?php

declare(strict_types=1);

namespace App\Services\Platform\Notify;

use App\Support\Result;

/**
 * Fluent notification being built (FRS §2.6):
 *
 *   Notify::to($userId)->kind('N')->about('QUOTE', $qid)->title('Quote #{id} assigned')->body($remark)->send();
 *   Notify::audience(Audience::designation('SM'))->kind('M')->title('Price list live')->send();
 */
final class PendingNotification
{
    public string $kind = 'N';

    public string $title = '';

    public ?string $body = null;

    public ?string $refType = null;

    public ?int $refId = null;

    public ?string $template = null;

    /** @var array<string, mixed> */
    public array $vars = [];

    /** @var array<string, mixed> channel => true | options */
    public array $channels = [];

    /** @var array<string, mixed> */
    public array $data = [];

    public ?string $idempotencyKey = null;

    public bool $notifySelf = false;

    public string $priority = 'normal';

    public ?int $actorId = null;

    public function __construct(private readonly NotifyService $service, public Audience $audience) {}

    /** N (notification), A (alert) or M (message). */
    public function kind(string $kind): self
    {
        $this->kind = strtoupper($kind);

        return $this;
    }

    /** The record the notification opens (FRS §2.4 ref_type catalogue). */
    public function about(string $refType, ?int $refId = null): self
    {
        $this->refType = strtoupper($refType);
        $this->refId = $refId;

        return $this;
    }

    public function title(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function body(?string $body): self
    {
        $this->body = $body;

        return $this;
    }

    /** @param  array<string, mixed>  $vars */
    public function template(string $code, array $vars = []): self
    {
        $this->template = $code;
        $this->vars = $vars;

        return $this;
    }

    /** @param  array<string, mixed>  $vars */
    public function vars(array $vars): self
    {
        $this->vars = array_merge($this->vars, $vars);

        return $this;
    }

    /**
     * Channels: a list (`['INAPP', 'FCM']`) or a map with per-channel options (FRS Part B):
     * `['INAPP' => true, 'EMAIL' => ['template' => 'quote.customer.send', 'cc' => [...]]]`.
     *
     * @param  list<string>|array<string, mixed>  $channels
     */
    public function channels(array $channels): self
    {
        $map = [];
        foreach ($channels as $key => $value) {
            is_int($key) ? $map[strtoupper((string) $value)] = true : $map[strtoupper($key)] = $value;
        }
        $this->channels = $map;

        return $this;
    }

    /** @param  array<string, mixed>  $data */
    public function data(array $data): self
    {
        $this->data = array_merge($this->data, $data);

        return $this;
    }

    public function priority(string $priority): self
    {
        $this->priority = $priority;

        return $this;
    }

    public function idempotency(string $key): self
    {
        $this->idempotencyKey = $key;

        return $this;
    }

    public function notifySelf(bool $notifySelf = true): self
    {
        $this->notifySelf = $notifySelf;

        return $this;
    }

    public function actor(?int $actorId): self
    {
        $this->actorId = $actorId;

        return $this;
    }

    public function send(): Result
    {
        return $this->service->dispatch($this);
    }
}
