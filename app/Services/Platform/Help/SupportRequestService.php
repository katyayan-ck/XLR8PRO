<?php

namespace App\Services\Platform\Help;

use App\Models\Admin\UserScope;
use App\Models\User;
use App\Models\Utilities\Support\SupportRequest;
use App\Models\Utilities\Ticket\Ticket;
use App\Models\Utilities\Ticket\TicketPerson;
use App\Services\Platform\Notify\NotifyService;
use App\Services\Platform\Ticket\TicketService;
use App\Support\Result;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * "Still need help?" support requests (DEC-094, W16e; FRS help-and-support §5). A request opens a ticket (category
 * `SUP_*`, P2 when urgent else P3) owned by the `UTL_SUPP_ADMIN` holder with the fewest open support tickets (ties →
 * the one idle longest), and keeps a diagnostic zip in private storage: page / actions / network / errors from the
 * browser (`xl-diag.js`), the user's server trail, user / access facts and an optional screenshot — all text masked.
 * Only the requester, support admins and the ticket's assignees may download it; it is deleted after
 * `support.bundle_retention_days`. The support admin assigns `UTL_SUPP_EXEC` holders only.
 *
 * Example:
 *
 *   $result = $support->submit($user, ['category' => 'SUP_NOT_WORKING', 'urgent' => false, 'subject' => 'Bookings',
 *       'description' => 'The grid is empty', 'route' => 'sales.booking.index', 'snapshot' => [...], 'screenshot' => null]);
 *   // Result ok: ['id' => 7, 'ticket_id' => 31, 'number' => 'TCK/BKN/2026-27/00031']
 */
class SupportRequestService
{
    public const CATEGORIES = ['SUP_HOWTO', 'SUP_NOT_WORKING', 'SUP_WRONG_DATA', 'SUP_ACCESS', 'SUP_SUGGESTION'];

    private const DISK = 'local';

    public function __construct(
        private readonly TicketService $tickets,
        private readonly DiagnosticsService $diagnostics,
        private readonly NotifyService $notify,
    ) {}

    /**
     * Open the ticket and store the bundle.
     *
     * @param  array{category: string, urgent?: bool, subject: string, description: string, route?: ?string, diagnostics?: bool, snapshot?: ?array<string, mixed>, screenshot?: ?string}  $input
     */
    public function submit(User $user, array $input): Result
    {
        if (! in_array($input['category'], self::CATEGORIES, true)) {
            return Result::fail('SUPPORT_CATEGORY', __('utils.support.invalid_category'));
        }
        $route = isset($input['route']) && $input['route'] !== '' ? mb_substr((string) $input['route'], 0, 190) : null;
        $ownerId = $this->supportAdmin();
        $details = e(DiagnosticsService::mask((string) $input['description'])).($route ? '<br><small>Screen: '.e($route).'</small>' : '');

        $opened = $this->tickets->open([
            'category' => $input['category'],
            'priority' => ! empty($input['urgent']) ? 'P2' : 'P3',
            'title' => DiagnosticsService::mask((string) $input['subject']),
            'details' => $details,
            'requester_id' => $user->id,
            'owner_id' => $ownerId,
            'ref_type' => 'SUPPORT',
        ], $user->id);
        if (! $opened->ok) {
            return $opened;
        }
        $ticketId = (int) $opened->data['id'];

        $request = SupportRequest::query()->create(['ticket_id' => $ticketId, 'requester_id' => $user->id, 'category' => $input['category'], 'route' => $route]);
        if (! empty($input['diagnostics'])) {
            $this->storeBundle($request, $user, (array) ($input['snapshot'] ?? []), $input['screenshot'] ?? null);
            $this->tickets->remark($ticketId, $user->id, __('utils.support.bundle_remark', ['id' => $request->id]));
        }
        if ($ownerId !== null) {
            $this->notify->to($ownerId)->kind('N')->about('TICKET', $ticketId)->actor($user->id)
                ->title(__('utils.support.owner_notification', ['number' => $opened->data['number']]))->send();
        }

        return Result::ok(['id' => $request->id, 'ticket_id' => $ticketId, 'number' => $opened->data['number']]);
    }

