---
paths:
  - 'resources/views/components/**'
---

# Components

## Components and platform config changes update their guides
Changing a Blade component's props or behaviour (resources/views/components/**), public/js/xl-*.js, public/css/xl-*.css or config/platform.php (entities, settings seed pack, notify/docs config) must update the matching guide in the same change: tech-guides/platform/<utility>.md, tech-guides/platform/16-reference.md (settings, codes) or tech-guides/platform/ui-kit.md. Same standard as the app/** rule: nothing public in code without a guide entry.
