# Architecture Decision Records (ADR)

## ADR Process
When making critical structural, framework, database, or integration choices, document the decision here using the MADR (Markdown Architecture Decision Record) format:

`markdown
### [ADR-00X] Short Title
* **Status:** [Proposed | Accepted | Deprecated | Superseded]
* **Date:** YYYY-MM-DD
* **Context:** What was the problem and why was a decision required?
* **Decision:** What choice was made and why?
* **Consequences:** What are the positive and negative implications or trade-offs?
`

---

## Decision Log

### [ADR-001] Adoption of Multi-Layered AI Knowledge System (.ai/)
* **Status:** Accepted
* **Date:** 2026-09-05
* **Context:** AI agents need immediate access to domain rules, schemas, service maps, and conventions without bloating context windows or scanning irrelevant non-code assets.
* **Decision:** Created .ai/ directory housing modular, role-specific documentation files (architecture.md, services.md, database.md, rbac.md, scopes.md, conventions.md, decisions.md, known-pitfalls.md). Router instructions in .clinerules direct agents to specific files on demand.
* **Consequences:** Eliminates hallucinations and context overflow; guarantees strict adherence to existing service APIs and Eloquent query safety.

---

### [ADR-002] Multi-Tier RBAC with Temporal Role Assignments & Post Permissions
* **Status:** Accepted
* **Date:** 2026-08-15
* **Context:** Dealership employees frequently operate across multiple branches, change designations, or require temporary elevated privileges (e.g. acting branch manager). Standard static roles were insufficient.
* **Decision:** Implemented a unified access evaluation service (App\Services\RBACService) that synthesizes Spatie roles, organizational post permissions (Employee -> Post -> Permissions), and time-limited assignments (UserRoleAssignment::isActive()), with SuperAdmin wildcard bypass.
* **Consequences:** All permission checks must be routed through RBACService with a 3600-second cache TTL. Direct checks of ->hasRole() in application logic are deprecated in favor of canUserAccess().

---

### [ADR-003] Centralized LiteLLM Gateway Integration
* **Status:** Accepted
* **Date:** 2026-09-01
* **Context:** Multiple AI model endpoints (Fast, Multimodal, Enterprise, Router, Edge) are consumed by background document analysis, quote generation, and smart assistants. Hardcoding vendor SDKs creates tight coupling.
* **Decision:** Routed all AI traffic through a unified LiteLLM Gateway proxy (XCELR8_GATEWAY_BASE_URL with master key XCELR8_GATEWAY_MASTER_KEY).
* **Consequences:** Standardizes OpenAI-compatible API schemas for all models while allowing backend provider switching (e.g., Gemini, Llama, Mistral) without modifying application code.

---

### [ADR-004] Separation of Identity (Person) and Operational Account (User)
* **Status:** Accepted
* **Date:** 2026-07-20
* **Context:** An individual can transition between being a customer, an employee, or an external partner while retaining contact, KYC, and banking history.
* **Decision:** Decoupled Person (demographics, contact, address, banking) from User (system login, credentials, session tokens) and Employee (designation, branch assignments, reporting structure).
* **Consequences:** Mutations to personal contact details must be handled via PersonService, while auth and scoping belong to User and OrgService.