    /** The `UTL_SUPP_ADMIN` holder with the fewest open support tickets; ties → the longest without a new one. */
    public function supportAdmin(): ?int
    {
        $admins = $this->holders('UTL_SUPP_ADMIN');
        if ($admins === []) {
            return null;
        }
        $support = fn () => Ticket::query()->whereIn('owner_id', $admins)->where('category', 'like', 'SUP\_%');
        $open = $support()->whereIn('status', TicketService::OPEN)->selectRaw('owner_id, COUNT(*) AS n')->groupBy('owner_id')->pluck('n', 'owner_id');
        $last = $support()->selectRaw('owner_id, MAX(created_at) AS last_at')->groupBy('owner_id')->pluck('last_at', 'owner_id');

        usort($admins, fn (int $a, int $b) => [(int) ($open[$a] ?? 0), (string) ($last[$a] ?? '')] <=> [(int) ($open[$b] ?? 0), (string) ($last[$b] ?? '')]);

        return $admins[0];
    }

    /**
     * Assign support executives (only `UTL_SUPP_EXEC` holders) to a support ticket; the ticket routine checks that the
     * actor may manage it (owner or service desk).
     *
     * @param  list<int>  $executiveIds
     */
    public function assign(SupportRequest $request, array $executiveIds, User $actor): Result
    {
        if (! $actor->can('UTL_SUPP_ADMIN')) {
            return Result::fail('FORBIDDEN', __('utils.support.only_admin_assigns'));
        }
        $allowed = $this->holders('UTL_SUPP_EXEC');
        $executiveIds = array_values(array_unique(array_map('intval', $executiveIds)));
        if (array_diff($executiveIds, $allowed) !== []) {
            return Result::fail('SUPPORT_NOT_EXECUTIVE', __('utils.support.not_executive'));
        }
        $ticket = Ticket::query()->find($request->ticket_id);
        if ($ticket === null) {
            return Result::fail('NOT_FOUND', __('utils.support.ticket_missing'));
        }

        return $this->tickets->update($ticket->id, ['owner_id' => $ticket->owner_id ?? $actor->id, 'assignees' => $executiveIds], $actor->id);
    }

    /** Requester, any support admin, or an assignee of the request's ticket — and only while the zip is kept. */
    public function canDownload(SupportRequest $request, User $user): bool
    {
        if ((int) $request->requester_id === (int) $user->id || $user->can('UTL_SUPP_ADMIN')) {
            return true;
        }

        return $request->ticket_id !== null && TicketPerson::query()->where('ticket_id', $request->ticket_id)
            ->where('user_id', $user->id)->where('role', TicketService::ASSIGNEE)->exists();
    }

    /** Absolute path of the stored zip, or null when there is none / it was purged. */
    public function bundleFile(SupportRequest $request): ?string
    {
        return $request->bundle_path && Storage::disk(self::DISK)->exists($request->bundle_path)
            ? Storage::disk(self::DISK)->path($request->bundle_path) : null;
    }

    /** Deletes zips older than `support.bundle_retention_days` (the request and ticket stay). @return int zips removed */
    public function purge(): int
    {
        $days = max(1, (int) setting('support.bundle_retention_days', 90));
        $purged = 0;
        SupportRequest::query()->whereNotNull('bundle_path')->where('created_at', '<', now()->subDays($days))
            ->chunkById(200, function ($requests) use (&$purged) {
                foreach ($requests as $request) {
                    Storage::disk(self::DISK)->deleteDirectory('support/'.$request->id);
                    $request->update(['bundle_path' => null, 'bundle_bytes' => null, 'purged_at' => now()]);
                    $purged++;
                }
            });

        return $purged;
    }

    /** @return list<int> active users holding the permission (role or direct) */
    public function holders(string $permission): array
    {
        return User::permission($permission)->where('is_active', 1)->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
    }

