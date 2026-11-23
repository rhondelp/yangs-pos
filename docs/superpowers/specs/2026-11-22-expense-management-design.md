# Step 17 – Expense management (design)

Owner's step (2026-11-22), Phase 4. Covers YANGS_SPEC §5B "Expense Management" and the Phase 4 item "Expense tracking".
Approved in conversation on 2026-11-22.

## Intent

- The owner records what the bar spends to run: electricity, water, rent, staff-related costs, repairs, transport and the like, with an optional photo or PDF of the receipt.
- Stock purchases are **not** expenses. They live in stock-in (Step 14). The expense screen shows a separate, clearly labelled "Inventory purchases" total next to the expense total, so the owner sees both without the Phase 4 P&L ever counting a purchase twice (ARCHITECTURE §3.6).
- Expenses are financial history: never edited, never deleted. A mistake is voided with a reason and recorded again.
- A future mobile app reads and records expenses through the same API.

## Goals

1. **Categories** (`expense_categories`): seeded with Electricity, Water, Rent, Employee-related, Maintenance, Transportation, Other. Fixed list: no screen to add or retire categories in this step.
2. **Expenses** (`expenses`): category, description, amount, expense date, recorded by, recorded at, optional receipt attachment on the private disk.
3. **Void only:** an expense can be voided once, with a reason (`expenses.manage`). Nothing else about an expense can change. Voided expenses stay visible, marked voided, and are excluded from every total. "Record again" on a voided expense opens a new expense form prefilled with its category, description, amount and date (not the attachment).
4. **Totals:** for the filtered date range, the expense total (non-voided, filtered category) and a separate "Inventory purchases" total (sum of stock-in totals by business date, same date range; the category filter does not apply to it).
5. **Screens and API** with date-range and category filters.
6. **Logging:** `expense.recorded` and `expense.voided`, in the same transaction as the change.

## Non-goals

- Editing an expense, managing categories, a "Supplies (non-stock)" category (owner: non-sold supplies go under Other).
- Paying an expense from the cash drawer (`cash_movements`, not built yet).
- Supervisor PIN for voids.
- Expense reports, charts, PDF or Excel export (Phase 6), and the P&L (later Phase 4 step).

## Anti-goals

- A stock purchase recorded as an expense, or an "Inventory purchases" figure that is added into the expense total.
- A receipt file reachable by URL without `expenses.view`.
- A voided expense counted in any total.
- An expense row changed after it was written, other than its one void.

## Approaches

- **Void storage (picked A):**
  - A: void fields on the expense row (`voided_at`, `voided_by`, `void_reason`), as in the ARCHITECTURE ERD; the row may be updated exactly once, only to void it.
  - B: a separate append-only `expense_voids` table like `order_voids`. Rejected: an expense is only ever voided whole, so the extra table and join buy nothing.
- **Corrections (owner picked void only):** rejected editable expenses with logged changes, because past totals could change silently.
- **Attachment delivery (builder call, one sensible way):** files on the existing `local` disk (`storage/app/private`), streamed by a controller action behind `expenses.view`. Rejected: the public disk (anyone with the URL) and storing files in the database.

## Design

- **Migration:**
  - `expense_categories`: `id`, `name` unique, `is_active` boolean default true, timestamps.
  - `expenses`: `id`, `expense_category_id` FK (indexed), `description` string(200), `amount` decimal(12,2) with a check `amount > 0`, `expense_date` date (indexed), `attachment_path` nullable string, `attachment_name` nullable string(255) (the uploaded file's name, for download), `recorded_by` FK users, timestamps, `voided_at` timestamptz nullable, `voided_by` FK users nullable, `void_reason` string(300) nullable. Check: the three void columns are all null or all set.
  - A trigger `expenses_void_once()` rejects `DELETE`, and rejects `UPDATE` unless the old row is not voided and only `voided_at`, `voided_by`, `void_reason` and `updated_at` change.
- **Seeding:** `ExpenseCategorySeeder` runs in production seeding (CLAUDE.md allows categories), idempotent by name.
- **Model:** `Expense` refuses `delete()`, `forceDelete()` and any update except the void, mirroring the trigger. Not soft-deleted (void is its own state).
- **Service:** `App\Services\Finance\ExpenseService`:
  - `record(User $by, array $input, ?UploadedFile $attachment): Expense`: validates (category exists and is active, description 1–200 chars, amount money format > 0 and ≤ 9,999,999,999.99, date `Y-m-d` not after today's business date), stores the file under `expenses/YYYY/MM/` with a random name, logs `expense.recorded` (category, description, amount, date, has attachment). If the transaction fails, the stored file is removed.
  - `void(Expense $expense, User $by, string $reason): Expense`: locks the row, refuses an already voided expense ("This expense is already voided."), reason 1–300 chars, logs `expense.voided` with the reason.
  - `totals(array $filters): array{expenses: string, inventory_purchases: string}` with bcmath; `list(array $filters)` paginated 30, newest expense date first.
- **Attachment rules:** `jpg`, `jpeg`, `png`, `webp`, `pdf` by MIME and extension, max 5 MB. The form input accepts `image/*,application/pdf` so the tablet camera works. Served inline for images and PDF with `Content-Disposition` using `attachment_name`; `X-Content-Type-Options: nosniff`.
- **Filters** (`ExpenseFilterRequest`): `from`, `to` (`Y-m-d`, to ≥ from; default: the current month by business date), `category_id` (exists), `voided` (boolean, default hidden).
- **Authorization:** `expenses.view` for the list, the expense page, the totals and the receipt file; `expenses.manage` to record and void. Cashiers get neither by default. A Policy or route `can:` middleware, as the rest of the app does.
- **Screens** (Blade, `x-ui.*`, design system, UI UX Pro Max for the tiles and filter bar):
  - Expenses list: GET filter bar (from, to, category, show voided), two tiles side by side: "Expenses" and "Inventory purchases" with the hint "Stock-in, not an expense", then the table (date, category, description, amount, attachment icon, recorded by; voided rows struck through with a "Voided" badge). "Record expense" button for `expenses.manage`.
  - Record expense form (on its own page): category select, description, amount, date (default today's business date), receipt file. The submit button disables while sending.
  - Expense page: details, the receipt (image preview or PDF link), void dialog (reason) for `expenses.manage`, and on a voided expense its void details and "Record again".
  - Navigation: "Expenses" for `expenses.view`.
  - Touch targets ≥ 48 px; checked at 768×1024 and 1024×768.
- **API** (`/api/v1`, Resources): `GET expenses` (filters; `meta.totals` with `expenses` and `inventory_purchases`), `GET expenses/{id}`, `POST expenses` (multipart), `POST expenses/{id}/void`, `GET expenses/{id}/attachment`, `GET expense-categories`.

## Tests (feature)

- Voided expenses are excluded from the expense total; the total equals the sum of non-voided amounts in the range and category.
- Inventory purchases = sum of stock-in totals by business date in the range; not affected by the category filter; never added to the expense total.
- The receipt file: `expenses.view` gets it; a user without it gets 403 on web and API; the file is not under `public/`.
- Validation: bad amount, zero, future date, inactive or unknown category, wrong file type, file over 5 MB.
- Void: once only, reason required, logged; a second void is refused; the database trigger refuses a direct update and a delete.
- Permissions: `expenses.manage` needed to record and void.
- Filters: date range by expense date, category, voided shown on request.

## Left to the builder

Copy wording, icons, tile layout, the receipt preview size, file naming inside `expenses/YYYY/MM/`.
