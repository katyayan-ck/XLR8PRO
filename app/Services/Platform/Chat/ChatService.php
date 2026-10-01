<?php

declare(strict_types=1);

namespace App\Services\Platform\Chat;

use App\Events\Platform\ChatEntryAdded;
use App\Models\User;
use App\Models\Utilities\CommHistory\CommMaster;
use App\Models\Utilities\CommHistory\CommSubscription;
use App\Models\Utilities\CommHistory\CommThread;
use App\Services\KeywordValueService;
use App\Services\Platform\Docs\DocsService;
use App\Services\Platform\Notify\Audience;
use App\Services\Platform\Notify\NotifyService;
use App\Services\Platform\Settings\SettingsService;
use App\Support\Result;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Chat / communication history (FRS §3): one conversation per record, EVENT rows written by the
 * system (immutable) and REMARK rows written by people (editable within a window, soft-deleted).
 * The only writer of comm_master / comm_thread. A model may restrict access by defining
 * `chatCanView(int $userId): bool` and `chatCanRemark(int $userId): bool` (Task snoopers).
 */
final class ChatService
{
    public const EVENT = 'EVENT';

    public const REMARK = 'REMARK';

    public function __construct(
        private readonly NotifyService $notify,
        private readonly SettingsService $settings,
    ) {}

    public function master(Model $model, ?string $title = null): CommMaster
    {
        return CommMaster::query()->firstOrCreate(
            ['entityable_type' => $model::class, 'entityable_id' => $model->getKey()],
            ['title' => $title ?? ($this->refType($model) ?? class_basename($model)).' #'.$model->getKey()],
        );
    }

    /**
     * Immutable system entry (FRS CHAT-02). `$action` is an ENTITY_ACTIONS code (CREATED,
     * STATUS_CHANGED, ASSIGNED, APPROVED, COUNTERED, ATTACHED…).
     *
     * @param  array<string, mixed>  $meta
     */
    public function event(Model $model, string $action, string $summary, array $meta = [], ?int $actorId = null): CommThread
    {
        return $this->write($this->master($model), self::EVENT, strtoupper($action), $summary, null, $meta, null, $actorId);
    }

    /** Event on a master directly (legacy EntityHistoryService adapter). @param array<string, mixed> $meta */
    public function eventOnMaster(CommMaster $master, string $action, string $summary, ?string $body = null, array $meta = [], ?int $parentId = null, ?int $actorId = null): CommThread
    {
        return $this->write($master, self::EVENT, strtoupper($action), $summary, $body, $meta, $parentId, $actorId);
    }

    /**
     * Human remark, optionally a reply and with a file (FRS CHAT-03, CHAT-07, CHAT-08).
     */
    public function remark(Model $model, string $body, ?UploadedFile $file = null, ?int $parentId = null, bool $internal = false, ?int $actorId = null): Result
    {
        $actorId ??= $this->actor();
        if ($actorId && method_exists($model, 'chatCanRemark') && ! $model->chatCanRemark($actorId)) {
            return Result::fail('FORBIDDEN', 'You may read this conversation but not post in it.');
        }
        $body = trim(strip_tags($body));
        if ($body === '' && ! $file) {
            return Result::fail('EMPTY', 'Write a remark or attach a file.');
        }

        $master = $this->master($model);
        if ($parentId !== null && ! CommThread::query()->where('comm_master_id', $master->id)->whereKey($parentId)->exists()) {
            return Result::fail('INVALID_PARENT', 'The remark you are replying to is not in this conversation.');
        }

        $thread = DB::transaction(function () use ($model, $master, $body, $file, $parentId, $internal, $actorId) {
            $files = [];
            if ($file) {
                $doc = app(DocsService::class)->attach($model, $file, 'thread-docs', ['title' => $file->getClientOriginalName()], $actorId, withEvent: false);
                if ($doc->ok) {
                    $files[] = $doc->data;
                }
            }
            $thread = $this->write($master, self::REMARK, 'REMARKED', mb_substr($body, 0, 120), $body, ['files' => $files], $parentId, $actorId, $internal);
            if ($files !== []) {
                $this->write($master, self::EVENT, 'ATTACHED', 'File attached: '.$files[0]['name'], null, ['doc_id' => $files[0]['id']], null, $actorId);
            }

            return $thread;
        });

        $this->notifyRemark($model, $thread, $body, $actorId);

        return Result::ok(['id' => $thread->id]);
    }

    public function editRemark(int $threadId, string $body, ?int $actorId = null): Result
    {
        $actorId ??= $this->actor();
        $thread = CommThread::query()->find($threadId);
        if (! $thread || $thread->kind !== self::REMARK) {
            return Result::fail('NOT_FOUND', 'Remark not found.');
        }
        if ((int) $thread->actor_id !== (int) $actorId) {
            return Result::fail('FORBIDDEN', 'Only the author may edit a remark.');
        }
        if ($thread->created_at->diffInMinutes(now()) > (int) $this->settings->get('chat.edit_window_minutes', 15)) {
            return Result::fail('EDIT_WINDOW_CLOSED', 'The edit window for this remark has closed.');
        }
        $thread->forceFill(['body' => trim(strip_tags($body)), 'title' => mb_substr(trim(strip_tags($body)), 0, 120), 'edited_at' => now()])->save();

        return Result::ok(['id' => $thread->id]);
    }

