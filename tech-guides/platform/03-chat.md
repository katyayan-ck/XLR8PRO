# 3. Chat — history & remarks

One conversation per record: **events** written by the system (immutable) and **remarks** written by people
(editable for a few minutes, soft-deleted, replies, files, @mentions, followers).
Service `App\Services\Platform\Chat\ChatService` · facade `Chat` · model trait `HasCommunications`.

## Concepts
- **EVENT** = something happened (`CREATED`, `STATUS_CHANGED`, `APPROVAL_REQUESTED`, …) — action codes from KeyValue
  `ENTITY_ACTIONS`. **REMARK** = a person wrote something.
- **Access:** `Chat::canView($model, $userId)` = model `chatCanView()` if defined, else the entity permission from
  `config/platform.php`. Posting additionally checks `chatCanRemark()` (e.g. task snoopers read only).
- **Internal remarks** are visible to their author and to `UTL_CHAT_MODERATE` holders only.
- **@username** in a remark sends that user an `M` notification; **followers** (subscribers) get new remarks.
- Files on remarks are stored through Docs (collection `thread-docs`) and an `ATTACHED` event is added.

## API
| Call | Returns |
|---|---|
| `Chat::event($model, 'STATUS_CHANGED', 'Moved to KYC', $meta = [], $actorId = null)` | `CommThread` |
| `Chat::remark($model, $body, ?UploadedFile $file, ?$parentId, $internal = false, $actorId = null)` | Result `{id}` — `FORBIDDEN`, `EMPTY`, `INVALID_PARENT` |
| `Chat::editRemark($threadId, $body)` | Result — author only, within `chat.edit_window_minutes` (`EDIT_WINDOW_CLOSED`) |
| `Chat::deleteRemark($threadId)` | Result — author or `UTL_CHAT_MODERATE` |
| `Chat::timeline($model, $viewerId)` | `['events' => [...], 'remarks' => [...], 'combined' => [...]]` |
| `Chat::events($model)` / `Chat::remarks($model)` | lists |
| `Chat::subscribe($model, $userId)` / `unsubscribe` / `isSubscribed` | follow a record |
| `Chat::resolve('BOOKING', $id)` | the model for a ref |
| `Chat::entityForApi($type, $id, $userId)` | the model for an **API** reference — entity code or short class name only (never a class path), loaded through the model (data scope), must pass `canView()`; throws `ModelNotFoundException` (404) / `AuthorizationException` (403). Used by the v1 history / document endpoints (BUG-182) |
| `Chat::refType($model)` | `'BOOKING'` etc. |

Timeline entries: `id, kind, action, action_label, actor_name, time_human, time_iso, title, body, files[], parent_id,
is_internal, removed, edited, can_edit, can_delete`.

Trait on your model (`use HasCommunications;`): `$model->recordEvent('CREATED', 'Quote created', $meta = [], $body = null)`
(`$body` = the detail line under the summary),
`$model->addRemark('text', $file)`, `$model->history()`, `$model->commMaster`.

Components: `<x-chat.thread :model=… title=… filter="combined|events|remarks" :composer="true|false" />` and the
composer alone `<x-chat.composer :model=… :parent-id=… :allow-internal="true" />` (the internal-remark checkbox only
shows when allowed). `Chat::eventOnMaster($master, …)` writes on a conversation you already hold, with a body and a
parent.

## Use cases

**1. Log every status change of a record**
```php
$booking->update(['status' => 'KYC_DONE']);
Chat::event($booking, 'STATUS_CHANGED', "Status {$from} → KYC_DONE", ['from' => $from, 'to' => 'KYC_DONE']);
```

**2. Show the timeline + composer on any screen**
```blade
<x-chat.thread :model="$booking" title="History" />
{{-- read only: --}}
<x-chat.thread :model="$booking" :composer="false" />
```

**3. Restrict who may read / write a record's conversation** (model methods)
```php
public function chatCanView(int $userId): bool   { return $this->consultant_user_id === $userId || User::find($userId)?->can('SLS_BKNG_VIEW'); }
public function chatCanRemark(int $userId): bool { return $this->status !== 'CANCELLED'; }
```

**4. Remark with a file from code** (e.g. an import job leaves a note)
```php
Chat::remark($enquiry, 'Imported from DMS; 3 fields corrected', null, null, false, $systemUserId);
```

**5. Let a user follow a record**
```php
Chat::subscribe($quote, backpack_user()->id);    // they now get "New remark by …" messages
```

**6. System reply under a person's remark** (e.g. an integration answering a question)
```php
Chat::eventOnMaster(Chat::master($booking), 'UPDATED', 'DMS number assigned', 'DMS-88213', ['dms' => 'DMS-88213'], $remarkId, $systemUserId);
```

**7. Internal note only its author and moderators see**
```php
Chat::remark($quote, 'Customer is price-shopping at the other dealer', null, null, internal: true);
```

**8. Only the events, e.g. an audit panel**
```blade
<x-chat.thread :model="$booking" filter="events" :composer="false" title="Audit trail" />
```

## Events & testing
`ChatEntryAdded` for every entry. Assert with `Chat::events($model)` / `Chat::timeline($model, $viewerId)`: see
[15-testing.md](15-testing.md). Codes: [16-reference.md](16-reference.md).

## Screens & API
Component posts go to `/admin/utils/chat/remark` (and `…/subscribe`, `PUT/DELETE …/remark/{id}`).
Mobile v1: `/api/v1/history/{type}/{id}` (adapter `EntityHistoryService`).

## Gotchas
- Never write `CommMaster` / `CommThread` directly. `EntityHistoryService` / `addHistory()` are legacy adapters.
- Use a registered entity type; unregistered models still work for events but won't get deep links.
- Event summaries are plain text; put structured data in `$meta`.
