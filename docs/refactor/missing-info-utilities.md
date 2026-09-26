# Xceler8 Support Utilities — Combined Workflow FRS

**Document status:** Consolidated baseline and gap-analysis draft  
**Prepared:** 2026-09-26  
**Project:** Xceler8 / BMPL CRM

## 1. Purpose and evidence boundary

This document consolidates the support utilities explicitly identified in the available project context: **Chat**, **Documentation/Knowledge Base**, **Ticket Support/Helpdesk**, and the shared platform capabilities required by them. It converts the known direction into implementation-ready workflows and use cases.

The repository scan available for this review returned only `composer.json`; no application source, migrations, routes, views, tests, product FRS, or previously exported design notes were available for verification. Therefore, this document distinguishes between:

- **Confirmed repository evidence:** technology/package indicators in `composer.json`.
- **Previously stated project direction:** the utilities named in the request and the known Xceler8 architecture context.
- **Proposed baseline:** requirements added to make the utilities complete, secure, testable, and implementable.

This is not proof that every historical independent conversation was recovered. It is a gap-controlled FRS that should be reconciled against any missing conversation exports or source branches.

## 2. Verification findings

### 2.1 Confirmed in the repository manifest

The package manifest indicates support for Laravel/PHP application development, Passport API authentication, Spatie permissions, media attachments, mail, FCM notifications, Microsoft Graph, Google Sheets, Excel import/export, PDF generation, query caching, and DataTables. These dependencies are compatible with the proposed utilities, but a dependency does **not** prove that the corresponding feature has been implemented.

Relevant evidence is in `composer.json` at the project root. [file:1]

### 2.2 Not verifiable from the available repository

The following could not be confirmed as implemented:

- Chat tables, models, routes, APIs, UI, broadcasting, or message retention.
- Documentation/category/article/version/search models and workflows.
- Ticket tables, statuses, assignment rules, SLA timers, escalation, or reports.
- A unified notification center, preferences, templates, queues, or delivery logs.
- Tenant/organization data scoping for support utilities.
- Attachments, antivirus validation, download authorization, and retention rules.
- Audit logs, immutable event history, and administrative traceability.
- Tests, seeders, permissions, policies, factories, API documentation, or deployment configuration.

### 2.3 Important gaps to resolve

1. **Source-of-truth gap:** obtain the missing project files or conversation exports before claiming historical completeness.
2. **Terminology gap:** decide whether “chat” means general direct/team chat, ticket-linked conversation, customer live chat, or all three.
3. **Documentation scope gap:** decide whether documentation is internal-only, customer-facing, or both, with separate publication controls.
4. **Support model gap:** confirm whether tickets may be created by external customers, internal users, email ingestion, API clients, or all channels.
5. **Real-time gap:** choose WebSockets/broadcasting, polling, or a staged fallback. The current manifest does not prove a broadcasting provider.
6. **SLA gap:** define business calendars, priority targets, pause conditions, holidays, and escalation recipients.
7. **AI gap:** decide whether AI suggestions, summarization, classification, and knowledge search are in scope; if yes, define data-privacy and human-approval rules.

## 3. Shared platform requirements

### 3.1 Actors and access

| Actor | Core capabilities |
|---|---|
| Platform administrator | Configure utilities, global permissions, retention, templates, integrations, and reporting. |
| Organization administrator | Manage organization users, teams, visibility, workflows, and reports within the organization scope. |
| Support manager | Configure queues, assign work, monitor SLAs, reopen/close tickets, and review metrics. |
| Support agent | Handle assigned or permitted tickets/chats, reply, attach files, link articles, and record internal notes. |
| Documentation editor | Draft, edit, review, publish, archive, and version knowledge articles. |
| End user/customer | Create and track tickets, participate in permitted chats, search published documentation, and rate resolutions. |
| Viewer/auditor | Read authorized records and reports without changing business data. |
| Automation/service account | Ingest email/API events, send notifications, run SLA jobs, and synchronize external systems. |

All access must be enforced through role permissions plus organization/team/data-scope policies. UI hiding is not authorization.

### 3.2 Common entities