    /** Soft delete; the timeline shows "remark removed" (FRS CHAT-09). */
    public function deleteRemark(int $threadId, ?int $actorId = null): Result
    {
        $actorId ??= $this->actor();
        $thread = CommThread::query()->find($threadId);
        if (! $thread || $thread->kind !== self::REMARK) {
            return Result::fail('NOT_FOUND', 'Remark not found.');
        }
        $moderator = $actorId && User::query()->find($actorId)?->can('UTL_CHAT_MODERATE');
        if ((int) $thread->actor_id !== (int) $actorId && ! $moderator) {
            return Result::fail('FORBIDDEN', 'Only the author may remove a remark.');
        }
        $thread->delete();

        return Result::ok();
    }

    /**
     * Timeline (FRS CHAT-04/05): events, remarks and both combined, oldest first.
     *
     * @return array{events: list<array<string, mixed>>, remarks: list<array<string, mixed>>, combined: list<array<string, mixed>>}
     */
    public function timeline(Model $model, ?int $viewerId = null): array
    {
        $viewerId ??= $this->actor();
        $master = CommMaster::query()->where('entityable_type', $model::class)->where('entityable_id', $model->getKey())->first();
        if (! $master) {
            return ['events' => [], 'remarks' => [], 'combined' => []];
        }

        $window = (int) $this->settings->get('chat.edit_window_minutes', 15);
        $moderator = $viewerId !== null && (bool) User::query()->find($viewerId)?->can('UTL_CHAT_MODERATE');
        $rows = CommThread::withTrashed()->where('comm_master_id', $master->id)->with('actor:id,username,person_code')->orderBy('created_at')->orderBy('id')->get()
            ->filter(fn (CommThread $t) => ! $t->is_internal || (int) $t->actor_id === (int) $viewerId || $moderator)
            ->map(fn (CommThread $t) => $this->present($t, $viewerId, $window, $moderator))
            ->values();

        return [
            'events' => $rows->where('kind', self::EVENT)->values()->all(),
            'remarks' => $rows->where('kind', self::REMARK)->values()->all(),
            'combined' => $rows->all(),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function events(Model $model): array
    {
        return $this->timeline($model)['events'];
    }

    /** @return list<array<string, mixed>> */
    public function remarks(Model $model): array
    {
        return $this->timeline($model)['remarks'];
    }

    /** Follow the conversation: new remarks notify the subscriber (FRS CHAT-10). */
    public function subscribe(Model $model, int $userId): Result
    {
        CommSubscription::query()->firstOrCreate(['comm_master_id' => $this->master($model)->id, 'user_id' => $userId]);

        return Result::ok();
    }

    public function unsubscribe(Model $model, int $userId): Result
    {
        CommSubscription::query()->where('comm_master_id', $this->master($model)->id)->where('user_id', $userId)->delete();

        return Result::ok();
    }

    public function isSubscribed(Model $model, int $userId): bool
    {
        $master = CommMaster::query()->where('entityable_type', $model::class)->where('entityable_id', $model->getKey())->first();

        return $master !== null && CommSubscription::query()->where('comm_master_id', $master->id)->where('user_id', $userId)->exists();
    }

    /** The record behind an entity type code and id, or null when the type or record is unknown. */
    public function resolve(string $refType, int $refId): ?Model
    {
        $class = config('platform.entities.'.strtoupper($refType).'.model');

        return $class && class_exists($class) ? $class::query()->find($refId) : null;
    }

    /**
     * The record behind an API entity reference, for the signed-in app user (DEC-095 #2, BUG-182). Accepts only the entity
     * codes of `config('platform.entities')` (`BOOKING`, `ENQUIRY`, …) or the short class names the app sends today
     * (`Booking`, `Enquiry`, `Quotation` …, mapped to their code) — never an arbitrary class. The record is loaded through
     * its model (data scope applies) and must pass `canView()`.
     *
     *   $booking = Chat::entityForApi('BOOKING', 42, $userId);
     *
     * @throws ModelNotFoundException<Model> unknown type or record (404)
     * @throws AuthorizationException the user may not see the record (403)
     */
    public function entityForApi(string $type, int $id, int $userId): Model
    {
        $code = $this->entityCode($type);
        $class = $code !== null ? config("platform.entities.{$code}.model") : null;
        if (! $class || ! class_exists($class)) {
            throw (new ModelNotFoundException)->setModel(Model::class, [$id]);
        }
        $model = $class::query()->findOrFail($id);
        if (! $this->canView($model, $userId)) {
            throw new AuthorizationException;
        }

        return $model;
    }

    /** Entity code for an API type: the code itself, or a registered model's short class name. */
    private function entityCode(string $type): ?string
    {
        $entities = (array) config('platform.entities', []);
        if (isset($entities[strtoupper($type)]) && ($entities[strtoupper($type)]['model'] ?? null)) {
            return strtoupper($type);
        }
        foreach ($entities as $code => $entity) {
            if (($entity['model'] ?? null) && strcasecmp(class_basename($entity['model']), $type) === 0) {
                return $code;
            }
        }

        return null;
    }

    /** May the user see this record's conversation: the model's own rule, else the entity's view permission. */
    public function canView(Model $model, int $userId): bool
    {
        if (method_exists($model, 'chatCanView')) {
            return (bool) $model->chatCanView($userId);
        }
        $user = User::query()->find($userId);
        $permission = config('platform.entities.'.$this->refType($model).'.permission');

        return $user !== null && ($user->can('UTL_CHAT_MODERATE') || ($permission && $user->can($permission)));
    }

    /** Entity type code of a model (config/platform.php), e.g. QUOTE, TASK. */
    public function refType(Model|string $model): ?string
    {
        $class = is_string($model) ? $model : $model::class;
        foreach (config('platform.entities', []) as $code => $entity) {
            if (($entity['model'] ?? null) === $class) {
                return $code;
            }
        }

        return null;
    }

    /** @param  array<string, mixed>  $meta */
    private function write(CommMaster $master, string $kind, string $action, string $title, ?string $body, array $meta, ?int $parentId, ?int $actorId, bool $internal = false): CommThread
    {
        $actorId ??= $this->actor();
        $actionId = rescue(fn () => KeywordValueService::getValueId(config('platform.chat.actions_keyword'), $action), null, false);

        $thread = new CommThread([
            'comm_master_id' => $master->id,
            'parent_id' => $parentId,
            'actor_id' => $actorId ?? 0,
            'action_id' => $actionId,
            'title' => mb_substr($title, 0, 250),
            'body' => $body,
            'extra_data' => ['action' => $action] + $meta,
        ]);
        $thread->kind = $kind;
        $thread->is_internal = $internal;
        $thread->save();

        ChatEntryAdded::dispatch($master->id, $thread->id, $kind, $action);

        return $thread;
    }

    /** @return array<string, mixed> */
    private function present(CommThread $t, ?int $viewerId, int $window, bool $moderator = false): array
    {
        $extra = (array) $t->extra_data;
        $removed = $t->trashed();

        return [
            'id' => $t->id,
            'kind' => $t->kind ?: self::EVENT,
            'action' => $extra['action'] ?? null,
            'action_label' => ucwords(strtolower(str_replace('_', ' ', (string) ($extra['action'] ?? '')))),
            'actor_id' => $t->actor_id,
            'actor_name' => $t->actor?->display_name ?? 'System',
            'time_human' => $t->created_at?->diffForHumans(),
            'time_iso' => $t->created_at?->toIso8601String(),
            'title' => $removed ? 'Remark removed' : $t->title,
            'body' => $removed ? null : $t->body,
            'files' => $removed ? [] : ($extra['files'] ?? []),
            'parent_id' => $t->parent_id,
            'is_internal' => (bool) $t->is_internal,
            'removed' => $removed,
            'edited' => $t->edited_at !== null,
            'can_edit' => ! $removed && $t->kind === self::REMARK && (int) $t->actor_id === (int) $viewerId && $t->created_at?->diffInMinutes(now()) <= $window,
            'can_delete' => ! $removed && $t->kind === self::REMARK && ((int) $t->actor_id === (int) $viewerId || $moderator),
        ];
    }

    private function notifyRemark(Model $model, CommThread $thread, string $body, ?int $actorId): void
    {
        $refType = $this->refType($model) ?? 'CHAT';
        $refId = (int) $model->getKey();

        // @mentions → a message to each mentioned user (FRS CHAT-08)
        preg_match_all('/@([A-Za-z0-9._\-]+)/', $body, $m);
        $mentioned = $m[1] === [] ? [] : User::query()->whereIn('username', array_map('strtolower', $m[1]))->pluck('id')->all();
        if ($mentioned !== []) {
            $this->notify->toMany($mentioned)->kind('M')->about($refType, $refId)->actor($actorId)
                ->title('{actor} mentioned you')->body(mb_substr($body, 0, 200))->data(['chat_thread_id' => $thread->id])->send();
        }

        // subscribers (except the author and those already mentioned)
        $this->notify->audience(Audience::watchersOf($refType, $refId)->except($mentioned))->kind('M')->about($refType, $refId)->actor($actorId)
            ->title('New remark by {actor}')->body(mb_substr($body, 0, 200))->data(['chat_thread_id' => $thread->id])->send();
    }

    private function actor(): ?int
    {
        return auth(backpack_guard_name())->id() ?? auth()->id();
    }
}
