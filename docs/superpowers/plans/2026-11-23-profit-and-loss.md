# Financial Calculations and Profit and Loss Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A P&L statement with sales, COGS, expenses, inventory losses and net profit, plus period, product, category, cashier and payment-method breakdowns, for `finance.view`, on the web and under `/api/v1`.

**Architecture:** One read-only service, `App\Services\Finance\FinancialSummaryService`, runs SQL aggregates over the existing records (no new tables). Every figure is computed from one source table and dated by that table's own `business_date`. A Form Request validates the filters; thin web and API controllers call the service.

**Tech Stack:** Laravel 13, PHP 8.4, PostgreSQL, Blade + Tailwind, bcmath via `App\Support\Money`.

**Spec:** `docs/superpowers/specs/2026-11-23-profit-and-loss-design.md`

## Global Constraints

- Figures and sources exactly as in the spec's Definitions table. Only `closed` sessions are sales.
- Money: SQL `SUM` over `numeric`; any PHP arithmetic uses `Money::add/sub/percentOf` (bcmath). Never floats. Output strings with 2 decimals (`'0.00'` when empty).
- Inventory purchases never enter any profit figure.
- Voids are never subtracted from gross again; refunds never change COGS.
- Null `unit_cost` counts as `0`; `items_without_cost` = Σ (quantity − voided quantity) of such active lines in closed sessions.
- `finance.view` only (Owner has every permission; it is not in `Permission::cashierDefaults()`). Cashier gets 403 on web and API.
- Business logic in the service; validation in a Form Request; API output through a Resource; UI through `x-ui.*` components and `design-system/YANGS/MASTER.md`; 48 px touch targets.
- No `git commit` by the agent. The user commits at the end of the step.
- Run tests with `php artisan test --compact`; PowerShell syntax on this machine.

## Review Focus

1. **A range with no records** must return `'0.00'` everywhere, empty rows, `margin` null, and no division by zero. Test: Task 2, `test_an_empty_range_is_all_zero`.
2. **Variants** (bottle and bucket of the same product) must be separate product rows. Test: Task 2, `test_variants_are_separate_rows`.
3. **An archived product or category** sold in the range must still appear with its name. Test: Task 2, `test_archived_products_still_show`.
4. **A payment corrected twice** must count under the latest method only. Test: Task 2, `test_the_latest_correction_wins`.
5. **A 6-year range** must be refused with a clear message, and `by=foo` too. Test: Task 3, `test_filters_are_validated`.

---

## The fixture (shared by Tasks 1–3)

Trait `tests/Feature/Finance/Concerns/BuildsFinanceFixture.php`, method `buildFinanceFixture(): void`. Seed `RolesAndPermissionsSeeder`, `PaymentMethodSeeder`, `ExpenseCategorySeeder`. Users: `$this->owner` (Owner) and `$this->cashier` (Cashier). Build everything through the real services (`OrderService`, `BillService`, `PaymentService`, `VoidService`, `RefundService`, `PaymentCorrectionService`, `InventoryService`, `ExpenseService`, `TableSessionService`), the way `tests/Feature/Inventory/Concerns/SellsTrackedProducts.php` and `tests/Feature/Corrections/RefundServiceTest.php` do. Payments need fresh UUID `idempotency_key`s; GCash needs a `reference_no`.

**Business date 2026-11-20** (`travelTo('2026-11-20 21:00', 'Asia/Manila')`):

- Categories Beer, Cocktails, Food. Products:
  - Beer (Beer): price 100, cost 40, untracked;
  - Gin Tonic (Cocktails): price 150, tracked, recipe 1 shot of inventory item Gin;
  - Sisig (Food): price 250, cost null.
