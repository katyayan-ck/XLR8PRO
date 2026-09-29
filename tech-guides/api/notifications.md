# API — Notifications, alerts and messages (module UTL)

**Base URL:** `{{base_url}}/api/v1`. **Postman:** [`postman/notifications.postman_collection.json`](postman/notifications.postman_collection.json).
**Source:** `app/Http/Controllers/Api/V1/NotificationController.php` → `App\Services\NotificationService` (an adapter over
the Notify utility, `tech-guides/modules/api-v1-adapters.md`); resources `NotificationResource`, `AlertResource`,
`MessageResource`.
**Auth:** Bearer token + a live device session ([auth.md](auth.md)). **Envelope and common errors:**
[index.md](index.md#errors-common-to-every-endpoint-dec-085).

> ⚠ `sender.name` / `receiver.name` are always `null` today: `users` has no `name` column (the same defect as BUG-187;
> the fix — fill them from the display name — is part of owner decision D1).

**Paging (every list):** query `page` (default 1), `per_page` (default 20); the response has
`pagination {current_page, per_page, total, last_page}`.
**Sorting (lists):** `sort_by` (see each list; anything else → `created_at`), `sort_order` `asc` | `desc` (default `desc`).
Before 30-09 an unknown value was a 500 (BUG-217).

## Notifications

### GET /notifications — my notifications
**Query:** paging, sorting (`created_at`, `sent_at`, `read_at`, `priority`), `type`, `category`, `priority`,
`read_status` (`read` | `unread`).

**200:**
```json
{ "http_status": 200, "success": true, "code": "S200", "message": "Notifications retrieved", "timestamp": "…",
  "data": {
    "notifications": [
      { "id": 812, "type": "BOOKING", "title": "Booking BK-1043 invoiced", "description": "…", "priority": "normal",
        "category": "sales", "is_read": false, "read_at": null, "sent_at": "2026-09-30T10:12:00+05:30",
        "is_sent_via_fcm": true, "reference_type": "booking", "reference_id": 1043, "deep_link": "xlrm://booking/1043",
        "sender": { "id": 5, "name": null }, "payload": {}, "created_at": "2026-09-30T10:12:00+05:30" } ],
    "pagination": { "current_page": 1, "per_page": 20, "total": 1, "last_page": 1 } } }
```

### GET /notifications/unread — unread only
**Query:** paging, sorting (as above). **200:** as above, message `Unread notifications retrieved`.

### POST /notifications/{id}/read — mark one read
- **200:** `data` = the notification (as above) with `is_read: true`; message `Notification marked as read`.
- **403** `AUTH_FORBIDDEN` — not your notification. **404** `RESOURCE_NOT_FOUND`.

### POST /notifications/mark-all-read
**200:** `data: {"count": 7}` (rows marked); message `All notifications marked as read`.

### DELETE /notifications/{id}
**200:** message `Notification deleted` (no `data`). **403** not yours. **404**.

## Alerts

### GET /alerts — my alerts
**Query:** paging, sorting (`created_at`, `read_at`, `severity`), `severity`, `read_status`.

**200:** `data: { "alerts": [ { "id", "severity", "title", "description", "is_read", "read_at", "reference_type",
"reference_id", "deep_link", "sender": {"id", "name"}, "created_at" } ], "pagination": {…} }`; message `Alerts retrieved`.

### POST /alerts/{id}/read
**200:** `data` = the alert; message `Alert marked as read`. **403** not yours. **404**.

## Messages (one-to-one)

### GET /messages/user/{user_id} — the conversation with a user
**Query:** paging. Newest first.

**200:**
```json
{ "…": "…", "message": "Conversation messages retrieved",
  "data": { "messages": [ { "id": 31, "sender": { "id": 40, "name": null, "avatar": null },
                            "receiver": { "id": 5, "name": null }, "message_text": "Customer confirmed", "message_type": "text",
                            "is_read": false, "read_at": null, "attachments": [], "created_at": "…", "updated_at": "…" } ],
            "pagination": { "…": "…" } } }
```

### POST /messages/user/{user_id} — send a message
**Body (JSON):**

| Field | Validation |
|---|---|
| `message_text` | required unless `attachments` is given; string, max 2,000 |
| `message_type` | required; `text` \| `image` \| `document` |
| `attachments` | array of URLs (optional) |

- **201:** `data` = the message (as above); message `Message sent`.
- **404:** `User not found with ID 999`. **422:** `VALIDATION_FAILED` + `errors`.

### POST /messages/{id}/read
**200:** `data` = the message; message `Message marked as read`. **403** not the receiver. **404**.
