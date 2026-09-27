<?php

declare(strict_types=1);

namespace App\Services\Platform\Comms\Drivers;

use App\Services\Platform\Settings\SettingsService;
use InvalidArgumentException;

/**
 * Active driver per channel from Settings (`mail.driver`, `sms.driver`, `whatsapp.driver`,
 * `telephony.driver`). Adding a vendor = one class + one entry here + a Settings value.
 */
final class DriverRegistry
{
    /** @var array<string, array<string, class-string|string>> channel => driver name => class ('sandbox' = SandboxDriver) */
    private const DRIVERS = [
        'EMAIL' => ['laravel' => LaravelMailDriver::class, 'log' => 'sandbox'],
        'SMS' => ['sandbox' => 'sandbox'],
        'WHATSAPP' => ['sandbox' => 'sandbox'],
        'TELEPHONY' => ['sandbox' => SandboxTelephonyDriver::class],
    ];

    private const SETTING = ['EMAIL' => 'mail.driver', 'SMS' => 'sms.driver', 'WHATSAPP' => 'whatsapp.driver', 'TELEPHONY' => 'telephony.driver'];

    public function __construct(private readonly SettingsService $settings) {}

    public function for(string $channel, ?string $name = null): ChannelDriver|TelephonyDriver
    {
        $channel = strtoupper($channel);
        $name = strtolower((string) ($name ?: $this->settings->get(self::SETTING[$channel] ?? '', 'sandbox')));
        $class = self::DRIVERS[$channel][$name] ?? null;
        if ($class === null) {
            throw new InvalidArgumentException("No {$channel} driver named {$name}.");
        }

        return $class === 'sandbox' ? new SandboxDriver($channel) : app($class);
    }

    /** Failover driver for SMS (FRS SMS-06), or null. */
    public function failover(string $channel): ?ChannelDriver
    {
        $name = strtolower((string) $this->settings->get(strtolower($channel).'.failover_driver', ''));
        if ($name === '' || ! isset(self::DRIVERS[strtoupper($channel)][$name])) {
            return null;
        }
        $driver = $this->for($channel, $name);

        return $driver instanceof ChannelDriver ? $driver : null;
    }

    /** @return list<string> driver names available for a channel */
    public function available(string $channel): array
    {
        return array_keys(self::DRIVERS[strtoupper($channel)] ?? []);
    }
}