- Stock-in: 20 shots of Gin, line cost 1000.00, so the average is 50.00 and purchases are 1000.00.
- Tab A (T1, opened by the cashier):
  - order Beer ×4, then line discount 40 (`updateItem` by owner) → line total 360;
  - order Gin Tonic ×3 (unit_cost snapshot 50.00); order Sisig ×1;
  - order Beer ×2, cancelled whole (untracked); order Gin Tonic ×1, cancelled with `returnToStock: false` → **loss 50**;
  - cashier pays Cash 100;
  - owner voids Gin Tonic ×1 with return to stock (amount 150) and Beer ×1 wasted (amount 90) → **loss 40**;
  - `present`, bill discount 10 % → 82.00, so net total 738.00 (gross 850, discount_total 112);
  - cashier pays GCash 638 (closes the tab);
  - owner corrects the Cash 100 payment to GCash.
- Tab B (T2, owner): Beer ×2, owner pays Cash 200 (closes).
- Tab C (T3): Beer ×1, left open (not a sale).
- Tab D (T4): Beer ×1, item cancelled, tab cancelled (not a sale).
- Expenses: Electricity 1500.00; another of 999.00, voided.
- Stock-out: Gin 2 shots, `damaged` → **loss 100** (2 × 50).

**Business date 2026-11-21** (`travelTo('2026-11-21 21:00', 'Asia/Manila')`):

- Refund 50.00 from Tab B's Cash payment (refund method Cash).
- Expense Rent 5000.00.

Expected values (hand-calculated):

| Range | Gross | Disc. | Refunds | Net sales | COGS | Gross profit | Expenses | Losses | Net profit | Purchases | No cost |
|---|---|---|---|---|---|---|---|---|---|---|---|
| 11-20 → 11-21 | 1050.00 | 112.00 | 50.00 | 888.00 | 300.00 | 588.00 | 6500.00 | 190.00 | -6102.00 | 1000.00 | 1 |
| 11-20 only | 1050.00 | 112.00 | 0.00 | 938.00 | 300.00 | 638.00 | 1500.00 | 190.00 | -1052.00 | 1000.00 | 1 |
| 11-21 only | 0.00 | 0.00 | 50.00 | -50.00 | 0.00 | -50.00 | 5000.00 | 0.00 | -5050.00 | 0.00 | 0 |

COGS: Tab A Beer 3 × 40 + Gin 2 × 50 + Sisig 1 × null = 220; Tab B 2 × 40 = 80.

---

### Task 1: The statement (`summary`)

**Files:**
- Create: `app/Services/Finance/FinancialSummaryService.php`
- Create: `tests/Feature/Finance/Concerns/BuildsFinanceFixture.php`
- Test: `tests/Feature/Finance/FinancialSummaryServiceTest.php`

**Interfaces:**
- Produces: `FinancialSummaryService::summary(string $from, string $to): array` with keys `gross_sales, discounts, refunds, net_sales, cogs, gross_profit, operating_expenses, inventory_losses, net_profit, inventory_purchases` (strings, 2 decimals) and `items_without_cost` (int). `$from`/`$to` are `Y-m-d` business dates, inclusive.
- Produces: trait `BuildsFinanceFixture` with `buildFinanceFixture(): void`, `$this->owner`, `$this->cashier`.

- [ ] **Step 1: Write the fixture trait and failing tests**
  - `test_the_statement_matches_the_hand_calculation`: build the fixture and assert all three rows of the table above, every key.
  - `test_inventory_purchases_are_not_operating_expenses`: `operating_expenses` for 11-20 is `'1500.00'` while `inventory_purchases` is `'1000.00'`, and `net_profit` equals `gross_profit − operating_expenses − inventory_losses` exactly.
  - `test_refunds_lower_sales_not_cogs`: the 11-21 row has refunds `'50.00'` and COGS `'0.00'`, and the full range COGS (`'300.00'`) equals the 11-20 COGS.
  - `test_voids_are_not_counted_twice`: the full range `net_sales` equals the Σ `net_total` of the closed sessions (938.00) minus refunds (50.00); the voided 150 + 90 appear nowhere.
