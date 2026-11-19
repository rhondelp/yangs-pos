# Transaction History and POS End-to-End Tests Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Filterable transaction history, an end-to-end API test of spec §4B steps 1–12, a tablet browser test, and real concurrency tests for double payment and double tab open.

**Architecture:** Filters live in `TransactionFilterRequest` + `TransactionController::index` on top of `TableSession::visibleTo`. The E2E test drives only `/api/v1`. Concurrency tests start several real PHP processes (`tests/Concurrency/worker.php`) against the test database, released together at one timestamp, so the PostgreSQL locks and unique indexes are what is tested. The browser test is Python Playwright (webapp-testing skill) against a running server.

**Spec:** `docs/YANGS_SPEC.md` §4 (binding), owner brief for Step 13, `CLAUDE.md`.

## Global Constraints
- View-own users see only tabs they opened or closed; `orders.view-all` sees all (`TableSession::visibleTo`).
- History = closed (paid) tabs. Payment status filter: `paid`, `partially_refunded`, `refunded`. Method filter matches the payment's corrected method.
- No new project packages. Python Playwright is a test tool installed at user level; the browser test documents it.
- Tests never touch the dev database (`yangs_test` only); concurrency workers inherit the test env.

## Review Focus
1. A cashier without `orders.view-all` filtering by another cashier sees nothing of theirs (filter never widens visibility). Test in Task 1.
2. Method filter after a correction uses the corrected method, not the original. Test in Task 1.
3. Five simultaneous payments of the full balance with different keys record exactly one payment. Test in Task 3.
4. Five simultaneous opens of one table create exactly one tab and leave the table Occupied. Test in Task 3.
5. Spec §4B step 11: paid only after payments cover the total; a partial payment leaves the tab open. Test in Task 2.

---

### Task 1: History filters
**Files:** Modify `app/Http/Requests/Corrections/TransactionFilterRequest.php`, `app/Http/Controllers/Web/TransactionController.php`, `resources/views/transactions/index.blade.php`. Test `tests/Feature/Corrections/TransactionFilterTest.php`.
- Request rules: `date` Y-m-d, `table_id` integer, `cashier_id` integer, `payment_status` in paid/partially_refunded/refunded, `method_id` integer. `filters(): array`.
- Cashier filter = `closed_by`; shown only with `orders.view-all`.
- [ ] Tests: each filter narrows; view-own + cashier filter of someone else → empty; method filter follows a correction; invalid values → errors. RED → implement → GREEN.

### Task 2: End-to-end API workflow
**Files:** Test `tests/Feature/EndToEnd/OpenTabWorkflowTest.php`.
- [ ] One test walking §4B 1–12 as a cashier over `/api/v1`, asserting §4C order fields (reference, table, cashier, time, products, quantities, unit price snapshot after a price change, subtotals, discount, total, order status, payment status), partial payment keeps the tab open, final payment closes it, the table is Available, the receipt is ready. Fix any bug it finds (test first).

### Task 3: Concurrency
**Files:** Create `tests/Concurrency/worker.php`, `tests/Feature/Concurrency/ConcurrencyTest.php` (uses `DatabaseTruncation`, not `RefreshDatabase`, so workers see committed rows).
- Worker: `php tests/Concurrency/worker.php <scenario> <startAtMicrotime> <args…>` boots the app with the parent's env, waits until the start time, runs one service call, prints `ok:<id>` or `refused:<message>`.
- [ ] Tests: 5 × `PaymentService::record` (different keys, full balance) → 1 payment, 4 refused, tab closed once, one receipt number; 5 × same key → 1 payment, all return its id; 5 × `TableSessionService::open` on one table → 1 tab, 4 refused.

### Task 4: Tablet browser test
**Files:** Create `tests/Browser/open_tab_flow.py`, `tests/Browser/README.md`.
- [ ] Python Playwright, 768×1024 and 1024×768: floor → open tab → add items → send → bill → split GCash + cash → change → receipt → transactions list shows it. Reads base URL, username, password from env. Run with the webapp-testing `with_server.py` helper or against a running server.

### Task 5: Verify and report
- [ ] Full suite, build, `docs/PROGRESS.md`, list of spec §4 items still unimplemented, ponytail review.
