# Design System and Base Layout Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A dark, high-contrast design system for a dim bar, mapped to Tailwind 4 tokens; a management layout (collapsible sidebar + top bar) and a full-screen POS layout; a reusable Blade component kit; permission-aware navigation; empty-state dashboards for owner and cashier; all existing pages moved onto the kit.

**Architecture:** Tokens live in `resources/css/app.css` `@theme` (Tailwind 4 has no JS config). Components are anonymous Blade components under `resources/views/components/ui/` (`<x-ui.button>`), layouts under `components/layouts/` (`<x-layouts.app>`, `<x-layouts.pos>`, `<x-layouts.guest>`). Navigation is one PHP list (`App\Support\Navigation`) filtered by permission and by `Route::has`, so modules appear when their routes exist. Modals use native `<dialog>` (focus trap, Esc, inert background without an Alpine plugin). Breeze's light components and class-based layouts are removed.

**Tech Stack:** Laravel 13 Blade, Tailwind 4 (`@theme`), Alpine.js 3, Barlow / Barlow Semi Condensed via fonts.bunny.net.

**Spec:** `docs/YANGS_SPEC.md` §10, §12; `design-system/YANGS/MASTER.md`; owner's Step 6 brief; `CLAUDE.md` UI rules (48px targets, portrait and landscape tablet).

## Global Constraints

- Every interactive target ≥ 48×48 px (`--spacing-touch: 3rem`).
- Text contrast ≥ 4.5:1; control borders and focus ring ≥ 3:1 against their background.
- No fake numbers: stat cards without data show an empty state, never 0 or sample values.
- Navigation shows an item only if the user has its permission and its route exists.
- No new npm/composer packages. Icons are inline SVG (Heroicons paths, MIT).
- Viewports to verify: 768×1024, 1024×768, 1366×768.

## Review Focus

- Sidebar collapsed state hides labels but keeps accessible names (`aria-label` / sr-only).
- Portrait tablet (768 wide): sidebar becomes an off-canvas drawer; no horizontal scroll.
- Users with no dashboard permission (empty roles) get a clear empty state, not a 403 loop.
- Confirm dialog submits with CSRF and the right HTTP method.
- Status badge never relies on color alone (always a text label); unknown status renders a neutral badge.

---

### Task 1: Tokens and MASTER.md

- Rewrite `design-system/YANGS/MASTER.md` with the final palette, type, spacing, radius, motion and component rules; record what changed from the generator draft and why.
- `resources/css/app.css`: `@theme` colors (`bg`, `surface`, `raised`, `line`, `edge`, `fg`, `muted`, `accent`, `on-accent`, `success`, `info`, `warning`, `danger`, `reserved`), fonts (`sans`, `display`), radius, `--spacing-touch`; base styles (body colors, focus-visible ring, reduced motion, tabular numerals).

### Task 2: Component kit

**Files:** `resources/views/components/ui/{button,input,select,textarea,card,modal,table,status-badge,stat-card,alert,flash,empty-state,confirm-dialog,icon}.blade.php`, `resources/views/vendor/pagination/yangs.blade.php` (set as default in `AppServiceProvider`).

Tests (`tests/Feature/Ui/ComponentTest.php`): `test_status_badge_maps_known_statuses_and_falls_back`, `test_stat_card_without_a_value_shows_an_empty_state`, `test_button_renders_link_or_button_with_touch_size`, `test_input_shows_label_error_and_old_value`, `test_confirm_dialog_posts_with_csrf_and_method`.

### Task 3: Layouts and navigation

**Files:** `app/Support/Navigation.php` (`items(User $user): array`), `resources/views/components/layouts/{app,pos,guest}.blade.php`; delete Breeze `layouts/*`, `app/View/Components/*`, old components.

Tests (`tests/Feature/Ui/NavigationTest.php`): owner sees Users, Roles, Activity log; cashier sees none of them; items whose route does not exist are hidden.

### Task 4: Dashboards

**Files:** `app/Http/Controllers/Web/DashboardController.php` (owner → `dashboard.owner`, cashier → `dashboard.cashier` in POS layout, otherwise a no-dashboard empty state), `resources/views/dashboard/{owner,cashier,none}.blade.php`, `routes/web.php`.

Tests (`tests/Feature/Ui/DashboardTest.php`): owner sees the owner dashboard with empty states and no digits in stat values; cashier sees the POS layout dashboard; a Bartender (empty role) sees the "no dashboard yet" state; guests redirect to login.

### Task 5: Move existing pages onto the kit

Login, profile/password, users (index/create/edit), roles matrix, activity logs (index/show). Existing tests must stay green.

### Task 6: Verify on tablets

Serve against a disposable database, sign in as owner and cashier, screenshot each key page at 768×1024, 1024×768, 1366×768; check 48px targets programmatically; save screenshots to `docs/screenshots/step-6/`.
