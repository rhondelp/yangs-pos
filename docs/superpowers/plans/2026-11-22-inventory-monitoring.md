# Inventory Monitoring and Reports Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** One stock-levels page with valuation and filters, one cross-item movement history (with an Adjustments tab), stored "unusual" flags on large stock-outs, after-commit events for Phase 6, and matching API endpoints.

**Architecture:** A new read-only `InventoryReportService` builds the filtered item query, the valuation summary and the paginated movement query; web and API controllers share it. `InventoryService` (the only ledger writer) gains the unusual flag in `stockOut()` and a crossing check in `deduct()` and `stockOut()`, dispatching events that implement `ShouldDispatchAfterCommit`.

**Tech Stack:** Laravel 13, PHP 8.4, PostgreSQL, Blade + Tailwind + Alpine, PHPUnit feature tests.

**Spec:** `docs/superpowers/specs/2026-11-20-inventory-monitoring-design.md` (binding), `CLAUDE.md`, `design-system/YANGS/MASTER.md`.

## Global Constraints

- Money: bcmath only. Value per item = `max(on_hand, 0) × avg_unit_cost`, rounded to 2 dp per item; total = sum of rounded rows.
- Status order (worse to the right): `in_stock` < `low_stock` < `out_of_stock` < `negative_stock`. Same rules as `InventoryItem::stockStatus()`.
- Dates filter on `business_date` (cutoff 06:00, `Asia/Manila`), never `created_at`.
- Movements are append-only: `is_unusual` is set on insert, never updated.
- Setting `unusual_adjustment_amount`: default `'1000.00'`; `''` or `0` turns it off; `nullable|regex money|max 99999999.99`.
- Events implement `Illuminate\Contracts\Events\ShouldDispatchAfterCommit`. No listeners in this step.
- Every page and endpoint in this step needs `inventory.view`. Movements of archived items are always included.
- UI: existing `x-ui.*` components, touch targets ≥ 48 px, filter bars are GET forms, checked at 768×1024 and 1024×768.
- No new packages. No `git commit`: each task ends with the files staged for the user's review, not committed.

## Review Focus

1. Search text containing `%` or `_` (e.g. `50%`) matches literally, not as a wildcard. Test in Task 2.
2. A negative-stock item adds ₱0 to the total but still counts in `negative` and in the attention filter. Test in Task 2.
3. Page 2 of a filtered movement list keeps its filters (`withQueryString`). Test in Task 3.
4. A stock-out that takes an item from low straight to zero fires one event (`low_stock` → `out_of_stock`), and one that skips levels fires one event with both ends. Test in Task 4.
5. Raising the unusual threshold later leaves old flags unchanged. Test in Task 4.

---

### Task 1: Schema, item category and shared status/value helpers

**Files:**
- Create: `database/migrations/2026_11_22_100000_add_monitoring_columns_to_inventory.php`
- Modify: `app/Models/InventoryItem.php`, `app/Services/Inventory/InventoryItemService.php` (`FIELDS`), `app/Http/Requests/Inventory/SaveInventoryItemRequest.php`, `app/Http/Resources/V1/InventoryItemResource.php`, `resources/views/inventory/show.blade.php` (Details form + `$value`), `resources/views/inventory/index.blade.php` (New item form)
- Test: `tests/Feature/Inventory/InventoryLevelsTest.php` (new)

**Interfaces:**
- Produces:
  - `InventoryItem::STATUS_RANK = ['in_stock' => 0, 'low_stock' => 1, 'out_of_stock' => 2, 'negative_stock' => 3]`
  - `static InventoryItem::statusFor(string $onHand, string $lowLevel): string` (no `archived`); `stockStatus()` calls it after its trashed check.
  - `InventoryItem::stockValue(): string` (2 dp, `'0.00'` when on hand ≤ 0)
  - `InventoryItem::category(): BelongsTo` (with trashed); `category_id` fillable
  - Resource adds `category_id`, `category` (name or null), `value`.

