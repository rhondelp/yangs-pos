# YANGS Design System – Master

> When building a page, first check `design-system/YANGS/pages/<page>.md`. If it exists, its rules override this file. Otherwise follow this file.

- **Product:** POS and management system for one small bar, used on tablets under dim, warm lighting, often one-handed and in a hurry.
- **Generated:** 2026-11-18 with UI UX Pro Max (`"nightlife bar pos dark dashboard" --design-system --density 8`), then revised by hand (see "Changes from the generator draft").
- **Implemented in:** `resources/css/app.css` (`@theme` tokens) and `resources/views/components/ui/*`.

## Direction

The bar top at night. Surfaces are smoked oak, text is the off-white of beer foam, and the single accent is lager amber. Everything is quiet except the numbers: totals, prices and table numbers are set large in a condensed signage face, the way a bar's price board is. Dark only; no light mode in v1.

## Color

| Token (`--color-*`) | Hex | Use |
|---|---|---|
| `bg` | `#1A1512` | Page background ("smoke") |
| `surface` | `#241D18` | Cards, sidebar, tables ("oak") |
| `raised` | `#30271F` | Hover rows, inputs, nested panels ("walnut") |
| `line` | `#3A3029` | Dividers and card borders (decorative only) |
| `edge` | `#7A6756` | Input and control borders; ≥ 3:1 on `bg` and `surface` |
| `fg` | `#F5EDE3` | Primary text ("foam"); 15.5:1 on `bg` |
| `muted` | `#BFAE9C` | Secondary text ("malt"); 7.7:1 on `surface` |
| `accent` | `#F2A93B` | Primary actions, focus ring, key numbers ("lager"); 9.1:1 on `bg` |
| `on-accent` | `#1A1512` | Text on `accent` |
| `success` | `#4ADE80` | Available, paid, in stock |
| `info` | `#60A5FA` | Occupied, open |
| `warning` | `#FACC15` | Awaiting payment, partial, low stock |
| `danger` | `#F87171` | Unpaid, out of stock, negative stock, destructive actions |
| `reserved` | `#C4B5FD` | Reserved |

Rules:
- Status is never shown by color alone: badges always carry a text label.
- Status colors are used as text on a 15% tint of the same color, or as a 4px left rule; never as large fills.
- Only `accent` fills buttons. Destructive buttons use `danger` fill with `on-accent` text (6.5:1).

## Typography

| Role | Family | Notes |
|---|---|---|
| Body, UI | Barlow 400/500/600 | Grotesque with signage roots; legible at arm's length |
| Headings, numbers | Barlow Semi Condensed 600/700 | `font-display`; money and counts use `tabular-nums` |

Scale (rem): 0.875 small, 1 body, 1.125 lead, 1.5 page title, 2 stat value, 2.5 POS total. Base size 16px; never below 14px. Sentence case everywhere; no all-caps labels (the YANGS wordmark is the only exception). Loaded from fonts.bunny.net with system fallbacks, so the app still works offline.

## Space, size, radius

- Spacing follows Tailwind's 4px scale; dense dashboard rhythm (8–24px gaps).
- `--spacing-touch: 3rem`: every interactive target is at least 48×48px (`min-h-touch`, `size-touch`). POS primary actions use 56–64px.
- Radius by hierarchy: controls 0.5rem, cards 0.75rem, POS tiles 1rem, badges full.
- No drop shadows on dark surfaces; depth comes from `surface` → `raised` steps and `line` borders. Dialogs get a dark scrim (rgba 0,0,0,0.7), no blur.

## Motion

- Color and background transitions 150ms ease-out on interactive elements only.
- Dialog open: 150ms fade. No entrance animations on page load, no hover lifts or scale.
- `prefers-reduced-motion: reduce` turns all transitions off.

## Components (`resources/views/components/ui`)

button, input, select, textarea, card, modal (native `<dialog>`), table, pagination, status-badge, stat-card, alert, flash, empty-state, confirm-dialog, icon. Use these; do not restyle raw elements per page.

## Layouts

- **Management** (`<x-layouts.app>`): left sidebar (expanded 16rem with labels, collapsed 5rem icons only, remembered per device); off-canvas drawer below 1024px; top bar with page title and the user menu. Nav items appear only when the user has the permission and the page exists.
- **POS** (`<x-layouts.pos>`): full screen, one 64px top strip (wordmark, cashier, back to management if allowed, sign out), content fills the rest. Built for 768×1024 portrait and 1024×768 landscape.
- **Guest** (`<x-layouts.guest>`): centered sign-in card.

## Accessibility checklist

- Visible focus: 2px `accent` ring with 2px offset on `bg`.
- Labels on every input (no placeholder-only labels); errors under the field.
- Icon-only buttons have an accessible name.
- No horizontal scroll at 768px; tables scroll inside their own container.

## Changes from the generator draft

| Draft | Final | Why |
|---|---|---|
| Slate (#0F172A) with green #22C55E accent | Warm oak/smoke with lager amber | Slate-and-green is the generic dark-dashboard default; warm tones suit a bar and keep green free for "available/paid" status |
| Glassmorphism, backdrop blur | Flat tonal layers, no blur | Blur costs frame rate on tablets and lowers text contrast in dim light |
| Fira Code headings, Fira Sans body | Barlow Semi Condensed headings and numbers, Barlow body | Monospace headings read as a developer tool; condensed signage type fits price boards and big totals |
| Hover lift (`translateY`), card shadows | No lifts, no shadows | Touch screens have no hover; shadows vanish on dark backgrounds |
| White modal, light input borders | Dark dialog, `edge` borders at ≥ 3:1 | Draft mixed light-mode specs into a dark theme |
| "Real-Time / Operations Landing" page pattern | Not used | YANGS is an app, not a marketing page |
