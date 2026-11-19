# Voids, Refunds and Corrections – Design

2026-11-19. The owner gave the brief and said "dive in"; the calls marked *(call)* below are the builder's, made from `docs/ARCHITECTURE.md` §3.3, §3.6, §3.7 and §3.10.

## Intent
Let authorised staff undo mistakes after money is involved, without ever editing or deleting history. Every undo is a new record with a reason, logged with old and new values, and reports can compute net sales from these records.

## Rules

### Void (`orders.void`)
- **When:** on a live tab (`open` or `billing`) that already has at least one payment. Before any payment, the existing item cancel (`orders.cancel-item`) is the way to remove items; a void on an unpaid tab is refused with "No payment yet: cancel the item instead." *(call: one clear line between cancel and void)*
- **After close:** no voids. Money goes back through a refund (ARCHITECTURE §3.3).
- **What:** one item (a quantity from 1 to the line's still-unvoided quantity), or a whole order (every unvoided unit of every active item in it).
- **Record:** append-only `order_voids` row per item: session, order, item, quantity, amount, reason, voided_by, approved_by, business_date. `order_items` rows are never changed by a void.
- **Amount:** for a line with quantity *q*, line discount *d* and *v* units voided in total, the voided value is `unit_price × v − prorate(d, v, q)`. The bill always recomputes from totals (not by summing stored amounts), so rounding never drifts. The stored `amount` is that row's share, for reports.
- **Limit:** the new total may not fall below what is already paid. If it lands exactly on it, the tab closes in the same transaction (receipt number, frozen totals, table freed), the same way a final payment closes it.
- **Stock:** `StockHook::itemVoided(OrderItem $item, int $quantity)` is called in the transaction. Phase 3 fills it in.

### Refund (`payments.refund`)
- **When:** only on `closed` tabs.
- **Linked to one payment.** Amount > 0 and ≤ that payment's amount minus its earlier refunds, so Σ refunds ≤ Σ payments.
- **Method:** any active method (defaults to the payment's). A reference number is required when the method says so. A reason is required.
- **Idempotent:** an `idempotency_key` (uuid, unique) like payments.
- **Record:** append-only `refunds` row: session, payment, method, amount, reference_no, reason, idempotency_key, refunded_by, approved_by, business_date.
- **Status:** every order on the tab gets `payment_status` `refunded` when Σ refunds = net total, otherwise `partially_refunded`.
- **No stock movement:** drinks served stay in COGS (ARCHITECTURE §3.6).

### Correction (`corrections.create`)
- **What it fixes:** a payment recorded with the wrong method or reference number (e.g. Cash pressed for a GCash transfer). *(call: the owner gave no examples; this is the correction the current data can need)*
- **Record:** append-only `payment_corrections` row: payment, old and new method, old and new reference, reason, corrected_by, approved_by. The payment row is never changed.
- **Effective values:** the latest correction wins. The bill, receipt and transaction pages show the effective method and reference.
- **Limits:** a cash payment that gave change cannot move to another method (change proves it was cash). The new method must be active; a reference is required when the new method requires one. Works on live and closed tabs.

### Approval PIN (optional)
- **Setting** `require_approval_pin` (bool, default false), on the Settings page.
- **When on:** a void, refund or correction also needs `approver_username` and `approver_pin` of an active user holding `approvals.grant`. The actor still needs the action's own permission *(owner: "a cashier needs these explicitly")*. An approver may approve their own action.
- **PIN:** 4–6 digits, hashed in `users.approval_pin`, set on the Profile page by users with `approvals.grant`.
- **Rate limit:** 5 failed attempts per minute per approver username. Each failure is logged as `approval.failed` (no PIN in the log).
- **Recorded:** `approved_by` on the record and on the activity log entry.

### Permissions
New: `orders.void`, `corrections.create`. Existing: `payments.refund`, `approvals.grant`. None are Cashier defaults.

### Screens
- **Bill screen:** on a tab with payments, each item has "Void" and each order has "Void order"; a dialog asks for quantity (item), reason, and approver fields when the setting is on.
- **Transactions:** `/transactions` lists closed tabs by business date (default today), newest first; `/transactions/{session}` shows items, voids, payments with their refunds and corrections, totals, refunded amount and net, with "Refund" and "Correct" dialogs per payment and the receipt link.
  - `orders.view-all` sees every tab; `orders.view-own` sees tabs the user opened or closed.

### API (`/api/v1`)
- `POST order-items/{item}/void`, `POST orders/{order}/void` (`orders.void`)
- `POST payments/{payment}/refunds` (`payments.refund`)
- `POST payments/{payment}/corrections` (`corrections.create`)
- `GET sessions/{id}/bill` and the receipt now include voids, refunds and effective payment methods.

## Out of scope
Cash drawer effects of refunds (no cashier shifts yet), item-level refunds, refunds on live tabs, voiding a void, receipt reprint marks for refunds, net-sales reports (Phase 4 reads these records).