- [ ] **Step 1: Write failing tests** in `InventoryLevelsTest` (setUp like `StockOutTest`):
  - `test_value_is_on_hand_times_average_rounded_per_item`: 3.5 bottles at avg 33.333333 → `'116.67'`.
  - `test_negative_and_zero_stock_are_worth_nothing`: `'0.00'` for both.
  - `test_status_for_thresholds`: `statusFor('5','5') === 'low_stock'`, `('5.001','5') === 'in_stock'`, `('0','5') === 'out_of_stock'`, `('-1','5') === 'negative_stock'`, `('1','0') === 'in_stock'`, `('0','0') === 'out_of_stock'`.
  - `test_an_item_can_have_a_category_and_the_change_is_logged`: PUT `inventory.update` with `category_id` → saved; `inventory_item.updated` log has `old.category_id = null`, `new.category_id = id`.
  - `test_an_archived_category_is_kept_but_not_newly_chosen`: item with category A, archive A; saving the item with `category_id = A` passes; another item choosing A gets error `category_id` "Choose a category that is in use."; a non-existent id is refused.
- [ ] **Step 2: Run** `php artisan test --filter=InventoryLevelsTest` → FAIL (no column / method).
- [ ] **Step 3: Migration**: `inventory_items.category_id` nullable `foreignId` → `categories`, indexed; `inventory_movements.is_unusual` boolean not null default false. `down()` drops both.
- [ ] **Step 4: Model, request, service, resource, views.** Request rule: `nullable|integer|exists:categories,id` plus a closure that fails with the message above when the category is archived and differs from the item's current `category_id`. Item forms get an optional `x-ui.select name="category_id" :label="__('Category')" :placeholder="__('None')"` listing active categories plus the item's current one (archived shown as "Name (archived)"). Show page `$value` uses `stockValue()`.
- [ ] **Step 5: Run** the filter again → PASS; run `php artisan test --filter=Inventory` → all pass.
- [ ] **Step 6: Stage** the files above.

### Task 2: Report service, stock-levels page and valuation API

**Files:**
- Create: `app/Services/Inventory/InventoryReportService.php`, `app/Http/Requests/Inventory/InventoryLevelFilterRequest.php`, `app/Http/Controllers/Api/V1/InventoryReportController.php`
- Modify: `app/Http/Controllers/Web/InventoryItemController.php` (`index`), `app/Http/Controllers/Api/V1/InventoryItemController.php` (`index`), `resources/views/inventory/index.blade.php`, `routes/api.php`
- Test: `tests/Feature/Inventory/InventoryLevelsTest.php`

**Interfaces:**
- Consumes: `statusFor`, `stockValue`, `category()` from Task 1.
- Produces:
  - `InventoryLevelFilterRequest::filters(): array{search: ?string, category_id: ?int, status: ?string, archived: bool}`; `status` ∈ `attention|low|out|negative`.
  - `InventoryReportService::levels(array $filters): array{items: Collection<InventoryItem>, summary: array{total_value: string, item_count: int, low: int, out: int, negative: int}}` — summary covers the filtered rows.
  - Route `GET /api/v1/inventory/valuation` → `InventoryReportController@valuation`, name `inventory.valuation`, in the `can:inventory.view` group.

