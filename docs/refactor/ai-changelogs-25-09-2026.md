# AI changelog — 25-09-2026

## Follow-up to the 24-09 known-bugs sweep

### Class-name case mismatches (BUG-156, fixed)
Class references whose letter case differs from the file on disk work on Windows but fail to
autoload on case-sensitive (Linux) filesystems. A whole-repo scan of `App\…` references against exact
on-disk paths found 10:

| File(s) | Before | After |
|---|---|---|
| `BookingCrudController`, `OrgService`, `BookingCoreService`, `BookingExchangeService`, `BookingFinanceService`, `BookingInsuranceService`, `BookingOtfService`, `BookingRtoService` | `XL_DSA_MASTER` (import + usages) | `Xl_DSA_Master` (declared class / file name) |
| `app/Models/Utilities/CommHistory/CommThread.php` | `\App\Models\Utilities\KeyValue\KeyValue::class` | `Keyvalue` (file `Keyvalue.php`); Pint also reformatted the one-line methods |
| `database/seeders/CrmLeadSourceSeeder.php` | `use App\Models\Crm\LeadSource;` | `use App\Models\CRM\LeadSource;` |

Rescan: zero mismatches in the app (remaining hits are only inside `.kilo/worktrees/`, a tool copy).

### `User::deviceTokens()` added (BUG-157, fixed)
`FirebaseService::sendToUserDevices()`, `getUserActiveDevices()` and `revokeAllUserDevices()` call
`$user->deviceTokens()`, which was never defined (not in the backup `User` either).
Added `deviceTokens()` → `hasMany(UserDeviceToken::class)` (FK `xlr8_iam_user_device_token.user_id`,
matching the inverse `UserDeviceToken::user()`). Generated SQL verified including the `active()` scope.

### PHPStan: exclude `app/Models_backup` (BUG-140, fixed for static analysis)
`phpstan.neon` gained `excludePaths: app/Models_backup`. The backup directory declares a stale
`App\Models\User`, which made Larastan report false "undefined method" errors
(`isSuperAdmin()`, `permissionOverrides()`). Directory deletion itself is left for the dead-code purge.

### Documented, not fixed
- **BUG-158** — `User::branches/locations/departments` and `Employee::branches/locations/departments`
  are `belongsToMany` through `xlr8_admin_emp_*_pivot` tables that don't exist. Live caller:
  `UserExporter` (`POST org/user/export`) crashes on the first user. The dashboard callers are dead code.
  Needs a decision on what the export's assignments sheet should contain.

### Checks
- `php -l` on all changed files; Pint clean; all changed classes load.
- MySQL was stopped mid-session, so the test suite was not re-run for this follow-up (the 24-09 run was
  212 passed / 31 known failures).

No commit made on 25-09.
