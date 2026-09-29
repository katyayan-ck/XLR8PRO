# Spares — part master, stock, orders, consumption, spare requests

Workshop parts. Legacy tables (`xlr8_spare_*`) with thin models; the only admin screen today is **Spare requests**
(`Admin\Spares\SpareRequest\SpareRequestCrudController`: list, create, delete, `fetchParts` AJAX, `data` for the grid).
No spares service yet — a new flow should add `App\Services\Spares\*` rather than growing the controller.

## Models (`App\Models\Module\Spare`)
| Model | Table | Relations / scopes |
|---|---|---|
| `XlSpareMaster` | `xlr8_spare_master` | fillable `part_no`, `name`, `category_id`, `division_id`, `order_price`, `sale_price`, `order_qty`; `category()` / `division()` → legacy `EnumMaster`; `stocks()`, `orders()`, `consumptions()`; scopes `filterBySearch($v)`, `filterByCategory($c)`, `filterByDivision($d)` |
| `XlSpareStock` | `xlr8_spare_stock` | scope `forPartsInStores($partIds, $storeIds)` |
| `XlSpareOrder` | `xlr8_spare_order` | scope `forPartInStore($partIds, $storeId)` |
| `XlSpareConsumed` | `xlr8_spare_consumption` | `partMaster()`; scopes `betweenDates($from, $to)`, `forStores($ids)`, `filterBySearch()`, `filterByCategory()`, `filterByDivision()` |
| `XlSpareRequest` | `xlr8_spare_request` | `details()`; not data-scoped (its branch column `srv_brnch_id` is an id — the rebuild, D28, should store `branch_code`) |
| `XlSpareRequestDetail` | `xlr8_spare_req_details` | `spareRequest()`, `partMaster()`; scopes `active()`, `filterBySearch()`, `filterByCategory()`, `filterByDivision()` |
| `XlSpareClosure` | `xlr8_spare_closure` | — |

Stores are workshop locations — `OrgService::serviceBranches()` (locations with `is_workshop`); vehicle dropdowns on the
request form come from `VehicleService::segmentOptions()` / `modelOptions()` (DEC-060).

## Use cases
**Parts consumed at a workshop this month**
```php
XlSpareConsumed::query()->forStores([$storeId])->betweenDates(now()->startOfMonth(), now())->with('partMaster')->get();
```
**Part search for a picker** → `XlSpareMaster::query()->filterBySearch($q)->limit(20)->get(['id', 'part_no', 'name'])`
(return Select2 `{results:[{id,text}]}`; use `<x-ui.select :source=…>`).

## Gotchas
- `category()` / `division()` point at a legacy `EnumMaster`; new code should use KeyValue (`KeywordValueService`).
- Permissions: `SPR_*` (see `.ai/rules/admin-backpack.md`), checked inline in the controller.
