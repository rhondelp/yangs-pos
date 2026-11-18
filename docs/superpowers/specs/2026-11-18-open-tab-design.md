# Open Tab (Table Sessions and Orders) – Design

Approved in conversation on 2026-11-18. The owner answered: split partial cancels, use business-day numbering (opens 8 PM, 06:00 cutoff), and include cancelling an empty tab.

## Intent
A cashier opens a tab on a table, sends several rounds of orders to it, edits or cancels items within their permissions, and sees a running bill. This step builds the backend and API only; the POS screen is the next step. Payments come later. Nothing here may mark anything Paid.

## Data
- **`table_sessions`:** table, opened_by, opened_at, closed_at, closed_by, `status` (open | billing | closed | cancelled), `business_date`, notes.
  - Partial unique index: `UNIQUE (dining_table_id) WHERE status IN ('open','billing')`.
  - `billing`, `closed` and `closed_by` are reserved for the payment step.
- **`orders`:** reference `ORD-YYYYMMDD-NNNN` (unique), session, cashier (`created_by`), `status` (open | cancelled), `payment_status` (unpaid | partial | paid; always unpaid here), notes, `business_date`.
- **`order_items`:** order, product, nullable variant, `product_name` and `variant_name` snapshots, `unit_price` and `unit_cost` snapshots (cost nullable), quantity, `discount`, `line_total` (= price × quantity − discount), `status` (active | cancelled), `cancel_reason`, `cancelled_by`, `cancelled_at`, notes.
- **`order_reference_counters`:** `(business_date PK, last_number)`. The next number comes from one atomic upsert (`INSERT … ON CONFLICT DO UPDATE … RETURNING`), so it is race-safe. The unique index on `orders.reference` is the backstop.

## Rules
- **Opening a tab:**
  - The table must be active and Available or Reserved.
  - The table row is locked, then the table moves to Occupied (`TableStatusService`, system) and the session is created, all in one transaction.
  - A second open tab fails with "Table X already has an open tab", from the pre-check or, under a race, from the index.
- **Adding an order:**
  - The tab must be open, and the session row is locked.
  - Each line needs a sellable product and, if given, an option of that product. Quantity is 1–99.
  - Snapshots: the option's price if an option was picked, otherwise the product's; cost is the option's cost, else the product's cost, else null.
  - `StockHook::deductForOrder($order)` is called in the same transaction (a no-op until Phase 3).
- **Updating an item:**
  - Only while the tab is open and the item is active.
  - Raising the quantity and changing notes need `pos.create-order`.
  - Changing the discount needs `orders.discount`; the discount must be between 0 and price × quantity.
  - Lowering the quantity is refused: use cancel.
- **Cancelling an item:**
  - Needs `orders.cancel-item` and a reason. An optional quantity below the line's quantity splits the line: the original keeps the rest and a new cancelled line holds the cancelled quantity with the same snapshots.
  - Any discount on the original moves first, pro rata (bcmath, half-up) to the line it belongs to.
  - When every item in an order is cancelled, the order becomes `cancelled`.
  - `StockHook::itemCancelled($item)` is called in the transaction.
- **Cancelling a tab:** needs `pos.cancel-session`. Only allowed when the tab has no active items. The table goes back to Available.
- **Running bill:** gross (Σ price × quantity of active items), discounts, total, item count, a per-order breakdown, and cancelled items listed separately and excluded. All bcmath.
- **Business date:** `BusinessDate::for($time)`: the Manila calendar date of (time − cutoff), with the cutoff from `config('pos.business_day_cutoff')`, default `06:00`.
- **Logging:** every write is activity-logged inside its transaction: `session.opened`, `session.cancelled`, `order.created`, `order_item.updated`, `order_item.cancelled`.
- **Payment status:** never written here. A test runs the whole flow and asserts every order is `unpaid`.

## Divergences from ARCHITECTURE.md (brief wins; doc updated)
- Cancellation is item state (status, reason, who, when), not an `order_item_voids` table.
- The discount is an item column, not a `discounts` table.
- Orders carry their own `payment_status`.

## Out of scope
POS UI, payments and closing tabs, VAT, supervisor PIN approval, stock deduction (hook only), tab move/merge/split.
