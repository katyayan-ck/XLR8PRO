---
paths:
  - 'app/**'
---

# App

## Keep developer guides in step with code (models, services, utilities)
Every change to a model, service, facade, trait, event, job, component or config key must update its developer guide in the same change: docs/domains/*.md for business models and services (and docs/domains/reference.md for new or renamed models), docs/utilities/*.md plus docs/utilities/16-reference.md for platform utilities (result codes, events, settings, permissions), docs/utilities/ui-kit.md for shared UI pieces. New or changed public methods get their signature, return shape and an example; removed methods are removed from the guide; newly found defects are named with their BUG id. A public method that exists in code but not in a guide (or a guide describing behaviour the code no longer has) is a defect in the change.