- `organizations`, `teams`, `users`, and user-team memberships.
- `support_channels` and channel configuration.
- `conversations`, `conversation_participants`, and `conversation_messages`.
- `tickets`, `ticket_statuses`, `ticket_priorities`, `ticket_categories`, `ticket_tags`.
- `ticket_assignments`, `ticket_events`, `ticket_replies`, and `ticket_watchers`.
- `knowledge_spaces`, `knowledge_categories`, `knowledge_articles`, `article_versions`, and `article_feedback`.
- `attachments`, `notifications`, `notification_deliveries`, and `audit_logs`.
- `sla_policies`, `sla_instances`, `business_calendars`, and escalation rules.

Use organization IDs and explicit ownership/scope columns wherever data can cross organizational boundaries. Use UUIDs or another non-sequential public identifier for externally exposed records.

### 3.3 Shared non-functional requirements

- Record all timestamps in UTC and render in the user’s configured timezone.
- Enforce authorization in controllers, policies, jobs, API resources, and downloads.
- Queue email, push, indexing, exports, and other slow work.
- Make notification and integration jobs idempotent.
- Validate attachment type, size, extension, content signature, and authorization.
- Keep audit events append-only and include actor, organization, IP/device metadata where permitted, old/new values, and correlation ID.
- Provide pagination, filtering, sorting, full-text search, and export limits.
- Use soft deletion only where legally and operationally appropriate; define purge rules separately.
- Add feature flags for staged rollout and migration-safe deployment.
- Document REST endpoints in OpenAPI and cover critical paths with unit, feature, authorization, and integration tests.

## 4. Utility A — Chat and conversations

### 4.1 Scope

Chat provides asynchronous and optionally real-time conversations between permitted participants. It supports direct/team conversations and ticket-linked conversations. Customer live chat may be enabled as a separate channel sharing the same conversation/message foundation, but it must not silently expose internal messages.

### 4.2 Core requirements

- Create direct, group, team, and ticket-linked conversations.
- Add/remove participants according to permission and conversation policy.
- Send text, formatted text, links, and authorized attachments.
- Reply, edit within a configured window, delete/redact according to policy, and quote/reply to a message.
- Track sent, delivered, read, failed, and moderated states where the channel supports them.
- Mention users/teams and create notifications.
- Search messages within the user’s permitted scope.
- Pin, bookmark, mute, archive, close, reopen, and transfer conversations.
- Link a conversation to a ticket, customer, organization, article, or other business record.
- Preserve internal notes as a distinct message visibility type.
- Support polling initially and broadcast events when a provider is configured.

### 4.3 Main workflow

1. User opens Chat and sees only conversations allowed by organization and participant scope.
2. User starts a conversation, selects permitted participants, and optionally links a ticket or record.
3. System creates the conversation and participant records, then emits an in-app notification.
4. A participant submits a message. The system validates membership, content, rate limits, attachment access, and conversation state.
5. System persists the message, writes an audit/event record, queues notifications, and publishes a real-time event if enabled.
6. Recipient opens the conversation; the system updates read state without exposing hidden/internal messages.
7. User may close or archive the conversation. Closing must retain history and allow reopening under policy.

### 4.4 Use cases

- UC-C01: Start a one-to-one conversation.
- UC-C02: Start a group conversation with a team.
- UC-C03: Continue discussion inside a ticket.
- UC-C04: Send a message with an attachment.
- UC-C05: Mention a user or team.
- UC-C06: Reply to a specific message.
- UC-C07: Edit or redact a message according to policy.
- UC-C08: Search conversation history.
- UC-C09: Mark messages read/unread and manage unread counts.
- UC-C10: Mute notifications for a conversation.
- UC-C11: Convert a conversation into a ticket.
- UC-C12: Link an existing conversation to a ticket.
- UC-C13: Add an internal note invisible to the customer.
- UC-C14: Agent handoff between teams.
- UC-C15: External visitor starts live chat, is identified or provisionally created, and is handed to an agent.
- UC-C16: Agent closes a resolved chat and optionally requests a satisfaction rating.
- UC-C17: Message delivery fails and is retried without duplicate creation.
- UC-C18: Unauthorized user attempts to access a conversation and receives a non-leaking denial.

### 4.5 Acceptance criteria

- A participant can never read a conversation or attachment outside their scope.
- Duplicate client retries do not create duplicate messages.
- Internal notes never appear in customer APIs, notifications, exports, or public chat.
- Every message has author, timestamp, conversation, visibility, and audit metadata.
- The UI works with real-time disabled by falling back to polling or refresh.

