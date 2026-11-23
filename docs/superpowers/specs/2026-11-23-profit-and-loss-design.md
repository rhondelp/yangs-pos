# Financial Calculations and Profit and Loss – Design (Step 18)

## Intent

The owner needs one trustworthy picture of how the bar is doing: what was sold, what it cost, what was spent, and
what is left. Every figure comes from real records, has exactly one source, and is never counted twice
(ARCHITECTURE §3.6). This step also covers the Phase 4 "Sales monitoring" item (sales by period, product, category,
cashier and payment method).

## Goals

- `App\Services\Finance\FinancialSummaryService` computes, for a date range:
  - Gross sales, Discounts, Refunds, **Net sales**
  - **COGS** (cost snapshots), **Gross profit**
  - **Operating expenses** (`expenses` table), **Inventory losses**, **Net profit**
  - Inventory purchases, shown for information only ("Stock-in, not in profit")
  - Count of sold items without a cost snapshot
- Breakdowns: day, week, month, year; product (with margin), category (with margin), cashier, payment method.
- Web page and API for `finance.view` (Owner has it; Cashier does not, and it is not a Cashier default).
- Feature tests with hand-calculated fixtures.

## Non-goals

- Payroll cost (Phase 5 adds a "Payroll" line to operating costs).
- VAT (not built; no `vat_amount` column yet).
- Current recipe cost and margin per product (the deferred "Product cost tracking" step). Margins here are
  historical, from sale-time snapshots.
- Charts, PDF and Excel export (Phase 6).
- Cash basis or cash drawer reports.

## Anti-goals

- A purchase counted as an expense, or counted in COGS when bought.
- A void subtracted from sales a second time (gross already excludes voided units).
- A refund that changes COGS (drinks served stay served).
- Product, category, cashier or method tables that do not add up to the same net sales as the summary.
- Any figure recomputed from current prices or costs.

## Definitions (owner-approved, with ARCHITECTURE §3.6)

Only `closed` table sessions are sales. Cancelled tabs and cancelled order items (removed before payment) count
nowhere. Each figure is dated by its own `business_date`.

| Figure | Source | Dated by |
|---|---|---|
| Gross sales | Σ `table_sessions.gross_total` (closed) | session `business_date` |
| Discounts | Σ `table_sessions.discount_total` (line + bill discounts) | session |
| Refunds | Σ `refunds.amount` | refund `business_date` |
| **Net sales** | gross − discounts − refunds | |
| COGS | Σ `order_items.unit_cost × (quantity − voided quantity)`, active items of closed sessions | session |
| **Gross profit** | net sales − COGS | |
| Operating expenses | Σ `expenses.amount`, not voided | `expense_date` |
| Inventory losses | wasted voids (`order_voids.returned_to_stock = false`: quantity × line `unit_cost`) + wasted cancels (cancelled `order_items` with `returned_to_stock = false`: quantity × `unit_cost`) + stock-out adjustments (Σ `inventory_movements.total_cost`, type `adjustment`) | void / order / movement `business_date` |
| **Net profit** | gross profit − operating expenses − inventory losses | |
| Inventory purchases | Σ `stock_receipts.total_cost` | receipt `business_date`; **never in profit** |

- A void with return to stock is out of both sales and COGS. A wasted void is out of sales and COGS, and its cost
  moves to inventory losses. A void is never subtracted again (gross already excludes it).
- A tracked item cancelled before payment without going back to stock was poured: its cost is an inventory loss,
  dated by its order's `business_date` (builder call; found while planning, ARCHITECTURE §3.6 missed it). A void of an
  untracked line always has `returned_to_stock = false` (VoidService), so its snapshot cost is a loss too.
- A refund lowers net sales only, on the day it is given. It may fall in a later range than its sale; that is
  correct (the money went back that day).
