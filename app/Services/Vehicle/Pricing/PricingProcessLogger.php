<?php

namespace App\Services\Vehicle\Pricing;

use Illuminate\Support\Facades\Log;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

/**
 * Path: app/Services/Vehicle/Pricing/PricingProcessLogger.php
 *
 * Gated by config('pricing.process_log') / PRICING_PROCESS_LOG.
 * File: {process_log_dir}/pricing_process_{session_N|run_timestamp}.log
 */
class PricingProcessLogger
{
    protected ?Logger $channel = null;
    protected string $contextKey;

    public function __construct(?int $sessionId = null)
    {
        $this->contextKey = $sessionId
            ? ('session_' . $sessionId)
            : ('run_' . now()->format('Ymd_His'));
    }

    public static function enabled(): bool
    {
        $cfg = config('pricing.process_log');
        if ($cfg === null) {
            return filter_var(env('PRICING_PROCESS_LOG', false), FILTER_VALIDATE_BOOLEAN);
        }
        return (bool) $cfg;
    }

    public function forSession(int $sessionId): self
    {
        $this->contextKey = 'session_' . $sessionId;
        $this->channel = null;
        return $this;
    }

    public function info(string $message, array $context = []): void { $this->write('info', $message, $context); }
    public function warning(string $message, array $context = []): void { $this->write('warning', $message, $context); }
    public function error(string $message, array $context = []): void { $this->write('error', $message, $context); }

    public function debug(string $message, array $context = []): void
    {
        if (! self::enabled()) {
            return;
        }
        $this->write('debug', $message, $context);
    }

    public function dumpSheetPreview(string $sheetTitle, string $sheetCode, array $matrix, int $rows = 12): void
    {
        if (! self::enabled()) {
            return;
        }
        $preview = [];
        $limit = min(count($matrix), $rows);
        for ($i = 0; $i < $limit; $i++) {
            $cells = [];
            foreach (array_slice($matrix[$i] ?? [], 0, 20) as $c) {
                $cells[] = ($c === null || $c === '') ? '' : mb_substr(trim((string) $c), 0, 40);
            }
            $preview['row_' . $i] = $cells;
        }
        $this->debug("Sheet preview [{$sheetTitle}] code={$sheetCode}", $preview);
    }

    protected function write(string $level, string $message, array $context): void
    {
        $payload = array_merge(['process' => $this->contextKey], $context);
        if (in_array($level, ['info', 'warning', 'error'], true)) {
            Log::{$level}('[PricingProcess] ' . $message, $payload);
        }
        if (! self::enabled()) {
            return;
        }
        $this->channel()->{$level}($message, $payload);
    }

    protected function channel(): Logger
    {
        if ($this->channel) {
            return $this->channel;
        }
        $dir = (string) config('pricing.process_log_dir', storage_path('logs/pricing'));
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $file = $dir . DIRECTORY_SEPARATOR . 'pricing_process_' . $this->contextKey . '.log';
        $logger = new Logger('pricing_process');
        $logger->pushHandler(new StreamHandler($file, Logger::DEBUG));
        $this->channel = $logger;
        return $this->channel;
    }
}
