# Billing, Payments and Receipts Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Deliver the consolidated bill, a bill discount, payments (cash with change, GCash and others with a reference, split, partial, idempotent, race-safe), closing the tab, and printable receipts, with screens and API.

**Spec:** `docs/superpowers/specs/2026-11-18-billing-payments-design.md` (binding). Also `CLAUDE.md`.

## Global Constraints
- **Paid:** mark Paid only when recorded payments ≥ total, inside one transaction with `lockForUpdate` on the session.
- **Money:** `decimal(12,2)` strings, `App\Support\Money` (bcmath, half-up). No floats.
- **History:** payments are append-only. Nothing is deleted. Every write is logged inside its transaction.
- **Settings:** production seeds only roles, permissions, payment methods, categories and settings.
- **Touch:** targets at least 48 px, plus a keypad. Buttons that send requests are disabled while sending.

## Tasks
1. **Shared counter.**
   - Migration that renames `order_reference_counters` to `reference_counters` with a composite key `(prefix, business_date)`.
   - `App\Services\Support\ReferenceGenerator::next(string $prefix, string $businessDate): string`, which replaces `OrderReferenceGenerator`.
   - Update callers and tests.
2. **Settings.**
   - `settings` table and `App\Services\Settings\SettingService` (`get`, `all`, `set` with log).
   - `SettingsSeeder` with defaults; Settings page at `/settings`.
   - Tests.
3. **Payment methods.**
   - Table and model, `PaymentMethodSeeder` (Cash, GCash).
   - `PaymentMethodService` (create, update with logs); page at `/payment-methods`.
   - API `GET payment-methods`.
   - Tests.
4. **Billing schema and services.**
   - `payments` table (append-only) and the session billing columns.
   - `RunningBill` gains subtotal, bill_discount, total, paid, balance and payments.
   - `BillService` (`present`, `applyDiscount`, `removeDiscount`).
   - `PaymentService` (`record`, `settleFreeBill`).
   - `OrderService::addOrder` reopens a `billing` tab.
   - Tests:
     - cannot mark Paid with insufficient payments;
     - split payment totals;
     - change calculation;
     - a double submit is ignored;
     - a stale or second payment cannot overpay;
     - a closed session refuses orders, payments and discounts;
     - the discount rules;
     - the table goes to Cleaning or Available;
     - receipt number format;
     - the zero bill.
5. **Receipt.**
   - `ReceiptService` (`data`, `recordPrint`).
   - `/receipts/{session}`, a printable view for 58 and 80 mm.
   - API receipt endpoint.
   - Tests: business details from settings; print vs reprint logs; open tabs refused.
6. **Screens and API.**
   - `/pos/sessions/{session}/bill`: Alpine bill, payment keypad and discount dialog.
   - API endpoints and tests.
   - Floor and POS "Bill" links.
7. **Verify.**
   - Playwright run at 768×1024 and 1024×768: split cash and GCash, change, double tap, receipt print view.
   - Screenshots, the full suite, the build and the docs.