- [ ] **Step 1: Write failing tests:**
  - `test_the_total_is_the_sum_of_rounded_rows`: two items worth `116.67` and `0.01` (from 3-dp × 6-dp values) → `total_value = '116.68'`.
  - `test_archived_items_count_only_when_asked`: archived item absent from items and total by default; present with `archived = true`.
  - `test_status_filters_follow_the_badge_rules`: items in stock, at level, out, negative → `low` returns the at-level one; `out` the zero one; `negative` the below-zero one; `attention` the three.
  - `test_a_negative_item_counts_as_negative_but_adds_nothing` (Review Focus 2): summary `negative = 1`, total unchanged.
  - `test_search_matches_name_or_sku_literally` (Review Focus 1): items "Gin 50%" and "Gin 500" → `search=50%` returns only the first; `search` by SKU, case-insensitive.
  - `test_category_filter`.
  - `test_invalid_filters_are_refused`: `status=bad`, `category_id=999999` → 422 on the API, errors on the web page.
  - `test_the_stock_levels_page_shows_summary_and_filters`: GET `/inventory?status=attention` → 200, sees the total, the counts, and only matching rows.
  - `test_the_valuation_api_returns_summary_and_items`: JSON `summary.total_value`, `data.*.value`, same filters; 403 without `inventory.view`.
  - `test_the_items_api_accepts_search_category_and_status`.
- [ ] **Step 2: Run** `php artisan test --filter=InventoryLevelsTest` → FAIL.
- [ ] **Step 3: Implement.** Search: `ilike` on `name` or `sku` with `%`, `_` and `\` escaped. Status SQL: negative `on_hand < 0`; out `on_hand = 0`; low `on_hand > 0 AND on_hand <= low_stock_level`; attention `on_hand <= low_stock_level` (the DB check keeps `low_stock_level >= 0`). `archived` → `withTrashed()`. Summary counts use `stockStatus()` over the fetched rows; total via `bcadd` of `stockValue()`. Unpaginated, ordered by name, with `units` and `category` loaded — mark `// shortcut: unpaginated, fine for a bar's item list; paginate past ~500 items`. Valuation response: `InventoryItemResource::collection($items)->additional(['summary' => $summary])`.
- [ ] **Step 4: Page.** Summary tiles (`x-ui.stat-card`: Stock value, Items, Low, Out, Below zero). GET filter bar: search input, category select, status select (All / Needs attention / Low / Out / Below zero), "Show archived" checkbox, Show button. Add a Category column. Keep the New item card. Links at the top: "Stock levels" (current) · "Movements" · "Adjustments" (routes from Task 3 — add the links in Task 3).
- [ ] **Step 5: Run** the filter → PASS; `php artisan test --filter=Inventory` → all pass.
- [ ] **Step 6: Stage.**

### Task 3: Movement history page, Adjustments tab and movements API

**Files:**
- Create: `app/Http/Requests/Inventory/MovementFilterRequest.php`, `app/Http/Controllers/Web/InventoryMovementController.php`, `resources/views/inventory/movements.blade.php`
- Modify: `InventoryReportService` (add `movements`, `movementUsers`), `InventoryReportController` (add `movements`), `app/Http/Resources/V1/InventoryMovementResource.php`, `routes/web.php`, `routes/api.php`, `resources/views/inventory/index.blade.php` (tab links)
- Test: `tests/Feature/Inventory/MovementHistoryTest.php` (new)

**Interfaces:**
- Consumes: `InventoryReportService` from Task 2.
- Produces:
  - `MovementFilterRequest::filters(): array{item_id: ?int, type: ?string, from: ?string, to: ?string, user_id: ?int}`; `type` ∈ `receipt|receipt_void|sale|void_return|adjustment`; `to` `after_or_equal:from`; `item_id` `exists:inventory_items,id` (archived pass); `user_id` `exists:users,id`.
  - `InventoryReportService::movements(array $filters): LengthAwarePaginator` (50 per page, newest first by id, `withQueryString()`, loads `item`, `createdBy`, `source` with `StockReceiptItem => receipt`, `OrderItem => order.session`).
  - `InventoryReportService::movementUsers(): array<int, string>` — only users who created a movement.
  - Web route `GET /inventory/movements` name `inventory.movements`, registered **before** `/inventory/{item}`. API `GET /api/v1/inventory-movements` name `inventory-movements.index`. Both under `can:inventory.view`.
  - Resource adds `inventory_item_id`, `item` (name), `base_unit`, `is_unusual`; `reference` = receipt reference or the order's `reference` for an `OrderItem` source.

