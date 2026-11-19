# Browser tests

`open_tab_flow.py` walks YANGS_SPEC §4B on the tablet screens in portrait (768×1024) and landscape (1024×768): floor → open a free table → tap products → send two rounds → bill → GCash + cash → change → receipt → transaction history. It is not part of `php artisan test`; run it before a release or after POS screen changes.

## Needs

- Python 3.11+ and Playwright for Python: `python -m pip install --user playwright`. It is a test tool, not a project dependency.
- A Chromium build. The script reuses one already in `~/AppData/Local/ms-playwright` (or set `YANGS_CHROMIUM` to a `chrome.exe`); otherwise run `python -m playwright install chromium`.
- A running app with a sellable product named **Red Horse** (₱85.00), at least one **Available** table, and a user with POS, payment, receipt and transaction permissions. Use the local database, never production: the run records real tabs and payments.
- The setting **Mark the table as Cleaning** (after payment) must be off: the run checks that the table is Available again.
- A user without `orders.view-all` still passes: the history check looks for a tab that user closed.

## Run

```powershell
$env:YANGS_URL = 'http://127.0.0.1:8000'
$env:YANGS_USER = '<username>'
$env:YANGS_PASSWORD = '<password>'
$env:YANGS_SHOTS = 'docs/screenshots/step-13'   # optional: save screenshots
python tests/Browser/open_tab_flow.py
```

Use port 8000 (or add yours to `SANCTUM_STATEFUL_DOMAINS`): the POS screens call `/api/v1` with the browser session. Exit code 0 means every check passed; on failure it prints the failing line.

To let the webapp-testing helper start and stop the server:

```powershell
python <claude skills>/webapp-testing/scripts/with_server.py --server "php artisan serve --port=8000" --port 8000 -- python tests/Browser/open_tab_flow.py
```