    /**
     * @param  array<string, mixed>  $snapshot  the browser's `XL.diag.snapshot()`
     */
    private function storeBundle(SupportRequest $request, User $user, array $snapshot, ?string $screenshot): void
    {
        $dir = 'support/'.$request->id;
        $relative = $dir.'/diagnostics-'.$request->id.'.zip';
        $disk = Storage::disk(self::DISK);
        $disk->makeDirectory($dir);

        $zip = new ZipArchive;
        if ($zip->open($disk->path($relative), ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            Log::warning('Support bundle could not be created', ['support_request' => $request->id]);

            return;
        }
        $trail = $this->diagnostics->trail($user->id);
        $files = [
            'page.json' => $snapshot['page'] ?? [],
            'actions.json' => $snapshot['actions'] ?? [],
            'network.json' => $snapshot['network'] ?? [],
            'errors.json' => $snapshot['errors'] ?? [],
            'server.json' => ['requests' => $trail, 'log' => $this->logLines(array_values(array_filter(array_column($trail, 'ref'))))],
            'user.json' => $this->userFacts($user),
        ];
        foreach ($files as $name => $data) {
            $zip->addFromString($name, DiagnosticsService::mask((string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)));
        }
        if (($image = $this->screenshot($screenshot)) !== null) {
            $zip->addFromString('screenshot.'.$image['ext'], $image['bytes']);
        }
        $zip->close();

        $request->update(['bundle_path' => $relative, 'bundle_bytes' => (int) $disk->size($relative)]);
    }

    /** @return array{ext: string, bytes: string}|null a PNG / JPEG data URL within the size limit */
    private function screenshot(?string $dataUrl): ?array
    {
        if ($dataUrl === null || ! preg_match('#^data:image/(png|jpeg);base64,([A-Za-z0-9+/=]+)$#', $dataUrl, $m)) {
            return null;
        }
        $bytes = base64_decode($m[2], true);
        $limit = max(256, (int) setting('support.max_screenshot_kb', 4096)) * 1024;
        $signature = $m[1] === 'png' ? "\x89PNG" : "\xFF\xD8\xFF";
        if ($bytes === false || strlen($bytes) > $limit || ! str_starts_with($bytes, $signature)) {
            return null;
        }

        return ['ext' => $m[1] === 'png' ? 'png' : 'jpg', 'bytes' => $bytes];
    }

    /** @return array<string, mixed> who the user is and what they may see (DEC-071 scopes) */
    private function userFacts(User $user): array
    {
        $permissions = $user->getAllPermissions()->pluck('name')->sort()->values()->all();

        return [
            'id' => $user->id,
            'name' => $user->getAttribute('display_name'),
            'username' => $user->getAttribute('username'),
            'employee_code' => $user->getAttribute('employee_code'),
            'designation' => $user->getAttribute('primary_designation'),
            'roles' => $user->getRoleNames()->values()->all(),
            'superadmin' => $user->isSuperAdmin(),
            'permission_count' => count($permissions),
            'permissions' => $permissions,
            'data_scopes' => UserScope::query()->where('user_id', $user->id)->where('is_active', 1)->get(['scope_type', 'scope_code'])->toArray(),
            'last_login_at' => $user->getAttribute('last_login_at')?->toIso8601String(),
        ];
    }

    /**
     * Today's log lines that carry one of these error references (at most 20 each, masked).
     *
     * @param  list<string>  $refs
     * @return array<string, list<string>>
     */
    private function logLines(array $refs): array
    {
        $file = storage_path('logs/laravel.log');
        if ($refs === [] || ! is_readable($file)) {
            return [];
        }
        $size = (int) filesize($file);
        $handle = fopen($file, 'r');
        if ($handle === false) {
            return [];
        }
        fseek($handle, max(0, $size - 2 * 1024 * 1024));   // the last 2 MB is enough for recent errors
        $tail = (string) stream_get_contents($handle);
        fclose($handle);
        $out = [];
        foreach (array_unique($refs) as $ref) {
            $lines = array_values(array_filter(explode("\n", $tail), fn (string $line) => str_contains($line, (string) $ref)));
            $out[$ref] = array_map(fn (string $l) => DiagnosticsService::mask(mb_substr($l, 0, 2000)), array_slice($lines, -20));
        }

        return $out;
    }
}