- [ ] **Step 1: Write failing tests:**
  - `test_all_movements_newest_first_across_items`.
  - `test_filters_by_item_type_and_user`.
  - `test_business_date_range_crosses_midnight`: `travelTo('2026-11-21 01:30', Asia/Manila)` stock-out → included by `from=to=2026-11-20`, excluded by `from=2026-11-21`.
  - `test_archived_items_movements_are_included`: archive the item; unfiltered list and `item_id=<archived>` both return its movements; the item select lists it.
  - `test_invalid_filters_are_refused`: `type=bogus`, `from=2026-11-21&to=2026-11-20`, `item_id=999999` → 422 (API) / session errors (web).
  - `test_page_two_keeps_the_filters` (Review Focus 3): 51 adjustments; page 1 next link contains `type=adjustment`.
  - `test_the_adjustments_tab_shows_reason_note_and_unusual`: GET `/inventory/movements?type=adjustment` shows "Damaged", the note, and an "Unusual" badge for a flagged row (insert via `stockOut` after Task 4, or `forceCreate` with `is_unusual = true` here).
  - `test_rows_link_to_item_and_source`: stock-in row links `stock-receipts.show`; sale row shows the order reference, linked to `transactions.show` only when the tab is closed and the user can `orders.view-all`.
  - `test_the_movements_api_filters_and_needs_inventory_view`.
- [ ] **Step 2: Run** `php artisan test --filter=MovementHistoryTest` → FAIL.
- [ ] **Step 3: Implement** the request, service methods, controllers, routes and resource changes.
- [ ] **Step 4: View.** Same three tab links as Task 2 (`aria-current="page"` on the active one; "Adjustments" = `?type=adjustment`). GET filter bar: item (all items incl. archived), type (hidden on the Adjustments tab), from, to, user. Table: time, item (link to `inventory.show`), type, quantity (+/−, colour), value, balance after, user, source link. On the Adjustments tab add Reason, Note and the "Unusual" `x-ui.status-badge` (add an `unusual` status to the badge component if missing). Empty state via `x-ui.empty-state`. Reuse the `$types` and `$reasons` label maps from `inventory/show.blade.php` — move them to `InventoryMovement::TYPE_LABELS` / `InventoryService::STOCK_OUT_REASONS` labels if both views need them.
- [ ] **Step 5: Run** the filter → PASS; `php artisan test --filter=Inventory` → all pass.
- [ ] **Step 6: Stage.**

### Task 4: Unusual stock-outs and stock-level crossing events

**Files:**
- Create: `app/Events/UnusualAdjustmentRecorded.php`, `app/Events/StockLevelCrossed.php`
- Modify: `app/Services/Inventory/InventoryService.php` (`stockOut`, `deduct`), `app/Services/Settings/SettingService.php` (`DEFAULTS`), `app/Http/Requests/Settings/UpdateSettingsRequest.php`, `resources/views/settings/edit.blade.php`
- Test: `tests/Feature/Inventory/StockAlertsTest.php` (new)

**Interfaces:**
- Consumes: `InventoryItem::statusFor`, `STATUS_RANK` from Task 1.
- Produces:
  - `UnusualAdjustmentRecorded(public InventoryMovement $movement)`
  - `StockLevelCrossed(public InventoryItem $item, public string $from, public string $to, public InventoryMovement $movement)`
  - Both `use Dispatchable, SerializesModels` and implement `ShouldDispatchAfterCommit`.
  - Setting key `unusual_adjustment_amount`, default `'1000.00'`.

