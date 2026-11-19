"""
YANGS_SPEC §4B on the tablet UI, in portrait (768x1024) and landscape (1024x768):
floor -> open a free table -> tap products -> send order -> bill -> GCash + cash -> change -> receipt -> transactions.

Run against a running app with a seeded "Red Horse" product and a free table (see tests/Browser/README.md):
    YANGS_URL=http://127.0.0.1:8000 YANGS_USER=... YANGS_PASSWORD=... python tests/Browser/open_tab_flow.py
or let the webapp-testing helper start the server:
    python <skills>/webapp-testing/scripts/with_server.py --server "php artisan serve --port=8000" --port 8000 -- python tests/Browser/open_tab_flow.py
Exit code 0 means every check passed.
"""

import glob
import traceback
import os
import re
import sys

from playwright.sync_api import expect, sync_playwright

BASE = os.environ.get("YANGS_URL", "http://127.0.0.1:8000")
USER = os.environ["YANGS_USER"]
PASSWORD = os.environ["YANGS_PASSWORD"]
SHOTS = os.environ.get("YANGS_SHOTS")  # optional folder for screenshots


def chromium_path():
    """Reuse an installed Playwright Chromium if this Python package's own build is not downloaded."""
    found = sorted(glob.glob(os.path.expanduser("~/AppData/Local/ms-playwright/chromium-*/chrome-win64/chrome.exe")))
    return os.environ.get("YANGS_CHROMIUM") or (found[-1] if found else None)


def shot(page, name):
    if SHOTS:
        os.makedirs(SHOTS, exist_ok=True)
        page.screenshot(path=os.path.join(SHOTS, f"{name}.png"))


def run(browser, width, height):
    tag = f"{width}x{height}"
    page = browser.new_page(viewport={"width": width, "height": height})
    errors = []
    page.on("pageerror", lambda e: errors.append(str(e)))

    # Sign in.
    page.goto(f"{BASE}/login")
    page.fill("#username", USER)
    page.fill("#password", PASSWORD)
    page.click("button[type=submit]")
    page.wait_for_load_state("networkidle")

    # 1-2. Pick a free table on the floor and open its tab.
    # The floor's own status filter: matching "Available" text would also hit a Cleaning table's "Mark available" button.
    page.goto(f"{BASE}/floor?status=available")
    page.wait_for_load_state("networkidle")
    card = page.locator("ul > li").first
    label = card.locator(".font-display").first.inner_text().strip()
    card.locator("button").first.click()
    page.locator("dialog[open]").get_by_role("button", name="Open tab").click()
    page.wait_for_url(re.compile(r"/pos/sessions/\d+$"))
    page.wait_for_load_state("networkidle")
    shot(page, f"1-pos-{tag}")

    # 3-4. Tap two Red Horse and send the order.
    tile = page.get_by_role("button", name=re.compile(r"^Red Horse"))
    tile.first.click()
    tile.first.click()
    if width < 1024:  # portrait: the order lives in a bottom sheet
        page.locator("[data-pos=open-order]").click()
        page.locator("dialog[open]").get_by_role("button", name="Send order").click()
    else:
        page.get_by_role("button", name="Send order").filter(visible=True).click()
    expect(page.get_by_role("status")).to_contain_text("sent")
    if width >= 1024:  # landscape: after sending, the side panel switches to the tab once the bill reloads
        expect(page.get_by_role("tab", name=re.compile(r"^Tab"))).to_have_attribute("aria-selected", "true")

    # 5-7. A second round joins the same tab; the running total follows.
    tile.first.click()
    if width < 1024:
        page.locator("[data-pos=open-order]").click()
        page.locator("dialog[open]").get_by_role("button", name="Send order").click()
    else:
        page.get_by_role("tab", name=re.compile("New order")).click()
        page.get_by_role("button", name="Send order").filter(visible=True).click()
    expect(page.get_by_text("₱255.00").filter(visible=True).first).to_be_visible()
    shot(page, f"2-tab-{tag}")

    # 8-9. Open the consolidated bill.
    if width < 1024:
        page.locator("[data-pos=open-tab]").click()
        page.locator("dialog[open]").get_by_role("link", name="Bill").click()
    else:
        page.get_by_role("link", name="Bill").first.click()
    page.wait_for_url(re.compile(r"/bill$"))
    # Each round stays its own line, with the price it was ordered at.
    expect(page.get_by_text("2 × Red Horse").filter(visible=True)).to_have_count(1)
    expect(page.get_by_text("1 × Red Horse").filter(visible=True)).to_have_count(1)
    expect(page.locator("[aria-live=polite]").first).to_have_text("₱255.00")

    # 10. GCash ₱100 with a reference, then the rest in cash with ₱500 handed over.
    page.get_by_role("button", name="GCash", exact=True).click()
    page.fill("#pay-amount", "100")
    page.fill("#pay-reference", "GC-UI-1")
    page.locator("[data-bill=pay]").click()
    expect(page.get_by_text("Paid so far")).to_be_visible()
    page.get_by_role("button", name="Cash", exact=True).click()
    page.get_by_role("group", name="Quick cash").get_by_role("button", name="₱500.00").click()
    expect(page.locator("[data-bill=pay]")).to_contain_text("Take ₱155.00 Cash")
    page.locator("[data-bill=pay]").click()

    # 11-12. Paid, change shown, table free again.
    expect(page.get_by_role("status").filter(has_text="Give change")).to_contain_text("₱345.00")
    shot(page, f"3-paid-{tag}")
    page.get_by_role("link", name="Print receipt").click()
    page.wait_for_url(re.compile(r"/receipts/\d+"))
    receipt = page.locator("main").inner_text()
    number = re.search(r"RCP-\d{8}-\d{4}", receipt).group(0)
    assert "₱255.00" in receipt and "₱345.00" in receipt, receipt

    page.goto(f"{BASE}/floor?status=available")
    exact_label = page.locator(".font-display", has_text=re.compile(rf"^{re.escape(label)}$"))  # T1 must not match T10
    expect(page.locator("ul > li").filter(has=exact_label)).to_have_count(1)

    # The paid tab is in the transaction history.
    page.goto(f"{BASE}/transactions")
    expect(page.get_by_role("cell", name=number, exact=True)).to_be_visible()
    shot(page, f"4-transactions-{tag}")

    assert not errors, errors
    page.close()
    return f"{tag}: table {label}, {number}"


def main():
    sys.stdout.reconfigure(encoding="utf-8")  # peso signs in messages; Windows consoles default to cp1252
    sys.stderr.reconfigure(encoding="utf-8")
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True, executable_path=chromium_path())
        try:
            for size in [(768, 1024), (1024, 768)]:
                print("ok", run(browser, *size))
        finally:
            browser.close()


if __name__ == "__main__":
    try:
        main()
    except Exception as e:  # noqa: BLE001 - report and fail the run
        traceback.print_exception(e, file=sys.stderr)  # the failing check, without the long aria dump
        sys.exit(1)
