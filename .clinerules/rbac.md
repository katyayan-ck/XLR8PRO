<!--
# Role-Based Access Control (RBAC) Matrix & Specifications
# Scope: Role-Based Access Control matrix structure, permission checking, and temporal roles
-->

# Role-Based Access Control (RBAC) Matrix

## 1. Overview
The application enforces a multi-tier authorization model combining:
1. **Spatie Roles & Permissions**: Base role assignments on the `User` model.
2. **Organizational Post-based Permissions**: Inherited through the linked `Employee`'s assigned posts.
3. **Temporal User Role Assignments**: Time-delimited permissions that activate and expire dynamically (`UserRoleAssignment::isActive()`).
4. **SuperAdmin Blanket Access**: Full bypass using wildcard `['*']`.

All RBAC decisions must flow through `App\Services\RBACService`.

---

## 2. Permission Resolution Pipeline

When checking `$rbacService->canUserAccess($user, $resource, $action)`:
1. **SuperAdmin Check**: If `$user->isSuperAdmin()`, access is granted immediately.
2. **Wildcard & Multi-Source Permission Aggregation**:
   Permissions are gathered from three sources and cached under `user.{id}.permissions` for 3600 seconds:
   - Direct & Role Permissions: `$user->roles->flatMap->permissions`
   - Employee Post Permissions: `$user->employee->posts->flatMap->permissions`
   - Active Temporal Roles: `$user->userRoleAssignments->filter->isActive()->flatMap->role->permissions`
3. **Evaluation**: Checks if `{$resource}.{$action}` exists within the aggregated permission set.

---

## 3. Core Role Matrix

| Role Name | Guard | Target User Type | Core Responsibilities |
|---|---|---|---|
| `super_admin` | `web`, `sanctum` | System Architects & IT Admins | Unrestricted global access (`*`), bypasses data scoping |
| `admin` | `web`, `sanctum` | General Dealership Administrators | Full module administration, system settings import/export |
| `branch_manager` | `web`, `sanctum` | Branch Heads | Full access within assigned branch scopes, downline management |
| `sales_manager` | `web`, `sanctum` | Sales Team Leads | Team quotes, pricing reviews, sales performance reports |
| `sales_consultant` | `sanctum` | Field Sales Consultants | Vehicle pricing quotes, customer inquiry creation, my-docs |
| `hr_executive` | `web` | HR Personnel | Employee lifecycle, onboarding/offboarding, post management |
| `auditor` | `web` | Internal Auditors | Read-only access across all entity history, pricing logs, documents |

---

## 4. Resource & Action Convention

Permissions follow the strict format: `<module_or_resource>.<action>`

### Standard Actions:
- `view`: View index list and single entity details.
- `create`: Open creation forms and persist new entities.
- `edit`: Update existing records.
- `delete`: Soft-delete or archive records.
- `export`: Download Excel/CSV/PDF dumps.
- `import`: Upload and process bulk spreadsheets.
- `approve`: Workflow sign-off / status transition.

### Common Resource Modules:
- `branch.*` (`branch.view`, `branch.edit`)
- `employee.*` (`employee.view`, `employee.create`, `employee.edit`, `employee.delete`)
- `pricing.*` (`pricing.view`, `pricing.export`, `pricing.update`)
- `system_setting.*` (`system_setting.view`, `system_setting.edit`, `system_setting.export`)
- `doc.*` (`doc.upload`, `doc.view`, `doc.approve`, `doc.delete`)

---

## 5. Cache Lifecycle & Invalidation
- The RBAC cache (`user.{$user->id}.permissions`) **must** be cleared upon:
  * Assigning or removing a role from a user (`RBACService::clearUserPermissionCache($user)`).
  * Updating permissions attached to an assigned role or post.
  * Changing employee post assignments.
*** End Patch