- [ ] **Step 2: Run them and watch them fail**: `php artisan test tests/Feature/Finance/FinancialSummaryServiceTest.php --compact`. Expected: FAIL, class `FinancialSummaryService` not found.
- [ ] **Step 3: Implement `summary()`**. Use one query per source, each filtered with `whereBetween('<table>.business_date', [$from, $to])`. COGS and no-cost counts use active `order_items` of `closed` sessions, with the voided quantity from `order_voids` summed in a subquery: `(quantity − voided) × coalesce(unit_cost, 0)`. Losses are the sum of three parts:
  - `order_voids` where `returned_to_stock = false`: `quantity × coalesce(item.unit_cost, 0)`;
  - cancelled `order_items` where `returned_to_stock = false`: `quantity × coalesce(unit_cost, 0)`, dated by `orders.business_date`;
  - `inventory_movements` of type `adjustment`: `total_cost`.
  Wrap each sum as `bcadd((string) $sum, '0', 2)`, the same pattern as `ExpenseService::totals`. Inventory purchases reuse the `stock_receipts` sum (same source as `ExpenseService::totals`).
- [ ] **Step 4: Run them and watch them pass** (same command). Expected: 4 passed.

### Task 2: Breakdowns (`breakdown`)

**Files:**
- Modify: `app/Services/Finance/FinancialSummaryService.php`
- Test: `tests/Feature/Finance/FinancialBreakdownTest.php`

**Interfaces:**
- Consumes: the Task 1 fixture and its private per-source queries (refactor them to take a bucket or group expression instead of copying them).
- Produces: `public const BREAKDOWNS = ['day', 'week', 'month', 'year', 'product', 'category', 'cashier', 'payment_method'];`
- Produces: `breakdown(string $from, string $to, string $by): array{rows: list<array>, footer: array}`. Row and footer keys:
  - `day|week|month|year`: row `period` (`'2026-11-20'`, `'2026-W47'`, `'2026-11'`, `'2026'`), `label`, and `net_sales, cogs, gross_profit, operating_expenses, inventory_losses, net_profit`. Rows are ordered by period and omitted when empty. The footer has the same six keys and equals `summary()`.
  - `product`: row `product_id, variant_id, name` ("Beer", or "Beer – Bucket" for a variant), `category, quantity` (int), `sales, cogs, gross_profit, margin` (`?string`, `Money::percentOf(gross_profit, sales)`, null when sales are 0), and `quantity_without_cost` (int). Rows are ordered by sales, highest first. Footer: `sales, bill_discounts, refunds, net_sales, cogs, gross_profit`.
  - `category`: row `category_id, name, quantity, sales, cogs, gross_profit, margin, quantity_without_cost`; same footer as `product`.
  - `cashier`: row `user_id, name, tabs` (int), `sales` (Σ `net_total` by `closed_by`). Footer: `sales, refunds, net_sales`.
  - `payment_method`: row `payment_method_id, name, payments, refunds, net`. Payments use the effective method: the latest `payment_corrections.new_payment_method_id` by id, else `payments.payment_method_id`, for payments of closed sessions in range. Refunds use `refunds.payment_method_id` by refund date. Footer: `payments, refunds, net_sales`.
  - Line sales = `line_total − Σ order_voids.amount`. Bill discounts = Σ line sales − Σ `net_total`.

