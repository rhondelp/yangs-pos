# Consolidated Bill, Payments and Receipts – Design

Approved 2026-11-18. The owner chose bill discounts as amount or percent, and daily receipt numbers `RCP-YYYYMMDD-NNNN` (business day).

## Data
- **`payment_methods`:**
  - `code` unique, `name`, `is_cash`, `requires_reference`, `is_active`, `sort_order`.
  - Seeded: Cash (cash), GCash (reference required). The owner adds and edits methods on a Payment methods page (`settings.manage`).
- **`payments`** (append-only, model guard plus DB trigger):
  - session, method, `amount` (applied to the bill, > 0), `tendered` (cash only), `change_given`, `reference_no`, `idempotency_key` (uuid, unique)
  - `received_by`, `paid_at`, `business_date`.
- **`table_sessions` additions:**
  - `bill_discount_type` (amount | percent), `bill_discount_value`, `bill_discount_reason`, `bill_discount_by`
  - `billed_at`
  - `gross_total`, `discount_total`, `net_total` (frozen at close)
  - `receipt_number` (unique).
- **`settings`** (key → jsonb value): `business_name`, `business_address`, `business_tin`, `receipt_footer`, `clean_table_after_payment` (bool, default false). The owner edits them on a Settings page.
- **Counters:** `order_reference_counters` becomes `reference_counters (prefix, business_date)`, so orders (`ORD`) and receipts (`RCP`) share one race-safe generator.

## Rules
- **Bill:**
  - Subtotal = Σ active line totals.
  - Bill discount: amount, clamped to the subtotal; or percent of the subtotal, half-up.
  - Total = subtotal − bill discount. Balance = total − Σ payments (never below 0).
- **Opening the bill page** presents the bill: session `open` → `billing`, table → Awaiting payment. Logged once.
  - Sending a new order to a `billing` tab returns it to `open` and the table to Occupied.
- **Bill discount:** needs `orders.discount` and a reason. Refused if the new total would fall below what is already paid. Logged with old and new values. Removable before payment.
- **Payment** (`PaymentService::record`), in one transaction with the session row locked:
  - **Idempotency:** a repeated `idempotency_key` returns the existing payment, no matter how many times it is sent. Checked before the lock, under the lock, and by the unique index.
  - **Allowed only** on `open` or `billing` tabs with a balance above 0 and an active method.
  - **Amount** defaults to the balance and may not exceed it.
  - **Cash:** `tendered` ≥ amount, change = tendered − amount. Overpayment exists only as cash change.
  - **Non-cash:** reference required when the method says so.
  - **When payments reach the total:**
    - freeze the totals;
    - assign `RCP-…`;
    - close the session (closed_at, closed_by);
    - mark every order's `payment_status` paid;
    - set the table to Cleaning if the setting is on, otherwise Available;
    - log `session.closed`.
  - **Otherwise:** orders become `partial`.
  - Logged: `payment.recorded`.
- **Zero-total bill** (everything discounted): `settleFreeBill` closes it with no payment. Allowed only when the total is 0 and there are active items. Logged.
- **Closed tabs** refuse orders, discounts and payments.
- **Receipt:**
  - Printable stand-alone page with `@page` CSS for 80 mm (`?paper=58` for 58 mm), shown only for closed tabs.
  - Business details and footer come from settings.
  - Each print view is logged: `receipt.printed` the first time, `receipt.reprinted` after.
- **Permissions:**
  - `payments.record`: bill page, payments, settle;
  - `orders.discount`: bill discount;
  - `receipts.view`: receipts;
  - `settings.manage`: settings and payment methods.
- **API:** `GET sessions/{id}/bill`, `POST sessions/{id}/payments`, `POST sessions/{id}/discount`, `POST sessions/{id}/settle`, `GET sessions/{id}/receipt`, `GET payment-methods`.

## Out of scope
Cashier shifts and drawer reconciliation, refunds and payment voids, VAT lines, supervisor PIN approval, and printer integration (browser print only).
