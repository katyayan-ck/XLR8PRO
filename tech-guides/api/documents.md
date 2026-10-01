# API — Documents (module UTL, Docs utility)

**Base URL:** `{{base_url}}/api/v1/docs`. **Postman:** [`postman/documents.postman_collection.json`](postman/documents.postman_collection.json).
**Source:** `app/Http/Controllers/Api/V1/DocController.php` → `App\Services\DocService` (adapter over
`Platform\Docs\DocsService`, see `tech-guides/platform/04-docs.md`).
**Auth:** Bearer token + a live device session. **Envelope / common errors:** [index.md](index.md#errors-common-to-every-endpoint-dec-085).

> **Access (DEC-095, BUG-182 fixed 02-10-2026):** `entity_type` is an entity code of `config/platform.php` or the
> app's short class name (as in [history.md](history.md)); the record must be visible to the caller (403 / 404).
> Group endpoints (`add`, `remove`, `zip`) work only on the caller's own groups (or any group with `UTL_DOCS_MANAGE`)
> and on documents the caller may see (`DocsService::canView()`); `approve` needs `UTL_DOCS_MANAGE`.

## POST /upload — upload a document (multipart/form-data)

| Field | Validation | Notes |
|---|---|---|
| `title` | required, string | |
| `description` | string, nullable | |
| `category_key` | required, string | Docs category |
| `expiry_date` | date, nullable | ISO `YYYY-MM-DD` |
| `file` | required, file | Type / size limits from Settings `docs.allowed_mimes`, `docs.max_upload_kb` |
| `entity_type` | string, nullable | Entity code (`ENQUIRY`, `BOOKING`, …) or short class name (`Enquiry`) — see the access note |
| `entity_id` | integer, nullable | With `entity_type` |
| `requires_approval` | boolean | |

- **201:** `data` = the `Document` row (id, title, description, owner, created_at, …); message `Document uploaded`.
- **404:** unknown `entity_id`. **422:** validation. **500** `SYSTEM_ERROR` + `error_ref` when the Docs utility refuses
  the file (its message is logged).

## GET /my — my documents
**200:** `data` = up to 200 documents I own or created, newest first; message `Documents retrieved`. Each item
(`DocsService::dto()`):

```json
{ "id": 91, "name": "PAN copy", "kind": "FILE", "collection": "docs", "mime": "application/pdf", "size": 184320,
  "url": "https://…/storage/…/pan.pdf", "is_image": false, "info": null, "path": "Customers/2026-27",
  "fy": "2026-27", "owner_id": 40, "created_at": "2026-09-30T10:12:00+05:30" }
```
`info` holds the text of an information card (`kind` = `INFORMATION`).

## GET /search?query=… — search the library + my documents
**Query:** `query` (required). **200:** `data` = matching documents (same shape), de-duplicated; message `Search results`.

## GET /analytics
**200:** `data: {"views": 12, "downloads": 3}` — my own view / download counts; message `Analytics retrieved`.

## POST /groups — create a group
**Body:** `name` (required), `description` (nullable). **201:** `data` = the group; message `Group created`.

## POST /groups/{groupId}/add
**Body:** `doc_id` (required, integer, must exist). **200:** message `Added to group`. **404** unknown group.

## DELETE /groups/{groupId}/remove/{docId}
**200:** message `Removed from group`. **404** unknown group or document.

## GET /groups/{groupId}/zip — download the group as a ZIP
**200:** `application/zip` file download (not JSON). **404** unknown group (JSON envelope).

## POST /{docId}/approve
**200:** message `Document approved` (records an `APPROVED` history event by the caller). **404** unknown document.