- [ ] **Step 1: Write the failing tests** (fixture range 11-20 → 11-21 unless stated):
  - `test_periods_add_up_to_the_statement`:
    - `day` rows are `2026-11-20` (net sales 938.00, net profit -1052.00) and `2026-11-21` (-50.00, -5050.00);
    - `week` is one row `2026-W47`, `month` is one row `2026-11`, `year` is one row `2026`, each with net profit -6102.00;
    - every footer equals `summary()` on the six shared keys.
  - `test_products_with_margins_reconcile_to_net_sales`:
    - Beer: qty 5, sales 470.00, COGS 200.00, gross profit 270.00, margin 57.45;
    - Gin Tonic: 2, 300.00, 100.00, 200.00, 66.67;
    - Sisig: 1, 250.00, 0.00, 250.00, 100.00, `quantity_without_cost` 1;
    - footer: sales 1020.00, bill discounts 82.00, refunds 50.00, net sales 888.00.
  - `test_categories`: Beer 470.00 / 200.00, Cocktails 300.00 / 100.00, Food 250.00 / 0.00; same footer.
  - `test_cashiers`: the cashier has 1 tab at 738.00 and the owner 1 tab at 200.00; footer refunds 50.00, net 888.00.
  - `test_payment_methods_use_the_corrected_method`: GCash payments 738.00, refunds 0.00, net 738.00; Cash 200.00, 50.00, 150.00; footer net sales 888.00.
  - Review Focus tests (each with its own small setup):
    - `test_an_empty_range_is_all_zero` (range 2026-01-01 → 2026-01-31);
    - `test_variants_are_separate_rows`;
    - `test_archived_products_still_show` (archive Beer through `ProductService` after the fixture);
    - `test_the_latest_correction_wins` (correct one payment twice).
- [ ] **Step 2: Run and watch them fail**: `php artisan test tests/Feature/Finance/FinancialBreakdownTest.php --compact`. Expected: FAIL, undefined method `breakdown`.
- [ ] **Step 3: Implement `breakdown()`**:
  - Period buckets are PostgreSQL expressions on each source's `business_date`: `business_date::text`, `to_char(business_date, 'IYYY-"W"IW')`, `to_char(business_date, 'YYYY-MM')` and `to_char(business_date, 'YYYY')`. Merge the per-source results in PHP by bucket key, then derive the profit lines with `Money`.
  - Product and category joins use `products` and `categories` without the soft-delete scope (`withTrashed` or a raw join).
  - Reject an unknown `$by` with `InvalidArgumentException`; the Form Request guards it first.
- [ ] **Step 4: Run Tasks 1–2 tests and watch them pass**: `php artisan test tests/Feature/Finance --compact`.

### Task 3: API and filter request

**Files:**
- Create: `app/Http/Requests/Finance/FinanceFilterRequest.php`
- Create: `app/Http/Controllers/Api/V1/FinanceController.php`
- Create: `app/Http/Resources/V1/FinanceReportResource.php`
- Modify: `routes/api.php` (inside the auth group, next to the Expenses block)
- Test: `tests/Feature/Finance/FinanceApiTest.php`

**Interfaces:**
- Consumes: `summary()`, `breakdown()`, `BREAKDOWNS`.
- Produces: `FinanceFilterRequest::filters(): array{from: string, to: string, by: string}`. Defaults are the same as `ExpenseFilterRequest::filters()`: `to` = today's business date, `from` = the first of `to`'s month, `by` = `'day'`. Rules:
  - `from`, `to`: `nullable|date_format:Y-m-d`; `to` also `after_or_equal:from`;
  - `by`: `nullable|in:` + `BREAKDOWNS`;
  - after validation, refuse ranges over 1826 days on `to` with "Choose a range of 5 years or less."
- Produces: routes `GET finance/summary` (`api.v1.finance.summary`) and `GET finance/breakdown` (`api.v1.finance.breakdown`), both `can:finance.view`. `FinanceReportResource` wraps the array unchanged. The summary response is `{"data": {...summary, "from", "to"}}`; the breakdown response is `{"data": {"by", "from", "to", "rows", "footer"}}`.

- [ ] **Step 1: Write the failing tests**:
  - `test_summary_and_breakdown_over_the_api`: fixture, `from=2026-11-20&to=2026-11-21`; `data.net_profit` is `'-6102.00'`; `by=product` gives `data.footer.net_sales` `'888.00'`.
  - `test_filters_are_validated`: `to` before `from` → 422 on `to`; `from=2020-01-01&to=2026-01-02` → 422 on `to` with the 5-year message; `by=foo` → 422 on `by`.
  - `test_cashiers_cannot_see_finance`: a Cashier gets 403 on both endpoints.
