---
paths:
  - 'app/Http/Controllers/Admin/**'
---

# Admin

## Backpack operations used
Use List+Create+Update+Delete operations by default for CrudControllers; the Show operation is not used in this app.

## Backpack field/column style
Most CrudControllers override index()/store()/create()/update() and point at custom Blade views via setListView()/setCreateView()/setEditView() rather than using CRUD::field()/CRUD::column(). When the field/column DSL is used, use fluent syntax (CRUD::field('x')->type(...)), not array syntax.

## Row-level data scoping in Backpack
Reuse the App\Http\Controllers\Admin\Traits\ScopedCrud trait (implement getScopeType()) for branch/location/department-scoped list results; don't duplicate scoping logic in setupListOperation.
