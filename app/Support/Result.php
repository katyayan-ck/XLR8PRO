<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Structured outcome of a platform-service call (FRS availability law 7): never a bare `false`.
 * `code` is a stable machine code (`OK`, `FORBIDDEN_TRANSITION`, `SESSION_CLOSED`…).
 *
 * @implements Arrayable<string, mixed>
 */
final class Result implements Arrayable, JsonSerializable
{
    /** @param  array<string, mixed>  $data */
    private function __construct(
        public readonly bool $ok,
        public readonly string $code,
        public readonly string $message,
        public readonly array $data = [],
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function ok(array $data = [], string $message = 'OK', string $code = 'OK'): self
    {
        return new self(true, $code, $message, $data);
    }

    /** @param  array<string, mixed>  $data */
    public static function fail(string $code, string $message, array $data = []): self
    {
        return new self(false, $code, $message, $data);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->data, $key, $default);
    }

    /** @return array{ok: bool, code: string, message: string, data: array<string, mixed>} */
    public function toArray(): array
    {
        return ['ok' => $this->ok, 'code' => $this->code, 'message' => $this->message, 'data' => $this->data];
    }

    /** @return array{ok: bool, code: string, message: string, data: array<string, mixed>} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