- [ ] **Step 2: Run and watch them fail**: `php artisan test tests/Feature/Finance/FinanceApiTest.php --compact`. Expected: FAIL, route not defined.
- [ ] **Step 3: Implement the request, the resource, the controller (`summary`, `breakdown`) and the routes.**
- [ ] **Step 4: Run and watch them pass** (same command).

### Task 4: Profit & loss page

**Files:**
- Create: `app/Http/Controllers/Web/FinanceController.php`
- Create: `resources/views/finance/index.blade.php`
- Modify: `routes/web.php` (`GET /finance`, `finance.index`, `can:finance.view`)
- Modify: `app/Support/Navigation.php` (add "Profit & loss" right after "Expenses", permission `Permission::FinanceView`, with an icon the `x-ui.icon` component already has)
- Modify: `tests/Feature/Ui/NavigationTest.php` (Owner list gains `'Profit & loss'` after `'Expenses'`)
- Test: `tests/Feature/Finance/FinanceWebTest.php`

**Interfaces:**
- Consumes: `FinanceFilterRequest::filters()`, `summary()`, `breakdown()`.

- [ ] **Step 1: Read `design-system/YANGS/MASTER.md` and `resources/views/expenses/index.blade.php`** (filter bar and stat tiles to copy). Use the `frontend-design` skill for the statement layout.
- [ ] **Step 2: Write the failing tests**:
  - `test_the_page_shows_the_statement_and_breakdown`: fixture, GET `/finance?from=2026-11-20&to=2026-11-21&by=product`. It must see in order: "Net sales", "₱888.00", "Cost of goods sold", "₱300.00", "Operating expenses", "₱6,500.00", "Inventory losses", "₱190.00", "Net profit", "-₱6,102.00" (use whatever negative format `Money::format` gives). It must also see "Inventory purchases", "₱1,000.00", "not in profit", "1 item sold without a cost", "Gin Tonic" and "66.67%".
  - `test_cashiers_cannot_open_the_page`: 403.
- [ ] **Step 3: Run and watch them fail**: `php artisan test tests/Feature/Finance/FinanceWebTest.php tests/Feature/Ui/NavigationTest.php --compact`.
- [ ] **Step 4: Build the page**:
  - Filter bar: From, To and "Show by" `x-ui.select` with labels Day, Week, Month, Year, Product, Category, Cashier, Payment method. It submits by GET, and every control is 48 px or more.
  - Statement card:
    - Sales section: Gross sales, Discounts, Refunds, **Net sales**;
    - COGS, then **Gross profit**;
    - Operating expenses and Inventory losses, then **Net profit**;
    - a negative net profit uses the danger colour from MASTER.md, plus a text sign, so colour is not the only cue.
  - Tiles beside the statement: Inventory purchases ("Stock-in, not in profit") and the no-cost note (shown only when the count is above 0).
  - Breakdown `x-ui.table` for the chosen "Show by", with its footer rows. The margin shows as "57.45%", or "–" when null.
  - Empty state when there are no rows.
- [ ] **Step 5: Run and watch them pass** (same command), then the whole suite: `php artisan test --compact`. Expected: all passing.
- [ ] **Step 6: Check the screens**: log in as the owner on the dev server, open `/finance` at 768×1024 and 1024×768, save screenshots to `docs/screenshots/step-18/`, and confirm that no control is under 48 px and the table scrolls inside its card.
- [ ] **Step 7: Update the docs**:
  - `docs/PROGRESS.md`: tick "Sales monitoring", "Cost calculations" and "Profit and loss reporting", and add a Step 18 section in the Step 17 format;
  - `docs/MODULES.md`: add a Finance entry;
  - run `graphify update .`.
