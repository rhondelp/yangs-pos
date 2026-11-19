# Stock-out and Automatic Sale Deduction Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Sales deduct stock through recipes with a cost snapshot, cancels/voids optionally return it, negative stock is blocked unless a setting allows it, and managers record stock-outs.

**Architecture:** `InventoryService` gains `deductForLine`, `returnForLine`, `stockOut`. `StockHook` becomes a thin adapter that `OrderService` / `VoidService` already call, delegating to `InventoryService` inside the caller's transaction (session lock held, items locked by ascending id).

**Spec:** `docs/superpowers/specs/2026-11-19-stock-out-and-sale-deduction-design.md` (binding), `CLAUDE.md`.

## Global Constraints
- Lock order: session, then inventory items ascending id. One writer: `InventoryService`. bcmath only; quantities 14,3, unit costs 14,6, money 12,2.
- Movements are append-only; every write is logged in its transaction.

## Review Focus
1. Two sessions ordering the last bottle at once: one succeeds, one is refused (real processes). Test in Task 2.
2. A failure after deduction (e.g. second line refused) rolls back the order and all movements. Test in Task 2.
3. Partial cancel / partial void returns only the cancelled units. Test in Task 3.
4. A product with tracking on but no recipe for the sold option deducts nothing and keeps its manual cost. Test in Task 2.
5. Return cost re-averages correctly when stock is zero or negative. Test in Task 3.

---

### Task 1: Schema and settings
Migration: `order_items.returned_to_stock` (bool, nullable), `order_voids.returned_to_stock` (bool, default false). Setting `allow_negative_stock` (false) on the Settings page.

### Task 2: Deduction on sale
`InventoryService::deductForOrder(Order, User)` / `deductForQuantity(OrderItem, int, User)`; `StockHook` delegates; `OrderService` passes the actor; cost snapshot written to the order item. Tests `tests/Feature/Inventory/SaleDeductionTest.php` + concurrency scenario `sell` in `tests/Concurrency/worker.php`.
- [ ] Tests: snapshot cost = Σ qty × avg; one sale movement per component; bucket of 6 deducts 6; cocktail deducts two items; untracked/no-recipe → no movement, manual cost; negative refused with message when off; allowed + `stock.went_negative` when on; quantity raise deducts and re-checks; second line refused rolls back first line's movement and the order; ledger sum = on hand; five processes selling the last bottle → one sale.

### Task 3: Returns on cancel and void
`InventoryService::returnForLine(OrderItem, int $quantity, User)`; `OrderService::cancelItem(..., bool $returnToStock = true)`, `VoidService::voidItem/voidOrder(..., bool $returnToStock = true)`; requests accept `return_to_stock`. Tests `tests/Feature/Inventory/StockReturnTest.php`.
- [ ] Tests: full and partial cancel return the units at the sale cost; `false` → no movement and `returned_to_stock = false`; void returns; refund leaves stock; return re-averages; untracked lines ignore the flag.

### Task 4: Manual stock-out
`InventoryService::stockOut`; web form on item page; API `POST inventory-items/{item}/stock-outs`. Tests `tests/Feature/Inventory/StockOutTest.php`.
- [ ] Tests: reason required (note required for other); unit conversion; never below zero; `inventory.adjust` enforced; logged; movement reason shown.

### Task 5: Screens
POS cancel dialog and bill void dialog "Put back in stock" checkbox; OrderItemResource / bill items expose `tracks_stock`; order reply `warnings` toast. Settings checkbox. Tests in the feature files above plus web assertions.

### Task 6: Verify
Full suite, build, browser check (with permission), docs, review.
