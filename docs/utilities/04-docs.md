# 4. Docs — files, information cards, library, packs

Store files (Spatie media library underneath) or text "information cards", attach them to records, file them in
a library path, control who sees them, collect them in a cart and download packs as zip.
Service `App\Services\Platform\Docs\DocsService` · facade `Docs` · model trait `HasDocuments`.

## Concepts
- **Kinds:** `IMAGE`, `DOCUMENT`, `INFORMATION` (a card with rich text, no file).
- **Collections** (`config/platform.php` → `docs.collections`): `docs`, `kyc`, `quote-pdf`, `thread-docs`, `wa-inbound`,
  `call-recordings`.
- **Library path:** Entity → Location → Category → Sub → Item → FY (`path_entity` … `fy`), browsable with facets.
- **Visibility — `Docs::canView($docId, $userId)` is the only check:**
  - owner, `UTL_DOCS_MANAGE` and superadmin always;
  - with entitlements: any matching `USER`, `DESIGNATION`, `DEPARTMENT`, `SCOPE` (type+code) or `PARENT` (whoever may see the record);
  - without entitlements: an attached file follows its record; an unattached library file follows `UTL_DOCS_VIEW`.
- Upload limits come from Settings: `docs.max_upload_kb`, `docs.allowed_mimes` (a comma list of file **extensions**, e.g.
  `pdf,jpg,png`; blank = any). The drop-zone checks the same list in the browser. Soft delete; the daily purge job
  removes files after `docs.purge_after_days`.

## API
| Call | Returns |
|---|---|
| `Docs::attach($model, UploadedFile $file, $collection = 'docs', $meta = [])` | Result = document DTO — `INVALID_FILE` |
| `Docs::card(['title' => …, 'info_body' => '<p>…</p>', 'path_*' => …], $model = null)` | Result DTO |
| `Docs::entitle($docId, ['users' => [..], 'designations' => ['SM'], 'departments' => ['SALES'], 'scopes' => ['BRANCH' => ['JPR']], 'parent' => true])` | Result (replaces) |
| `Docs::canView($docId, $userId)` | bool |
| `Docs::listFor($model, $collection = null)` | DTOs the viewer may see |
| `Docs::library($filters, $viewerId)` | `['items' => [...], 'facets' => [...]]`; filters `path_*`, `fy`, `kind`, `q` |
| `Docs::cartAdd/cartRemove($userId, $docId)`, `Docs::cart($userId)`, `Docs::cartSaveAs($userId, $name)` | cart → pack |
| `Docs::groups($userId)`, `createGroup`, `addToGroup`, `removeFromGroup`, `renameGroup`, `deleteGroup` | packs |
| `Docs::zip($userId, $groupId)` | Result `{path, files}` — only files the user may see |
| `Docs::delete($docId)` | Result — owner or `UTL_DOCS_MANAGE` |
| `Docs::mine($userId)`, `Docs::recent($viewerId)` | lists |
| `Docs::latestFor($model, $collection)` | `?Document` (with media) — newest document in a record's slot, no access filter |
| `Docs::supersede($model, $collection, $actorId = null, $exceptId = null)` | int — soft-deletes the slot's documents (except one) |

DTO: `id, name, kind, collection, mime, size, url, is_image, info, path[], fy, owner_id, created_at`.
Trait (`use HasDocuments;`): `$model->attachDocument($file, 'kyc')`, `$model->documentsList('kyc')`, `$model->documents()`.

**One-file slots** (a record's proof: receipt, policy copy, TRC, delivery photo — DEC-069):

| Trait call | Returns |
|---|---|
| `$m->replaceDocument('trc_copy', $file, $meta = [], $errorField = null)` | `Document` — attaches without a Chat event, then supersedes the older ones; a rejected file throws `ValidationException` on `$errorField` (default: the collection) |
| `$m->documentFor('trc_copy')` | `?array{id, name, mime, size, is_image, view_url, download_url}` |
| `$m->documentUrl('trc_copy', $inline = true)` | string, `''` when empty — the access-checked download route (`?inline=1` previews in the browser) |
| `$m->hasDocumentIn('trc_copy')` | bool |
| `$m->removeDocuments('trc_copy')` | int removed |

```php
// service: replace the RTO's TRC copy; show the error under the form field on a bad file
$rto->replaceDocument('trc_copy', $request->file('trc_copy'), [], 'trc_copy');
// Blade: link or preview
@if ($rto->hasDocumentIn('trc_copy'))<a href="{{ $rto->documentUrl('trc_copy') }}" target="_blank">TRC</a>@endif
```

Access: the owning model's `chatCanView($userId)` (booking satellites check `SLS_BKNG_VIEW`) or its entity
permission in `config/platform.php`.

## Use cases

**1. Attach an uploaded file to a record** (controller)
```php
$result = Docs::attach($booking, $request->file('pan'), 'kyc', ['title' => 'PAN card']);
if (! $result->ok) { return back()->withErrors(['pan' => $result->message]); }   // e.g. too large / type not allowed
```
An `ATTACHED` event is added to the booking's timeline.

**2. Upload widget with list on a record screen**
```blade
<x-docs.uploader :model="$booking" collection="kyc" title="KYC documents" />
```
Drop-zone with previews; uploads by AJAX with per-file progress, then the list refreshes.

**3. Only the finance team may see a document**
```php
Docs::entitle($docId, ['departments' => ['FIN'], 'users' => [$ownerId]]);
```

**4. Company library document** (e.g. an HR policy)
```php
Docs::attach(null, $file, 'docs', ['title' => 'Leave policy', 'path_entity' => 'BMPL', 'path_category' => 'HR', 'fy' => '26-27']);
```

**5. Information card** (no file)
```php
Docs::card(['title' => 'Insurance partners', 'info_body' => '<ul><li>ICICI</li><li>HDFC</li></ul>', 'path_category' => 'Insurance']);
```

**6. Build a pack for a customer file**
```php
Docs::cartAdd($uid, $invoiceDoc); Docs::cartAdd($uid, $kycDoc);
$pack = Docs::cartSaveAs($uid, 'Claim file — Nexon 1234');
return response()->download(Docs::zip($uid, $pack->get('group_id'))->get('path'))->deleteFileAfterSend();
```

**7. Show a document** `<x-docs.preview :doc="$dto" />` (thumbnail / icon, size, path, info card);
`<x-docs.library-path :path="$dto['path']" />`.

## Screens & permissions
**Utilities → Documents** `/admin/utils/docs` (library, my uploads, cart, packs). `UTL_DOCS_VIEW` to browse,
`UTL_DOCS_UPLOAD` to upload, `UTL_DOCS_MANAGE` to delete anyone's. Download `/admin/utils/docs/{id}/download`
always checks `canView`; `?inline=1` serves the file with `Content-Disposition: inline` (previews). Mobile v1 `/api/v1/docs/*` uses the adapter `DocService`.

## Events & testing
No domain event of its own; attaching writes a Chat `ATTACHED` event. Purge job `PurgeDeletedDocuments`. See [15-testing.md](15-testing.md); every code is in
[16-reference.md](16-reference.md).

## Gotchas
- Never call `$model->addMedia()` for business documents, never add file columns — use Docs.
- Never hand out media URLs as the permission check; link to the download route.
- Information card HTML is limited to `p, br, ul, ol, li, strong, em` (attributes stripped).