- [ ] **Step 1: Write failing tests** (setUp: 48 bottles at ₱30, `low_stock_level` 10; listen with `Event::listen(fn (X $e) => $this->seen[] = $e)` — not `Event::fake()`, so after-commit behaviour is real):
  - `test_a_stock_out_at_the_threshold_is_unusual`: threshold `'900.00'`, 30 bottles = ₱900 → `is_unusual = true`, one `UnusualAdjustmentRecorded`, `stock.adjusted` log `new.unusual = true`, the item page and Adjustments tab show "Unusual".
  - `test_below_the_threshold_is_not_unusual`: 29 bottles → false, no event.
  - `test_a_blank_or_zero_threshold_turns_it_off`: `''` and `'0'` → false for 48 bottles.
  - `test_a_later_threshold_change_leaves_old_flags` (Review Focus 5).
  - `test_a_rolled_back_stock_out_fires_nothing`: `DB::transaction(fn () => [$this->out(...), throw new RuntimeException])` caught → no events, no movement.
  - `test_one_event_per_crossing`: 48 → 10 (stock-out 38) fires `in_stock → low_stock`; 10 → 5 fires nothing; 5 → 0 fires `low_stock → out_of_stock` (Review Focus 4).
  - `test_skipping_levels_fires_one_event_with_both_ends`: 48 → 0 fires one `in_stock → out_of_stock`.
  - `test_recovery_then_drop_fires_again`: low → receive back to in stock → drop again fires a second `in_stock → low_stock`.
  - `test_a_sale_crossing_fires_once_for_two_lines_of_one_item`: product setup as in `SaleDeductionTest`; on hand 12, one order with two lines of 1 each → one `in_stock → low_stock` with the second line's movement.
  - `test_a_sale_below_zero_fires_out_to_negative`: `allow_negative_stock` on, on hand 0 → `out_of_stock → negative_stock`.
  - `test_the_setting_is_validated_and_saved`: `abc`, `-1`, `100000000` refused; `''` saved as off; change logged `settings.updated`.
- [ ] **Step 2: Run** `php artisan test --filter=StockAlertsTest` → FAIL.
- [ ] **Step 3: Implement.** In both writers, take `$from = statusFor(before, level)` and `$to = statusFor(after, level)` per movement, using the running balance already kept on the locked model, and dispatch `StockLevelCrossed` when `STATUS_RANK[$to] > STATUS_RANK[$from]`. In `stockOut`, `is_unusual = threshold > 0 && bccomp(total_cost, threshold, 2) >= 0`; add `'unusual' => $isUnusual` to the `stock.adjusted` new values; dispatch `UnusualAdjustmentRecorded` when true. Leave `stock.went_negative` as it is. Setting: `nullable|regex:/^\d{1,8}(\.\d{1,2})?$/` (covers max 99999999.99) with message "Enter an amount in pesos, up to 2 decimals."; Settings page input "Unusual stock-out amount (₱)" with hint "Stock-outs worth this much or more are flagged. Leave blank to turn off."
- [ ] **Step 4: Run** the filter → PASS; `php artisan test` → all pass (363 before this step).
- [ ] **Step 5: Stage.**

### Task 5: Verify, screens, docs

**Files:**
- Create: `docs/screenshots/step-16/*.png`
- Modify: `docs/PROGRESS.md` (Step 16 entry, Phase 3 "Low-stock monitoring" ticked), `docs/ARCHITECTURE.md` only if it lists settings or movement columns

- [ ] **Step 1:** `php artisan test` → all pass; `npm run build` → no errors; `vendor/bin/pint --test` if the project uses Pint.
- [ ] **Step 2:** Browser check (ask before driving the browser): `/inventory`, `/inventory/movements`, `/inventory/movements?type=adjustment`, Settings — at 768×1024 and 1024×768; every control ≥ 48 px; no horizontal page scroll. Save screenshots to `docs/screenshots/step-16/`.
- [ ] **Step 3:** `graphify update .`
- [ ] **Step 4:** PROGRESS entry in the Step 15 format (spec and plan, owner decisions, data, screens, API, tests count, known gaps).
- [ ] **Step 5:** `superpowers:requesting-code-review`, then `/ponytail-review`; fix findings; report summary, changed files, test results and one Conventional Commit line.
