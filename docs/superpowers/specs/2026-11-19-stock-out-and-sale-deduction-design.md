# Stock-out and Automatic Sale Deduction – Design

Approved 2026-11-19 (Phase 3, Step 15). Builds on Step 14 and `docs/ARCHITECTURE.md` §3.3, §3.4, §3.6.

## Owner decisions
- **Returns:** a cancel or void asks **"Put back in stock?"**. Yes writes a `void_return` movement; no writes nothing, and the cost is an inventory loss (ARCHITECTURE §3.3, §3.6). **A refund never touches stock**: the drink was consumed. An unopened bottle that comes back after a refund is a manual adjustment (later step).
- **`allow_negative_stock`** (setting, default **off**): when off, an order or quantity raise that would take any item below zero is refused with "Only N bottle of X left." When on, it is allowed, the movement's balance goes negative, the item shows "Negative stock", and `stock.went_negative` is logged once per crossing. This replaces ARCHITECTURE §1's "never block" as the default.

## Rules
- **What deducts:** a sold line deducts when its product has `track_inventory` on and a recipe exists for the way it was sold (its option, or the product without an option). No recipe or tracking off: no movement, and cost stays the product's or option's `cost` (Step 7 behaviour).
- **Cost snapshot:** for a deducting line, `order_items.unit_cost` is the sum of component quantity × the item's current `avg_unit_cost`, rounded half-up to 2 dp. It is read under the same lock that writes the sale movement.
- **Sale movement:** one `sale` movement per line and component. The quantity is minus (component quantity × line quantity); `unit_cost` is the item's average; the source is the order item.
- **Single writer and locks:** `InventoryService` does all of it inside the caller's transaction. The lock order is the session (already held), then inventory items in ascending id. Two tablets selling the last bottle cannot both succeed when negative stock is off.
- **Quantity raise:** deducts the extra units the same way, and re-checks negative stock.
- **Cancel (before payment) and void (after payment):**
  - with `return_to_stock: true`, each component gets a `void_return` movement for the returned units at the cost of the line's sale movement, re-averaged per ARCHITECTURE §3.4;
  - with `false`, there is no movement;
  - the choice is stored as `order_items.returned_to_stock` for cancels and `order_voids.returned_to_stock` for voids, so reports can value losses.
  - It only matters when the line deducted stock (`tracks_stock`); otherwise it is ignored.
- **Manual stock-out** (`inventory.adjust`):
  - `InventoryService::stockOut(item, by, {quantity, unit?, reason, note?})`;
  - reasons: `damaged`, `expired`, `missing`, `internal_use`, `other`; a note is required for `other`;
  - writes an `adjustment` movement at the current average and logs `stock.adjusted`;
  - it may never take stock below zero, whatever the setting.
- **Rollback:** everything runs in the order's transaction. If anything fails, the order and its movements both roll back.

## Screens and API
- **Item page:** a Stock-out card (quantity, unit, reason, note).
- **Movements:** show the reason.
- **POS and bill screens:** the cancel and void dialogs get a "Put back in stock" checkbox (default on) for lines that track stock.
- **Settings:** an "Allow selling below zero" checkbox.
- **API:**
  - `POST inventory-items/{id}/stock-outs`;
  - the cancel and void endpoints accept `return_to_stock`;
  - order replies carry `warnings` when stock went negative.

## Out of scope
Stock counts, "found" adjustments, receipt void, low-stock notifications, reports.
