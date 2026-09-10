# Known Pitfalls & Edge Cases

## 1. Incomplete Vehicles
- Vehicles created from Price List sheets may be marked INCOMPLETE if required fields are missing.
- These vehicles cannot be sold or quoted until all required fields are populated.
- Mitigation: Use VehicleService::missingFields() to identify gaps early in the workflow.

---

## 2. Status Transition Constraints
- A vehicle cannot move from INACTIVE -> ACTIVE without passing through the PricingEngineService workflow.
- DISCONTINUED status requires manual intervention; automatic transitions are not permitted.
- Mitigation: Ensure all vehicles reach ACTIVE status before moving to DISCONTINUED.

---

## 3. RBAC Cache Staleness
- RBAC permissions are cached for 3600 seconds. Rapid role changes may cause stale access.
- Mitigation: Use RBACService::clearUserPermissionCache() after role/post changes.

---

## 4. LiteLLM Gateway Connection Issues
- Network timeouts or gateway master key rotation can break AI service calls.
- Mitigation: Implement retry logic with exponential backoff in the PricingEngineService.

---

## 5. N+1 Query Risks
- Directly accessing relations inside loops without eager loading causes performance degradation.
- Mitigation: Always use with() for relations in loops (e.g., User::with(['employee.designation', 'person'])->get()).

---

## 6. Soft Delete Handling
- All tables include soft delete columns (deleted_at). Queries must respect deleted_at IS NULL filters.
- Mitigation: Use Eloquent policies that automatically exclude soft-deleted records.

---

## 7. Person vs User Confusion
- Mixing Person (demographics) and User (authentication) operations can lead to incorrect data mutations.
- Mitigation: Follow the separation principle: PersonService for profile changes, UserService for auth changes.

---

## 8. Scope Bypass Abuse
- Users with bypass_data_scoping = true or SuperAdmin status bypass all data scoping filters.
- Mitigation: Monitor and audit bypass usage; ensure SuperAdmin is properly restricted.

---

## 9. Pricing Calculation Race Conditions
- Multiple concurrent sessions calculating prices for the same vehicle may produce inconsistent results.
- Mitigation: Use database transactions and unique constraint checks during price calculation.

---

## 10. Accessory Catalog Import Conflicts
- Overwriting existing accessory records during import can lose customizations.
- Mitigation: Purge + reload pattern ensures clean state; use merge semantics where appropriate.
