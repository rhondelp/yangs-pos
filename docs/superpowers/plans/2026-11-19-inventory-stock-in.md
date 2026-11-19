# Suppliers, Inventory Items and Stock-in Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Suppliers, inventory items with units, product recipes, and stock-in receipts that post an append-only ledger and keep a cached on-hand quantity and weighted-average cost.

**Architecture:** Seven tables from ARCHITECTURE §2. `InventoryService` is the single writer of `inventory_movements`, `on_hand` and `avg_unit_cost`. CRUD services (`SupplierService`, `InventoryItemService`, `RecipeService`) follow the `CategoryService` pattern. Web pages use the management layout and `x-ui` kit; the API mirrors them.

**Tech Stack:** Laravel 13, PostgreSQL, Blade + Alpine, bcmath.

**Spec:** `docs/superpowers/specs/2026-11-19-inventory-stock-in-design.md` (binding), `docs/ARCHITECTURE.md` §3.4–3.6, `CLAUDE.md`.

## Global Constraints
- Money totals `decimal(12,2)`; per-unit costs `decimal(14,6)`; quantities `decimal(14,3)`. bcmath only; `Money::round($v, 6)` for unit costs, half-up.
- `inventory_movements` and `stock_receipts` / `stock_receipt_items` are append-only (model guard + `reject_append_only_change()` trigger). Suppliers and items use `HasAuditedSoftDeletes`.
- Every write logs inside its transaction. Items are locked in ascending id order.
- Permissions: `inventory.view`, `inventory.manage`, `inventory.stock-in` (exist already).

## Review Focus
1. A receipt with two lines for the same item averages line by line (second line sees the first's quantity and cost). Test in Task 3.
2. Average cost when `on_hand` is negative or zero uses the receipt's cost. Test in Task 3.
3. A free line (cost 0, e.g. a promo case) is accepted and lowers the average correctly. Test in Task 3.
4. A unit from another item is refused. Test in Task 3.
5. After any sequence of receipts, `on_hand` equals the ledger sum for every item. Test in Task 3.

---

### Task 1: Schema and models
**Files:** migrations `create_suppliers_table`, `create_inventory_items_table` (+ `inventory_units`), `create_recipe_components_table`, `create_stock_receipts_table` (+ items), `create_inventory_movements_table`; models `Supplier`, `InventoryItem`, `InventoryUnit`, `RecipeComponent`, `StockReceipt`, `StockReceiptItem`, `InventoryMovement`. Test `tests/Feature/Inventory/InventorySchemaTest.php`.
- [ ] Tests: unique names; unit factor > 0; recipe quantity > 0 and unique per (product, option, item); movements, receipts and receipt lines refuse update/delete in model and DB; suppliers/items archive and restore with logs.

### Task 2: Suppliers, items, units, recipes
**Files:** `app/Services/Inventory/{SupplierService,InventoryItemService,RecipeService}.php`. Test `tests/Feature/Inventory/InventoryCatalogTest.php`.
- Interfaces: `SupplierService::create(array)/update(Supplier, array)/archive/restore`; `InventoryItemService::create(array)/update(InventoryItem, array)/archive/restore`, `saveUnits(InventoryItem, list<{id?, name, factor}>)` (refuses removing a unit used by a receipt line); `RecipeService::save(Product, ?ProductVariant, list<{inventory_item_id, quantity}>)` (replaces the set, logs old and new).
- `on_hand` and `avg_unit_cost` are never writable through these services.
- [ ] Tests for each, including logging and the used-unit refusal.

### Task 3: Stock-in
**Files:** `app/Services/Inventory/InventoryService.php`. Test `tests/Feature/Inventory/StockInTest.php`.
- `InventoryService::receive(User $by, array $input): StockReceipt`, with input `{supplier_id?, invoice_no?, received_at, notes?, lines: list<{inventory_item_id, inventory_unit_id?, quantity, line_cost}>}`. Needs `inventory.stock-in`.
- [ ] Tests: 2 cases × 24 at ₱1,440 → 48 bottles at ₱30.000000; average 10 @ ₱25 + 48 @ ₱30 → ₱29.137931; negative on-hand → receipt cost; same item twice; free line; foreign unit refused; inactive item/supplier refused; future date refused; reference `SR-YYYYMMDD-NNNN`; movements carry `balance_after`; `stock.received` logged; ledger sum = `on_hand`; receipt and movements append-only.

### Task 4: Screens
**Files:** controllers `Web/{Supplier,InventoryItem,StockReceipt}Controller`, Form Requests, views `suppliers/*`, `inventory/*`, `stock-receipts/*`, recipe card in `products/edit`, routes, `Navigation` (Inventory, Stock-in, Suppliers). Test `tests/Feature/Inventory/InventoryWebTest.php`.
- [ ] Tests: pages need their permissions; create/edit/archive flows; the stock-in form posts lines and shows the receipt; the recipe card saves; navigation follows permissions.

### Task 5: API
**Files:** `Api/V1/{Supplier,InventoryItem,StockReceipt,Recipe}Controller`, Resources, routes. Test `tests/Feature/Inventory/InventoryApiTest.php`.
- [ ] Tests: permissions; create/list/show; stock-in returns 201 with lines and new on-hand; recipe get/put.

### Task 6: Verify
- [ ] Playwright screenshots at 768×1024 and 1024×768 (stock-in form and receipt, item detail, recipe card), 48 px audit; full suite; build; docs; review.