## 5. Utility B — Documentation and knowledge base

### 5.1 Scope

Documentation is a structured, searchable knowledge system for internal procedures, product guidance, FAQs, and customer-facing help. It must support draft-to-publication governance and article version history.

### 5.2 Core requirements

- Organize content into spaces, categories, subcategories, and articles.
- Store title, summary, body, slug, audience, language, tags, owner, reviewer, and status.
- Support draft, in review, changes requested, approved, published, scheduled, archived, and rejected states.
- Maintain immutable article versions and publication history.
- Restrict visibility by organization, role, team, subscription/product, or public access.
- Support article search with title/body/tag/category filters.
- Allow related articles, ticket linking, and suggested articles during ticket creation.
- Record helpful/not-helpful feedback and optional comments.
- Provide review dates, stale-content reports, and ownership reminders.
- Export or render approved content to HTML/PDF where required.
- Support attachments and embedded media through the common media policy.

### 5.3 Main workflow

1. Editor creates a draft in a selected knowledge space.
2. Editor saves revisions; each meaningful revision is versioned or tracked.
3. Editor submits the article for review.
4. Reviewer approves, requests changes, or rejects with a reason.
5. On approval, publisher publishes immediately or schedules publication.
6. Search indexing and notifications run asynchronously.
7. Readers access only the latest version permitted for their audience.
8. Feedback, usage, and linked-ticket outcomes are measured.
9. Owner reviews stale content before the review date and republishes, updates, or archives it.

### 5.4 Use cases

- UC-D01: Create a documentation space.
- UC-D02: Create nested categories.
- UC-D03: Draft an article.
- UC-D04: Save article revision and compare versions.
- UC-D05: Submit for review.
- UC-D06: Request changes with structured comments.
- UC-D07: Approve and publish.
- UC-D08: Schedule future publication.
- UC-D09: Unpublish or archive an article.
- UC-D10: Restrict an article to a role/team/organization.
- UC-D11: Search and filter documentation.
- UC-D12: Mark an article helpful/not helpful.
- UC-D13: Suggest an article while creating or replying to a ticket.
- UC-D14: Link an article to a ticket resolution.
- UC-D15: View article version history.
- UC-D16: Detect stale articles by review date or low feedback.
- UC-D17: Import documentation from an approved source.
- UC-D18: Export a space or article set for offline review.
- UC-D19: Prevent publication when mandatory metadata or review is missing.
- UC-D20: Keep customer-facing and internal content separate.

### 5.5 Acceptance criteria

- A customer sees only published content intended for that customer’s scope.
- Drafts and review comments never appear in public search.
- Every publication has an approver, version, publication timestamp, and audit event.
- Search results respect authorization filters before results are returned.
- An article can be traced to the version used in a ticket response.

## 6. Utility C — Ticket support/helpdesk

### 6.1 Scope

Ticket Support manages structured requests from intake through resolution and closure. It should support manual creation, customer portal/API creation, email ingestion if approved, assignment queues, SLA tracking, threaded replies, internal notes, attachments, escalation, and reporting.

### 6.2 Recommended lifecycle

`New → Triaged → Assigned → In Progress → Waiting for Customer → Waiting for Internal/External → Resolved → Closed`

Permitted transitions must be configurable by role and status. Reopen from Resolved/Closed should preserve history and either restart or continue SLA according to policy.

### 6.3 Core requirements

- Generate a human-readable ticket number and a non-guessable public ID.
- Capture subject, description, requester, organization, source, category, product/module, priority, impact, urgency, tags, and attachments.
- Support queues, assignment to agents/teams, watchers, and escalation owners.
- Provide public replies and internal notes as separate visibility types.
- Track status transitions, assignment history, priority changes, and time spent.
- Calculate response and resolution SLAs using business calendars and pause rules.
- Notify requester and staff on creation, assignment, reply, status change, SLA risk, breach, and closure.
- Allow merge, split, duplicate marking, parent/child linking, and related-ticket links.
- Require resolution summary and resolution category before closure where configured.
- Allow requester satisfaction rating after resolution.
- Provide dashboards for backlog, aging, SLA, workload, first response, resolution time, reopen rate, and CSAT.
- Support CSV/Excel import/export subject to authorization and validation.
- Maintain complete audit history.

