# API v1 adapters — services behind the mobile app's endpoints

The mobile app calls `/api/v1/*` (Sanctum token bound to a device, `validate_device` middleware, envelope
`{http_status, success, code, message, data}`; rules in `.ai/rules/api.md`). Its contract is **frozen** (DEC-004): add
fields, never rename or remove. Several services exist only to keep those endpoints working on top of the platform
utilities — **don't use them in new code**.

| Endpoint group | Controller | Service (adapter over) | New code uses |
|---|---|---|---|
| `auth/request-otp`, `verify-otp`, `me`, `logout` | `AuthController` | `AuthService` ([iam-auth.md](iam-auth.md)) + `OtpNotificationService` | `Sms::otp()` / `verify()` for new OTP flows |
| `notifications*`, `alerts*`, `messages*`, `devices*` | `NotificationController` | `NotificationService` (→ Notify), `FirebaseService` | `Notify` facade (docs/utilities/02) |
| `docs/*` | `DocController` | `DocService` (→ Docs) | `Docs` facade (docs/utilities/04) |
| `history/{entityType}/{entityId}` (+ `/thread`) | `EntityHistoryController` | `Utils\EntityHistoryService` (→ Chat) | `Chat` facade (docs/utilities/03) |
| `system-settings*` | `SystemSettingApiController` | `SystemSettingService`, `SystemSettingExportImportService` ([utils-legacy.md](utils-legacy.md)) | `Settings` facade |
| vehicle pricing | `Api\V1\Vehicle\Pricing\PricingController::getPricing` | `PricingEngineService::getPricingPayload()` ([pricing.md](pricing.md)) | same |

Known issues on this surface: BUG-182 (history / docs endpoints trust a client-supplied model class and skip record
access), BUG-187 (auth responses return null name / email / mobile), BUG-188 (OTP from `rand()`), BUG-189 (full mobile
numbers in logs).

---

## NotificationService (`App\Services\NotificationService`)
| Method | Returns |
|---|---|
| `sendAndLogNotification(User $to, $type, $title, $description, $entityType, $entityId, $extra = [])` | `Notification` row (sent through Notify: inbox row + queued FCM) |
| `sendToMultipleUsers(array $userIds, $type, $title, $description, $entityType, $entityId, $extra = [])` | per-user results |
| `sendAlert(User $to, $severity, $title, $description, $entityType, $entityId, $extra = [])` | `Alert` row |
| `sendMessage(User $from, User $to, $text, $type = 'text', $attachments = [])` | `Message` row (direct messages still live here) |
| `markAsRead(Notification $n)` / `markAllAsRead(User $u)` / `getUnreadCount(User $u)` | bool / count / count |
Equivalent today: `Notify::to($id)->about($entityType, $entityId)->title($title)->body($description)->send()`.

## FirebaseService (`App\Services\FirebaseService`)
FCM transport — Notify's push job calls it; don't call it from feature code.
| Method | Returns |
|---|---|
| `registerDeviceToken(User $u, $deviceId, $deviceName, $platform, $fcmToken, $metadata = [])` | `UserDeviceToken` (create or update) |
| `sendToDevice(UserDeviceToken $d, array $notification, array $data = [])` | bool |
| `sendToUserDevices(User $u, array $notification, array $data = [])` | `['success' => n, 'failure' => n, …]` |
| `sendToMultipleUsers(array $userIds, array $notification, array $data = [])` | aggregated results |
| `generateDeepLink($entityType, $entityId)` / `createPayload($action, $entityType, $entityId, $extra)` | deep link string / data payload |
| `logNotificationSent(Notification $n, array $fcmResult)` | void |
| `getUserActiveDevices(User $u)`, `revokeDevice($d)`, `revokeAllUserDevices($u)`, `cleanupExpiredTokens()` | Collection / bool / int / int |
| `testConnection()` | diagnostics array |
Credentials: the Google service-account file (key rotation pending — see state file).

## DocService (`App\Services\DocService`)
Over `DocsService`: `upload(array $data, ?Model $entity)` → `Document`; `hasAccess(User, Document)` (= `Docs::canView`);
`getMyDocuments(User)`; `createGroup(array $data)`; `addToGroup(DocGroup, Document)` / `removeFromGroup(...)`;
`downloadGroupZip(DocGroup)` → path; `search($q, User)`; `getAnalytics(User)`; `approve(Document, User $approver)` (records
the approval on the document's timeline; formal approvals use the Approval engine).

## EntityHistoryService (`App\Services\Utils\EntityHistoryService`)
Over `ChatService`: `createMaster(Model $entity, ?$title)` → `CommMaster`; `addThread(CommMaster $m, $actionSlug, $title,
$body, $extra, ?CommThread $parent, $actor)` → `CommThread` (an EVENT); `getFullHistory(Model $entity)` → the master with
threads. `HasCommunications::addHistory()` calls the same path — Booking still uses it.

## OtpNotificationService (`App\Services\OtpNotificationService`)
Login-OTP delivery for `AuthService`: `sendViaEmail($email, $otp, ?$mobile)`, `sendViaSms($mobile, $otp)` (placeholder
— logs instead of sending), `sendViaEmailAndSms($email, $mobile, $otp)` → per-channel results,
`sendVerificationSuccessEmail($email, $mobile, $deviceName)`, `sendAccountLockedEmail($email, $mobile, $reason)`.
New OTP flows: `Sms::otp()` / `Sms::verify()` (hashed, rate-limited, template `otp.sms`, never logged).

## Testing the API
Use a device-bound token (`.ai/rules/testing.md`): create a `DeviceSession`, then
`$user->createToken('test', ['device_id:'.$deviceId])`, and send `Authorization: Bearer …` plus the device header the
middleware expects. Assert the envelope keys and that **existing** keys are still present (contract tests).
