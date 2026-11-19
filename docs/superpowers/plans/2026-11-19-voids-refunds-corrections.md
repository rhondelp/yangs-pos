# Voids, Refunds and Corrections Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Authorised voids (item or order), refunds (per payment, partial or full) and payment corrections as append-only records, with an optional approval PIN, bill-screen and transaction-page UI, and API.

**Architecture:** Three append-only tables (`order_voids`, `refunds`, `payment_corrections`) written by `App\Services\Corrections\{VoidService,RefundService,PaymentCorrectionService}`, each in one transaction with the session row locked. `RunningBill` folds voids, refunds and effective payment methods into the bill. `ApprovalService` checks the optional PIN.

**Tech Stack:** Laravel 13, PostgreSQL, Blade + Alpine, bcmath via `App\Support\Money`.

**Spec:** `docs/superpowers/specs/2026-11-19-voids-refunds-corrections-design.md` (binding), plus `CLAUDE.md`.

## Global Constraints
- Original rows are never edited or deleted. New tables use the `AppendOnly` model trait and the `reject_append_only_change()` trigger.
- Every write is logged via `ActivityLogger::log($action, $subject, $old, $new, $actor, $approver)` inside its transaction. PINs are never logged.
- Money: `decimal(12,2)` strings, `Money` (bcmath, half-up). Session row `lockForUpdate` first, as in `LocksLiveSession`.
- Reasons: required, trimmed, at most 200 characters.
- Touch targets at least 48 px. Buttons that send requests are disabled while sending.

## Review Focus
1. A void that brings the total exactly to what was paid closes the tab (receipt, frozen totals, table freed). Test in Task 2.
2. Two refunds racing on one payment never exceed it. Test the cap counts earlier refunds in Task 3.
3. Voiding a discounted line twice in parts equals voiding it once: the bill recomputes from totals. Test in Task 2.
4. A correction to a method that requires a reference without one is refused, and a cash payment with change cannot be re-methoded. Test in Task 4.
5. Approval PIN: a wrong PIN, an approver without `approvals.grant`, a deactivated approver and the 6th attempt in a minute are refused. Test in Task 1.

---

### Task 1: Permissions, setting, approval PIN

**Files:**
- Modify: `app/Enums/Permission.php`, `app/Services/Settings/SettingService.php` (DEFAULTS), `resources/views/settings/edit.blade.php`, `app/Http/Requests/Settings/UpdateSettingsRequest.php`, `app/Http/Controllers/ProfileController.php`, `resources/views/profile/edit.blade.php`, `routes/web.php`
- Create: migration `add_approval_pin_to_users_table`, `app/Services/Corrections/ApprovalService.php`, `app/Http/Requests/Auth/UpdateApprovalPinRequest.php`, `resources/views/profile/partials/approval-pin-form.blade.php`
- Test: `tests/Feature/Corrections/ApprovalServiceTest.php`

**Interfaces:**
- Produces: `Permission::OrdersVoid = 'orders.void'`, `Permission::CorrectionsCreate = 'corrections.create'`; setting `require_approval_pin` (bool, default false); `ApprovalService::approve(array $input, string $permissionLabel): ?User` reads `approver_username`, `approver_pin` from `$input`. It returns null when the setting is off, returns the approver, or throws `ValidationException` on `approver_pin`. `ApprovalService::setPin(User $user, string $pin): void` logs `user.approval_pin_set` with no values.
- Route: `PUT /profile/approval-pin` (`profile.approval-pin`, `can:approvals.grant`).

- [ ] Tests: setting off → `approve([])` is null; on → missing fields refused; wrong PIN refused and logged `approval.failed`; approver without `approvals.grant` refused; deactivated approver refused; 6th failed attempt in a minute refused with the throttle message even with the right PIN; right PIN returns the approver; `setPin` stores a hash (not the PIN), and the profile route refuses a non-4–6-digit PIN and users without `approvals.grant`.
- [ ] Run → FAIL. Implement. Run → PASS.

### Task 2: Voids

**Files:**
- Create: migration `create_order_voids_table`, `app/Models/OrderVoid.php`, `app/Services/Corrections/VoidService.php`
- Modify: `app/Services/Orders/RunningBill.php`, `app/Services/Orders/StockHook.php`, `app/Services/Billing/PaymentService.php` (make `close()` public as `closeTab(TableSession $locked, User $by, array $bill, string $paid): void`, caller holds the lock), `app/Models/OrderItem.php` (`voids()` hasMany), `app/Models/TableSession.php` (`voids()`)
- Test: `tests/Feature/Corrections/VoidServiceTest.php`

**Interfaces:**
- Produces: `VoidService::voidItem(OrderItem $item, User $by, int $quantity, string $reason, array $approval = []): OrderVoid`; `VoidService::voidOrder(Order $order, User $by, string $reason, array $approval = []): Collection<OrderVoid>`. Log actions `order_item.voided` and `order.voided`. `StockHook::itemVoided(OrderItem $item, int $quantity): void`.
- `RunningBill::calculate()` adds keys `voided` (money string) and `voids` (list of `{id, product_name, quantity, amount, reason}`). `gross` and `discounts` become net of voids: per line, `gross += unit_price × (q − v)`, `discounts += d − prorate(d, v, q)`.

- [ ] Tests: unpaid tab refused ("No payment yet: cancel the item instead."); closed tab refused; reason required; quantity 1..unvoided refused outside; without `orders.void` → `AuthorizationException`; partial void lowers the total by `unit_price × v − prorate`; two partial voids of a discounted line equal one full void; order void voids every unvoided unit; total below paid refused; total equal to paid closes the tab with a receipt number and frees the table; the `order_items` row is unchanged (compare attributes before and after); `order_voids` refuses update (model and DB).
- [ ] Run → FAIL. Implement. Run → PASS, plus `tests/Feature/Billing tests/Feature/Orders`.

