<?php

declare(strict_types=1);

namespace App\Services\Platform\Comms;

use App\Models\Admin\Person;
use App\Models\User;
use App\Services\IdentifierService;
use App\Services\PersonService;
use Illuminate\Support\Facades\DB;

/**
 * Identity resolution, consent, suppression and masking for the comms wrappers (FRS Part B laws
 * 4 and 7). Recipients are a person_code, a user id or a raw address.
 */
final class ContactService
{
    public const CHANNELS = ['EMAIL', 'SMS', 'WHATSAPP', 'CALL'];

    public function __construct(private readonly IdentifierService $identifiers) {}

    /**
     * @return array{address: ?string, person_code: ?string}
     */
    public function resolve(int|string $recipient, string $channel): array
    {
        $channel = strtoupper($channel);
        $personCode = null;
        if (is_int($recipient) || ctype_digit((string) $recipient) && strlen((string) $recipient) < 8) {
            $personCode = User::query()->whereKey((int) $recipient)->value('person_code');
        } elseif (! str_contains((string) $recipient, '@') && ! preg_match('/^\+?[\d\s\-]{10,15}$/', (string) $recipient)) {
            $personCode = (string) $recipient;
        }

        if ($personCode !== null) {
            $person = Person::query()->where('person_code', $personCode)->first();
            $address = $channel === 'EMAIL' ? $person?->primary_email : $person?->primary_mobile;

            return ['address' => $this->normalise($address, $channel), 'person_code' => $person ? $personCode : null];
        }

        $address = $this->normalise((string) $recipient, $channel);

        return ['address' => $address, 'person_code' => $address ? $this->personByAddress($address, $channel) : null];
    }

    /** E.164 for phones (+91 default), lower-case for email; null when invalid. */
    public function normalise(?string $address, string $channel): ?string
    {
        if ($address === null || trim($address) === '') {
            return null;
        }
        if (strtoupper($channel) === 'EMAIL') {
            $email = strtolower(trim($address));

            return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
        }
        $digits = preg_replace('/\D/', '', $address);
        if (str_starts_with(trim($address), '+') && strlen($digits) >= 11 && strlen($digits) <= 15 && ! str_starts_with($digits, '91')) {
            return '+'.$digits;
        }
        $mobile = $this->identifiers->cleanMobile($address);

        return $mobile && preg_match('/^[6-9]\d{9}$/', $mobile) ? '+91'.$mobile : null;
    }

    public function personByAddress(string $address, string $channel): ?string
    {
        $criteria = strtoupper($channel) === 'EMAIL' ? ['email' => $address] : ['mobile' => substr(preg_replace('/\D/', '', $address), -10)];

        return PersonService::find($criteria)?->person_code;
    }

    /** Consent: explicit row wins; no row = not consented (promotional sends need a yes). */
    public function consented(?string $personCode, string $channel): bool
    {
        if ($personCode === null) {
            return false;
        }

        return (bool) DB::table('xlr8_comm_consent')->where('person_code', $personCode)->where('channel', strtoupper($channel))->value('granted');
    }

    public function hasOptedOut(?string $personCode, string $channel): bool
    {
        return $personCode !== null && DB::table('xlr8_comm_consent')->where('person_code', $personCode)->where('channel', strtoupper($channel))->where('granted', false)->exists();
    }

    public function setConsent(string $personCode, string $channel, bool $granted, string $source, ?int $actorId = null): void
    {
        DB::table('xlr8_comm_consent')->updateOrInsert(
            ['person_code' => $personCode, 'channel' => strtoupper($channel)],
            ['granted' => $granted, 'source' => $source, 'changed_by' => $actorId, 'updated_at' => now(), 'created_at' => now()],
        );
    }

    public function suppressed(string $channel, string $address): bool
    {
        return DB::table('xlr8_comm_suppression')->where('channel', strtoupper($channel))->where('address', strtolower($address))->exists();
    }

    public function suppress(string $channel, string $address, string $reason, ?string $note = null): void
    {
        DB::table('xlr8_comm_suppression')->updateOrInsert(
            ['channel' => strtoupper($channel), 'address' => strtolower($address)],
            ['reason' => $reason, 'note' => $note, 'updated_at' => now(), 'created_at' => now()],
        );
    }

    /** "+9198XXXXXX12" / "ra***@example.com" — what logs and browsers see. */
    public function mask(?string $address): string
    {
        if ($address === null || $address === '') {
            return '';
        }
        if (str_contains($address, '@')) {
            [$local, $domain] = explode('@', $address, 2);

            return mb_substr($local, 0, 2).'***@'.$domain;
        }

        return substr($address, 0, 5).str_repeat('X', max(0, strlen($address) - 7)).substr($address, -2);
    }

    /** Inside a HH:MM-HH:MM window (Asia/Kolkata)? Blank window = always. */
    public function withinWindow(string $window): bool
    {
        if (! preg_match('/^(\d{1,2}):(\d{2})\s*-\s*(\d{1,2}):(\d{2})$/', trim($window), $m)) {
            return true;
        }
        $now = now('Asia/Kolkata');
        $minutes = $now->hour * 60 + $now->minute;
        $from = (int) $m[1] * 60 + (int) $m[2];
        $to = (int) $m[3] * 60 + (int) $m[4];

        return $from <= $to ? $minutes >= $from && $minutes < $to : $minutes >= $from || $minutes < $to;
    }
}
