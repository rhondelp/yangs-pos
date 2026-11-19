# Suppliers, Inventory Items and Stock-in – Design

Approved 2026-11-19 (Phase 3, Step 14). Follows `docs/ARCHITECTURE.md` §2 (ERD), §3.4 (weighted average cost), §3.5 (units and recipes), §3.6 (accounting separation).

## Intent
Record what the bar buys and keeps, in the units it actually counts, so later steps can deduct stock on sale and report COGS, valuation and losses from real records.

## Owner decisions
- **No item type.** The brief's "product / supply" split is dropped. Cups, ice and straws are ordinary items that recipes consume. Cleaning or office supplies that are never sold are operating expenses (Phase 4), not inventory.
- **ARCHITECTURE names** are kept: `inventory_units` (the brief's unit conversions) and `recipe_components` (the brief's product–inventory links).

## Data
- **`suppliers`:** name (unique), contact person, phone, email, address, notes. Audited soft deletes (archive and restore).
- **`inventory_items`:**
  - name (unique), SKU (nullable, unique), base unit (`bottle`, `ml`, `pc`…);
  - `on_hand` `decimal(14,3)`, cached from the ledger;
  - `avg_unit_cost` `decimal(14,6)` per base unit;
  - `low_stock_level` `decimal(14,3)`;
  - audited soft deletes.
- **`inventory_units`:** item, name, `factor` (base units per unit, > 0). Unique per item.
  - The base unit is implicit (factor 1).
  - A unit may be removed only while no receipt line uses it; lines keep their own snapshots anyway.
- **`recipe_components`:**
  - product, optional product option (null = the product sold without an option), item, `quantity` (base units per one sold, > 0).
  - Unique per (product, option, item). Several components make a cocktail.
  - Stored and edited now; used for deduction in a later step.
- **`stock_receipts`:**
  - `reference` `SR-YYYYMMDD-NNNN` (shared counter);
  - supplier (nullable), invoice / reference number, `received_at`, `business_date`, `received_by`;
  - `total_cost` `decimal(12,2)`, notes.
  - Append-only in this step (no void yet).
- **`stock_receipt_items`:** receipt, item, unit (nullable = base), `unit_name` and `unit_factor` snapshots, `quantity` (in that unit), `base_quantity`, `line_cost` `decimal(12,2)`, `base_unit_cost` `decimal(14,6)`.
- **`inventory_movements`** (append-only, model guard and trigger):
  - item, `type` (`receipt` now; `sale`, `void_return`, `adjustment`, `receipt_void` later);
  - signed `quantity` (base units), `unit_cost` (6 dp), `total_cost` (2 dp), `balance_after`;
  - reason, note, polymorphic source (`stock_receipt_items` here), `business_date`, `created_by`, `created_at`.

## Rules
- **One writer.** `InventoryService` is the only code that writes movements or changes `on_hand` / `avg_unit_cost`, inside a DB transaction, locking the items by ascending id.
- **Stock-in (`InventoryService::receive`):**
  - per line, `base_quantity = quantity × factor` and `base_unit_cost = line_cost / base_quantity` (6 dp, half-up);
  - average cost: if `on_hand > 0`, `(on_hand × avg + qty × cost) / (on_hand + qty)`; otherwise the receipt's cost;
  - a receipt line for the same item twice is applied line by line, in order;
  - `total_cost = Σ line_cost`. One `receipt` movement per line, with `balance_after`.
  - Logs `stock.received` (reference, supplier, lines, total).
- **Validation:**
  - At least one line. Quantity > 0, up to 3 decimals. Line cost ≥ 0, money.
  - The unit must belong to the item. The item and supplier must be active.
  - `received_at` may not be in the future.
- **Accounting:** purchases are inventory, never operating expenses. `stock_receipts.total_cost` feeds the purchases report later, not P&L.
- **Ledger invariant:** for every item, `on_hand` = Σ `inventory_movements.quantity`.

## Permissions
- `inventory.view`: lists and detail pages.
- `inventory.manage`: suppliers, items, units, recipes.
- `inventory.stock-in`: create receipts.

## Screens and API
- **Screens:** Suppliers, Inventory items (detail with units and movement history), Stock-in (new receipt with lines; receipt list and detail), and a Recipe card on the product edit page. Management layout, 48 px targets, portrait and landscape tablets.
- **API (`/api/v1`):** suppliers CRUD (archive and restore), inventory items CRUD with units, `GET items/{id}/movements`, stock receipts list, show and create, product recipe get and put.

## Out of scope (later Phase 3 steps)
Receipt void, deduction on sale and its cost snapshot, void/cancel returns, manual stock-out and adjustments, low-stock notifications, inventory reports.
