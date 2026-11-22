# Step 16 – Inventory monitoring and reports (design)

Owner's step (2026-11-20). It replaces the earlier draft of this file, which covered alerts and product cost.
Covers YANGS_SPEC §4C "Inventory Monitoring" and the Phase 3 item "Low-stock monitoring". Notifications come in Phase 6.

## Intent

- The owner and stock staff should see, in one place, what is on hand and what it is worth, and what needs reordering.
- They should see every stock movement across items, so a missing bottle can be traced.
- Large manual stock-outs (possible loss or theft) should stand out.
- A future mobile app should read the same data through the API.

## Goals

1. **Stock levels page** (`/inventory`, the existing list, upgraded):
   - search by name or SKU;
   - a category filter;
   - a status filter: all, needs attention (low, out or negative), low, out, negative;
   - "Show archived" (archived items are hidden by default);
   - each row keeps its badge and value (on hand × average cost);
   - a valuation summary: total value, item count, and counts that are low, out or negative.
2. **Movement history page** (`/inventory/movements`, new):
   - every movement across items, newest first, paginated 50;
   - filters: item, type (receipt, receipt void, sale, void return, adjustment), business date from and to, user;
   - the **Adjustments** tab is the same page with `type=adjustment` preset, and shows the reason, note and unusual flag.
3. **Unusual adjustments:**
   - new setting `unusual_adjustment_amount` (₱, default `1000.00`; blank or 0 turns it off), edited on Settings;
   - a manual stock-out whose value is at or above it is stored with `is_unusual = true`, logged in `stock.adjusted`, and shown with an "Unusual" badge;
   - it fires `UnusualAdjustmentRecorded`.
4. **Events for Phase 6** (no listeners now):
   - `UnusualAdjustmentRecorded(movement)`;
   - `StockLevelCrossed(item, from, to, movement)` when a sale or stock-out moves an item into a worse state (in stock > low > out > negative), once per crossing.
5. **API:**
   - `GET /api/v1/inventory/valuation` returns the summary and per-item values, with the same filters as the page;
   - `GET /api/v1/inventory-movements` returns all movements, with the same filters as the page;
   - `GET /api/v1/inventory-items` gains `search`, `category_id` and `status`.

## Non-goals

- Notifications, a bell or an alerts page (Phase 6 listens to the events).
- Product cost tracking: moved to its own step after this one, with option A (current recipe cost and margin).
- PDF or Excel export (plan item 38).
- Alerts when an owner edits `low_stock_level` without a movement.

## Anti-goals

- A total that counts negative stock as negative money.
- An event for a rolled-back change.
- Filters that silently drop movements of archived items.

## Approaches

- **Category (owner picked 1):** add an optional `category_id` on `inventory_items` that points to the existing product categories. Rejected: a separate inventory category list, because it is a second list to maintain.
- **Crossing check (owner picked, earlier in this conversation):** the check lives in `InventoryService`, the only ledger writer. It runs in `deduct()` and `stockOut()`; `stockIn()` only raises stock, so it is skipped. Rejected: an `InventoryItem` observer (hidden logic, no movement context) and a scheduled scan (late, needs stored state).
- **Unusual flag stored, not computed:** the flag is set when the movement is inserted. This keeps it true to the threshold of that day. Movements are append-only, so the flag is never changed later. Rejected: comparing against today's setting at display time, because history would change when the setting does.

## Design

- **Migration:**
  - `inventory_items.category_id` is nullable, with a foreign key to `categories` and an index;
  - `inventory_movements.is_unusual` is a boolean, not null, default false.
  - The category is set on the item form as an optional "Category" select (`inventory.manage`); a change is logged in `inventory_item.updated` with the existing changed-fields logging. Archived categories still show on items that use them.
- **Valuation:** value per item = `max(on_hand, 0) × avg_unit_cost`, using bcmath and rounded to 2 dp per item. The total is the sum of the rounded rows, so the page adds up. Archived items count in the total only when "Show archived" is on.
- **Query:** `App\Services\Inventory\InventoryReportService`:
  - `levels(array $filters)` returns the filtered item query and the summary;
  - `movements(array $filters)` returns a paginated query;
  - the web controllers and API controllers share both.
  - The status filter runs in SQL with the same rules as `InventoryItem::stockStatus()`.
  - The date filter uses `business_date` (the day cut off at 06:00).
  - Movements of archived items are always included.
- **Validation:** `InventoryLevelFilterRequest` and `MovementFilterRequest` check the type list, dates (from ≤ to), and that ids exist. The setting is `nullable|regex money|max 99999999.99`.
- **Events:** plain Laravel events that implement `ShouldDispatchAfterCommit`, so a rollback sends nothing. The crossing compares each component against the running balance, so two lines of one item in one sale fire once per real crossing. `stock.went_negative` stays as it is.
- **Authorization:** both pages and the API endpoints need `inventory.view`. Users in the user filter are those who created a movement.
- **UI:** the existing `x-ui.*` components, the design system in `design-system/YANGS/MASTER.md`, and UI UX Pro Max for the filter bar and summary tiles. The filter bar is a GET form, and touch targets are 48 px or more. Check it at 768×1024 and 1024×768. Each row on the movements page links to the item and to its source (stock receipt or order).

## Tests (feature)

- **Valuation math:** cases with decimals, negative stock counted as 0, a total equal to the sum of rows, archived items included only on request.
- **Low-stock thresholds:**
  - status at, below and above the level;
  - level 0;
  - out and negative;
  - the attention filter.
- **Filters:**
  - search, category and status;
  - movement item, type, date range (business date across midnight) and user;
  - an archived item's movements;
  - invalid filter input refused.
- **Unusual:**
  - at the threshold, below it, and with it off;
  - the flag is stored and the event fires;
  - a rolled-back stock-out fires no event.
- **Crossing events:**
  - one event per crossing;
  - none while the item stays low;
  - recovery followed by a drop fires again.
- **API:** both endpoints, the same filters, `inventory.view` required.

## Left to the builder

Copy wording, icons, tile layout and tab styling within the design system.
