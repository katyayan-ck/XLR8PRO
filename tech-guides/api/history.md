# API — Record history (chat timeline) (module UTL, Chat utility)

**Base URL:** `{{base_url}}/api/v1/history`. **Postman:** [`postman/history.postman_collection.json`](postman/history.postman_collection.json).
**Source:** `app/Http/Controllers/Api/V1/EntityHistoryController.php` → `App\Services\EntityHistoryService` (adapter over the
Chat utility, `tech-guides/platform/03-chat.md`).
**Auth:** Bearer token + a live device session. **Envelope / common errors:** [index.md](index.md#errors-common-to-every-endpoint-dec-085).

> ⚠ **BUG-182 (open, owner decision D3):** `{entityType}` is resolved as `App\Models\{entityType}` from the URL and the
> caller's right to see that record is not checked — any signed-in user can read or append to any record's history.
> Admin screens use the access-checked Chat utility (`ChatService::canView()`).

**`entityType`:** the model class under `App\Models`, URL-encoded (e.g. `CRM%5CEnquiry` for `CRM\Enquiry`,
`Module%5CBooking%5CBooking`). **`entityId`:** the record id.

## GET /{entityType}/{entityId} — the record's history
**200:** `data` = the root threads, each with `children`, `media`, `actor`, `action`:

```json
{ "http_status": 200, "success": true, "code": "S200", "message": "History retrieved", "timestamp": "…",
  "data": [ { "id": 5501, "comm_master_id": 812, "parent_id": null, "actor_id": 40, "action_id": 3,
              "title": "Booking created", "body": null, "extra_data": {}, "is_internal": false, "edited_at": null,
              "created_at": "…", "children": [], "media": [], "actor": { "id": 40, "username": "…" },
              "action": { "id": 3, "…": "…" } } ] }
```
Each item is a `CommThread` row (`comm_master_id`, `parent_id`, `actor_id`, `action_id`, `title`, `body`, `extra_data`,
`is_internal`, `edited_at`) with its replies (`children`), files (`media`), the author (`actor`, a user) and the action
type (`action`).

- **404:** `History not found` (the record has no history yet) or unknown record.
- **500:** unknown `entityType` (not a model class) — logged with a reference.

## POST /{entityType}/{entityId}/thread — add a comment / event (multipart/form-data)

| Field | Validation |
|---|---|
| `action_key` | required, string (e.g. `REMARK`) |
| `title` | string, nullable |
| `message` | string, nullable |
| `parent_id` | integer, nullable — reply to a thread |
| `attachments[]` | files (optional) |

- **201:** `data` = the new thread; message `Comment added`. A history master is created for the record when missing.
- **404:** unknown record or `parent_id`. **422:** validation.