### Task 3: Refunds

**Files:**
- Create: migration `create_refunds_table`, `app/Models/Refund.php`, `app/Services/Corrections/RefundService.php`
- Modify: `app/Enums/PaymentStatus.php` (`PartiallyRefunded = 'partially_refunded'`, `Refunded = 'refunded'`), `app/Models/Payment.php` (`refunds()`), `app/Services/Orders/RunningBill.php`
- Test: `tests/Feature/Corrections/RefundServiceTest.php`

**Interfaces:**
- Produces: `RefundService::refund(Payment $payment, User $by, array $input): Refund` where `$input` = `{amount: string, reason: string, idempotency_key: uuid, method_id?: int, reference_no?: ?string, approver_username?, approver_pin?}`. Log action `payment.refunded`.
- `RunningBill::calculate()` adds `refunded` (money string) and each payment entry gets `refunded` and `refunds` (list of `{id, method, amount, reason, refunded_at}`).

- [ ] Tests: live tab refused; amount above payment minus earlier refunds refused ("The most this payment can refund is ₱X."); reason required; reference required for GCash; repeated key returns the first refund; full refund sets orders `refunded`, partial `partially_refunded`; without `payments.refund` → `AuthorizationException`; payment row unchanged; `refunds` append-only (model and DB).
- [ ] Run → FAIL. Implement. Run → PASS.

### Task 4: Payment corrections

**Files:**
- Create: migration `create_payment_corrections_table`, `app/Models/PaymentCorrection.php`, `app/Services/Corrections/PaymentCorrectionService.php`
- Modify: `app/Models/Payment.php` (`corrections()`, `effectiveMethod()`, `effectiveReference()`), `app/Services/Orders/RunningBill.php` (payments use effective method and reference, add `corrected: bool`), `app/Services/Billing/ReceiptService.php` (unchanged if it reads `RunningBill` payments)
- Test: `tests/Feature/Corrections/PaymentCorrectionServiceTest.php`

**Interfaces:**
- Produces: `PaymentCorrectionService::correct(Payment $payment, User $by, array $input): PaymentCorrection` where `$input` = `{method_id: int, reference_no?: ?string, reason: string, approver_username?, approver_pin?}`. Log action `payment.corrected` with old and new method names and references.

- [ ] Tests: Cash → GCash with a reference works, and the bill and receipt show GCash; GCash without a reference refused; cash payment with change cannot move; no change at all refused ("Nothing to correct."); the latest of two corrections wins; payment row unchanged; without `corrections.create` → `AuthorizationException`.
- [ ] Run → FAIL. Implement. Run → PASS.

### Task 5: API

**Files:**
- Create: `app/Http/Requests/Corrections/{VoidItemRequest,VoidOrderRequest,RefundRequest,CorrectPaymentRequest}.php`, `app/Http/Controllers/Api/V1/CorrectionController.php`, `app/Http/Resources/V1/{OrderVoidResource,RefundResource,PaymentCorrectionResource}.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Corrections/CorrectionApiTest.php`

**Interfaces:**
- Routes: `POST order-items/{item}/void` and `POST orders/{order}/void` (`can:orders.void`), `POST payments/{payment}/refunds` (`can:payments.refund`), `POST payments/{payment}/corrections` (`can:corrections.create`). Each replies 201 with `{data: <record>, bill: <BillResource>}`; a replayed refund replies 200.

- [ ] Tests: each route 403 without its permission; 422 without a reason; happy path 201 with the bill; the refund replay is 200.
- [ ] Run → FAIL. Implement. Run → PASS.

### Task 6: Screens

**Files:**
- Modify: `resources/views/pos/bill.blade.php`, `resources/js/pos.js` (`billScreen`: `voidItem`, `voidOrder`), `app/Http/Controllers/Web/BillController.php` (urls, `canVoid`, `approval` flag), `app/Http/Resources/V1/BillResource.php` (items carry `order_id`, `voidable`)
- Create: `app/Http/Controllers/Web/TransactionController.php` (`index`, `show`, `refund`, `correct`), `resources/views/transactions/{index,show}.blade.php`, `app/Http/Requests/Corrections/TransactionFilterRequest.php`
- Modify: `routes/web.php`, `app/Support/Navigation.php` (Transactions, `orders.view-own`), `tests/Feature/Ui/NavigationTest.php`, `tests/Feature/Rbac/RouteGuardTest.php` if it lists routes
- Test: `tests/Feature/Corrections/TransactionWebTest.php`

**Interfaces:**
- Web routes: `GET /transactions`, `GET /transactions/{session}` (`can:orders.view-own`; `view-all` sees all, otherwise only tabs the user opened or closed, else 404), `POST /transactions/payments/{payment}/refunds` (`can:payments.refund`), `POST /transactions/payments/{payment}/corrections` (`can:corrections.create`). The two POSTs redirect back with a status message; errors go back to the dialog.

- [ ] Tests: list shows today's closed tabs, the date filter works, view-own hides others' tabs (404 on show); detail shows voids, refunds, the corrected method and net; refund and correct forms post and redirect with status; buttons hidden without permission; approver fields render when the setting is on.
- [ ] Run → FAIL. Implement. Run → PASS. `npm run build`.

### Task 7: Verify
- [ ] Playwright at 768×1024 and 1024×768: partial payment, void an item on the bill screen, final payment, refund part of it on the transaction page, correct a method. Screenshots go to `docs/screenshots/step-12/`, with an audit for targets under 48 px and horizontal scroll.
- [ ] Full suite, build, `docs/PROGRESS.md`, `graphify update .`.
