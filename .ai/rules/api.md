---
paths:
  - 'app/Http/Controllers/Api/**'
---

# Api

## Authorization call site
Enforce authorization via manual ownership checks in the method body or route middleware, not via $this->authorize()/Gate::/->can(). BaseController exposes an authorize() helper but it is not actually used anywhere — its presence is not a signal to start using it.

## Typed input retrieval
Retrieve request input via $request->input(), dynamic properties, or the validate() return array. Do not use typed input helpers like $request->string()/->integer()/->enum().

## Route model binding
Fetch models explicitly with Model::findOrFail() (or query builder firstOrFail()) inside the method body; do not rely on implicit route-model binding.