### 6.4 Main workflow

1. Requester submits a ticket through portal, UI, API, or approved inbound email.
2. System validates identity, organization scope, required fields, attachments, and rate limits.
3. System creates the ticket, initial public message, event record, and SLA instance.
4. Classification applies category, priority, queue, tags, and optional suggested knowledge articles.
5. Queue manager or automation assigns the ticket to an agent/team.
6. Agent replies publicly or writes an internal note. Each action updates timeline and notifications.
7. If waiting on the requester, status changes to `Waiting for Customer` and SLA pause behavior is applied.
8. Agent resolves the issue with a summary and optional linked article.
9. Requester may reopen within the configured window or accept the resolution.
10. Closure job or authorized user closes the ticket, records final metrics, and sends survey notification.
11. Escalation jobs detect approaching/breached SLAs and notify or reassign according to policy.

### 6.5 Use cases

- UC-T01: Create ticket as an authenticated user.
- UC-T02: Create ticket on behalf of another user.
- UC-T03: Create ticket from an external API client.
- UC-T04: Ingest a ticket from an approved email mailbox.
- UC-T05: Prevent duplicate ticket creation from retried requests.
- UC-T06: Auto-classify category, priority, and queue.
- UC-T07: Manually triage and correct classification.
- UC-T08: Assign to a team.
- UC-T09: Assign to an individual agent.
- UC-T10: Reassign while preserving history.
- UC-T11: Add watcher/follower.
- UC-T12: Add public reply.
- UC-T13: Add internal note.
- UC-T14: Attach and download an authorized file.
- UC-T15: Change priority with audit trail.
- UC-T16: Pause SLA while waiting for customer.
- UC-T17: Escalate on impending or breached SLA.
- UC-T18: Merge duplicates.
- UC-T19: Split a multi-issue ticket.
- UC-T20: Link related tickets.
- UC-T21: Link or send a knowledge article.
- UC-T22: Resolve with mandatory resolution details.
- UC-T23: Customer reopens a resolved ticket.
- UC-T24: Automatically close after a configured period.
- UC-T25: Customer rates support.
- UC-T26: Manager views workload and SLA dashboards.
- UC-T27: Export filtered tickets.
- UC-T28: Bulk update tickets with permission and confirmation.
- UC-T29: Restrict ticket visibility across organizations.
- UC-T30: Audit an unauthorized or suspicious action.

### 6.6 Acceptance criteria

- A ticket cannot be viewed or changed outside the requester’s or staff member’s permitted scope.
- Public replies and internal notes are technically distinct at API, notification, export, and UI layers.
- SLA calculations are reproducible from stored policy, calendar, pause intervals, and event history.
- Every state transition records actor, timestamp, old status, new status, and reason where required.
- Closing requires configured mandatory fields and does not erase the conversation history.

## 7. Notifications and integrations

### 7.1 Notification matrix

| Event | Requester | Assignee/team | Manager | Channels |
|---|---:|---:|---:|---|
| Ticket created | Yes | Yes | Optional | In-app, email, push |
| New public reply | Yes | Yes | Optional | In-app, email, push |
| Internal note | No | Yes | Optional | In-app, push |
| Assignment/reassignment | Optional | Yes | Optional | In-app, email |
| SLA risk/breach | No | Yes | Yes | In-app, email, push |
| Ticket resolved/closed | Yes | Optional | Optional | In-app, email |
| Article review due | No | Owner/editor | Manager | In-app, email |
| Chat mention | Mentioned user | Optional | No | In-app, push |

Users need per-channel preferences, quiet hours, digest options, and organization-level mandatory alerts. Delivery attempts must be logged with provider response and retry state.

### 7.2 Candidate integrations

The manifest contains mail, FCM, Microsoft Graph, Google Sheets, Excel, PDF, media, Passport, and permission-related dependencies, so these are viable integration candidates. [file:1]

Implementation must still define credentials, scopes, webhook verification, retry behavior, rate limits, failure queues, data mapping, and reconciliation. Do not treat package presence as integration completion.

## 8. APIs and screens

### 8.1 Suggested API resources

