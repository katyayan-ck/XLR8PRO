# Router & Indexing Blueprint

## Overview

This document provides a fast, top-level navigation map linking development and architecture requests to the relevant distributed `.clinerules/` reference catalogs.

---

## Catalog Index

| Catalog | File | Scope / Contents |
|---|---|---|
| **Core Architecture** | `00-core.md` | Core services blueprint (`OrgService`, `PersonService`). |
| **Models Registry** | `02-models.md` | ~140 Eloquent models across Admin, IAM, CRM, Vehicle, Pricing, Booking, Spares, Utilities. |
| **Services Registry** | `03-services.md` | 51 service classes with method signatures, caching patterns, dependencies, and business rules. |
| **Controllers Registry**| `04-controllers.md` | Backpack CRUD controllers and REST API V1 endpoints with permissions and routes. |
| **Jobs, Commands & I/O**| `05-jobs-commands-io.md`| Queue jobs, Artisan commands, Excel importers (`app/Imports/`), and exporters (`app/Exports/`). |
| **Routes & Config** | `06-routes-config.md` | Web, API, and Backpack route groups; application configuration reference (`config/`). |
| **Views, Menus & UI** | `07-views-menus.md` | Backpack admin sidebar menu, custom CRUD buttons/columns/fields, and domain views. |

---

## Domain Request Routing Map

| When working on... | Consult Reference Catalogs |
|---|---|
| **Users, Persons, Employees, Hierarchy** | `02-models.md` (§2), `03-services.md` (§1, §5), `04-controllers.md` (§1), `05-jobs-commands-io.md` (§3) |
| **Vehicle Master (Segment/Model/Variant)** | `02-models.md` (§3), `03-services.md` (§3), `04-controllers.md` (§1), `.clinerules.md` (§4.1-4.3) |
| **Accessories Catalogue** | `02-models.md` (§3), `03-services.md` (§3), `05-jobs-commands-io.md` (§2, §4) |
| **Vehicle Pricing Pipeline (7 Stages)** | `02-models.md` (§4), `03-services.md` (§4), `04-controllers.md` (§1, §2), `05-jobs-commands-io.md` (§1), `07-views-menus.md` (§2) |
| **Authentication & IAM (OTP, RBAC, FCM)** | `02-models.md` (§5), `03-services.md` (§1, §2), `04-controllers.md` (§2), `06-routes-config.md` (§2) |
| **CRM, Quotations & Booking Engine** | `02-models.md` (§6, §7), `03-services.md` (§5), `04-controllers.md` (§1), `07-views-menus.md` (§2) |
| **Workshop Spares & Reports** | `02-models.md` (§7), `04-controllers.md` (§1), `07-views-menus.md` (§2) |
| **Backpack Admin UI, Sidebar & Fields** | `07-views-menus.md` (§1, §3), `04-controllers.md` (§1) |
| **Queue Workers & Console Tasks** | `05-jobs-commands-io.md` (§1, §2), `06-routes-config.md` (§1) |
| **Config & Environment Variables** | `06-routes-config.md` (§2), `.env.example` |