- `unit_cost` null (product without a recipe) counts as ₱0, and the page says "N items sold without a cost".
- Inventory losses are their own line (owner call: "the best you can do"). The spec formula
  `Net = Gross profit − Operating expenses` holds with operating costs = expenses + losses (ARCHITECTURE §3.6).

## Breakdowns

All in SQL aggregates (PostgreSQL `numeric`, exact), final sums with bcmath. Every table ends with the rows needed to
reach the summary's net sales.

- **Line sales** (per order item) = `line_total − Σ order_voids.amount`. Void amounts are cumulative differences of
  `RunningBill::lineTotal`, so the sum is exact. This is sales after line discounts, before bill discounts.
- **Bill discounts** = Σ line sales − Σ `net_total` (closed sessions in range).
- **Product / category:** quantity sold (quantity − voided), line sales, COGS, gross profit, margin
  (`gross profit ÷ line sales`, blank when sales are ₱0). Grouped by `product_id` and `product_variant_id` (a bottle and a
  bucket have different prices and margins), labelled with the snapshot names of the latest sale (builder call); category from the product (archived ones included). Footer: total line sales − bill discounts − refunds = net
  sales.
- **Cashier:** closed sessions by `closed_by` (who took the final payment): tabs, net total. Footer: − refunds =
  net sales.
- **Payment method:** Σ `payments.amount` of closed sessions in range, by **effective** method (latest
  `payment_corrections.new_payment_method_id`, else the original), minus Σ `refunds.amount` by the refund's method and
  date. Totals to net sales.
- **Day / week / month / year:** one row per bucket (ISO week, Monday start) with net sales, COGS, gross profit,
  expenses, losses, net profit; each figure bucketed by its own date. Buckets with no records are omitted.

## Screens

- Menu item "Profit & loss" (`finance.view`), route `/finance`.
- Filters: From / To (default this month so far, business date), "Show by" select
  (Day, Week, Month, Year, Product, Category, Cashier, Payment method; default Day).
- Statement card: the P&L lines in order, as a statement (sales section, cost section, profit lines), with the
  "items without a cost" note and the inventory purchases tile beside it.
- Breakdown table below for the chosen "Show by", with its reconciling footer.
- Reuses the Blade components, layout and design system (`design-system/YANGS/MASTER.md`); 48 px controls;
  checked at 768×1024 and 1024×768.

## API

- `GET /api/v1/finance/summary?from&to` → the statement figures.
- `GET /api/v1/finance/breakdown?from&to&by=day|week|month|year|product|category|cashier|payment_method` → rows and
  footer.
- Both `can:finance.view`. Money as strings with 2 decimals. Form Request validates dates (`to ≥ from`, an end date
  alone starts on the first of its month, same rule as expenses) and `by`. Range capped at 5 years.

## Approaches considered

1. **Read-time SQL aggregates over the existing records (picked).** No new tables; figures always match the source
   records; the load (one bar, a few hundred lines a night) is small for indexed `business_date` queries.
2. Nightly summary table per business day. Faster at scale, but adds a job, a second copy of the truth, and
   restatement work when a refund or late expense lands. Not needed at this load.
3. Compute in PHP from loaded models. Simplest to write, but loads every order item for a year range; SQL sums are
   both shorter and faster.

## Tests (hand-calculated fixtures)

One fixture day with written-out expected numbers, covering:
- a closed tab with line discount and bill discount; a cancelled item; a cancelled tab; an open tab (not counted);
- a void returned to stock and a wasted void;
- a refund on a later day (lowers that day's net sales, COGS unchanged);
- an item with no cost snapshot;
- an expense, a voided expense, a stock-in (in purchases, not in expenses or COGS), a stock-out adjustment;
- a payment corrected to another method.

Assertions: every statement line; each breakdown's rows and that its footer equals net sales; net profit identity;
inventory purchases absent from every profit figure; permission checks (Owner yes, Cashier 403) for web and API.

## Left to the builder

Query shapes, index additions if an `EXPLAIN` shows a need, view markup within the design system, and how the period
buckets are labelled.