- `GET/POST /api/v1/conversations`
- `GET/POST /api/v1/conversations/{conversation}/messages`
- `POST /api/v1/conversations/{conversation}/read`
- `GET/POST /api/v1/tickets`
- `GET/PATCH /api/v1/tickets/{ticket}`
- `POST /api/v1/tickets/{ticket}/replies`
- `POST /api/v1/tickets/{ticket}/notes`
- `POST /api/v1/tickets/{ticket}/assign`
- `POST /api/v1/tickets/{ticket}/transition`
- `POST /api/v1/tickets/{ticket}/attachments`
- `GET /api/v1/knowledge/articles`
- `GET/POST/PATCH /api/v1/knowledge/articles/{article}`
- `POST /api/v1/knowledge/articles/{article}/submit-review`
- `POST /api/v1/knowledge/articles/{article}/publish`
- `POST /api/v1/knowledge/articles/{article}/feedback`
- `GET /api/v1/reports/support`

Every endpoint needs policy checks, validation rules, pagination limits, consistent error format, correlation IDs, and OpenAPI documentation.

### 8.2 Suggested screens

- Support dashboard.
- Ticket list with saved filters and bulk actions.
- Ticket detail timeline with public/internal composer.
- Chat inbox and conversation detail.
- Knowledge base home, category, article, editor, review queue, and version history.
- SLA/queue/team configuration.
- Notification preferences and delivery logs.
- Reports/export center.
- Audit log viewer.

## 9. Implementation plan

### Phase 0 — Discovery and decisions

- Recover missing source files and historical decision records.
- Confirm utility boundaries, actors, organizations, channels, and data-retention policy.
- Finalize status matrices, permissions, SLA policy, notification matrix, and public/private visibility rules.

### Phase 1 — Foundation

- Implement organization scope, policies, permissions, audit events, attachments, notifications, and common API conventions.
- Add factories, seeders, test helpers, queues, feature flags, and OpenAPI baseline.

### Phase 2 — Ticket core

- Implement ticket entities, lifecycle, replies/notes, assignments, attachments, notifications, search, and audit history.
- Add authorization and feature tests before adding automation.

### Phase 3 — Knowledge base

- Implement spaces/categories/articles, versioning, review/publication workflow, search, feedback, and ticket linking.

### Phase 4 — Chat

- Implement conversations, participants, messages, read state, internal notes, ticket linking, and polling.
- Add broadcast provider only after baseline behavior is stable and tested.

### Phase 5 — SLA, automation, and integrations

- Add business calendars, timers, escalation jobs, email/API intake, FCM, external synchronization, import/export, and reporting.

### Phase 6 — Hardening

- Run security review, authorization matrix tests, load tests, attachment abuse tests, queue retry tests, backup/restore tests, and user acceptance testing.

## 10. Test coverage checklist

- Organization and role data isolation.
- Every permission and policy boundary.
- Ticket creation through each enabled channel.
- Idempotent API/email ingestion.
- Public reply versus internal note leakage prevention.
- Assignment, reassignment, merge, split, reopen, and auto-close.
- SLA start, pause, resume, breach, holiday, and timezone behavior.
- Notification preferences, retries, and failure handling.
- Chat participant access, read state, duplicate sends, and real-time fallback.
- Documentation draft, review, publish, unpublish, version comparison, and search visibility.
- Attachment validation, authorization, retention, and failed upload cleanup.
- Audit completeness and export authorization.
- Performance for ticket lists, message timelines, and knowledge search.

## 11. Decision register to complete

| ID | Decision required | Owner | Status |
|---|---|---|---|
| DEC-001 | Define “chat” variants: direct, team, ticket-linked, customer live chat | Product/Architecture | Open |
| DEC-002 | Confirm internal, customer-facing, or dual documentation | Product | Open |
| DEC-003 | Confirm enabled ticket intake channels | Product/Integration | Open |
| DEC-004 | Select real-time transport and fallback | Architecture | Open |
| DEC-005 | Define SLA targets, business calendars, pause rules, escalations | Support/Product | Open |
| DEC-006 | Define message/article/ticket retention and deletion policy | Security/Compliance | Open |
| DEC-007 | Decide AI features and human approval/privacy controls | Product/Security | Open |
| DEC-008 | Confirm search engine and indexing strategy | Architecture | Open |
| DEC-009 | Confirm external notification providers and templates | Integration | Open |
| DEC-010 | Recover and reconcile historical FRS/conversation artifacts | Project owner | Open |