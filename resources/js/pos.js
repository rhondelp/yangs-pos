// POS screens: Alpine components that talk to /api/v1 with the browser session (Sanctum SPA mode).

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

/** JSON request to the internal API. Throws an Error whose message is safe to show the cashier. */
export async function api(method, url, body) {
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: body === undefined ? undefined : JSON.stringify(body),
    });

    // Signed out, deactivated, or the page's CSRF token expired: start again from the login screen.
    if (response.status === 401 || response.status === 419) {
        window.location.href = '/login';
        throw new Error('Your session ended. Sign in again.');
    }

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        const first = Object.values(data.errors ?? {})[0]?.[0];
        throw new Error(first ?? data.message ?? 'Something went wrong. Try again.');
    }

    return data;
}

// Money arrives as 2-decimal strings. Draft totals are summed in whole centavos (no float rounding);
// the server's running bill stays the source of truth.
const toCents = (amount) => {
    const [whole, cents = '0'] = String(amount).split('.');
    return Number(whole) * 100 + Number(cents.padEnd(2, '0').slice(0, 2));
};

export const peso = (amount) => {
    const cents = typeof amount === 'number' ? amount : toCents(amount);
    return '₱' + Math.floor(cents / 100).toLocaleString('en-PH') + '.' + String(cents % 100).padStart(2, '0');
};

const clock = (iso) => new Date(iso).toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit', timeZone: 'Asia/Manila' });

export function registerPos(Alpine) {
    // Floor: "Open tab" on a free table, then go to its POS screen.
    Alpine.data('openTab', ({ url, posUrl }) => ({
        busy: false,
        error: null,
        async open() {
            if (this.busy) return;
            this.busy = true;
            this.error = null;
            try {
                const { data } = await api('POST', url);
                window.location.href = posUrl.replace('__ID__', data.id);
            } catch (e) {
                this.error = e.message;
                this.busy = false;
            }
        },
    }));

    Alpine.data('posScreen', (boot) => ({
        menu: boot.menu,
        session: boot.session,
        tabs: boot.tabs,
        urls: boot.urls,
        category: boot.menu[0]?.id ?? null,
        query: '',
        draft: [],
        picking: null,
        panel: 'draft', // landscape side panel: draft | tab
        sending: false,
        error: null,
        toast: null,
        peso,
        clock,

        init() {
            // Another tablet may add to this tab; catch up whenever the cashier comes back to this screen.
            document.addEventListener('visibilitychange', () => document.visibilityState === 'visible' && this.refresh());
        },

        get products() {
            const q = this.query.trim().toLowerCase();
            if (q) return this.menu.flatMap((c) => c.products).filter((p) => p.name.toLowerCase().includes(q));
            return this.menu.find((c) => c.id === this.category)?.products ?? [];
        },
        get draftCount() {
            return this.draft.reduce((n, line) => n + line.quantity, 0);
        },
        get draftTotal() {
            return this.draft.reduce((sum, line) => sum + toCents(line.price) * line.quantity, 0);
        },
        get activeItems() {
            return this.session.orders.flatMap((o) => o.items).filter((i) => i.status === 'active');
        },

        tap(product) {
            if (!product.available) return;
            if (product.variants.length) {
                this.picking = product;
                this.$refs.variants.showModal();
            } else {
                this.add(product, null);
            }
        },
        pick(variant) {
            this.add(this.picking, variant);
            this.$refs.variants.close();
        },
        add(product, variant) {
            const key = `${product.id}:${variant?.id ?? 0}`;
            const line = this.draft.find((l) => l.key === key);
            if (line) {
                line.quantity = Math.min(99, line.quantity + 1);
            } else {
                this.draft.push({
                    key,
                    product_id: product.id,
                    variant_id: variant?.id ?? null,
                    name: product.name,
                    variant: variant?.name ?? null,
                    price: variant?.price ?? product.price,
                    quantity: 1,
                });
            }
            this.flash(variant ? `${product.name}, ${variant.name} added` : `${product.name} added`);
        },
        lineTotal(line) {
            return peso(toCents(line.price) * line.quantity);
        },
        step(line, by) {
            line.quantity = Math.max(1, Math.min(99, line.quantity + by));
        },
        remove(line) {
            this.draft = this.draft.filter((l) => l !== line);
        },

        async send() {
            if (this.sending || this.draft.length === 0) return;
            this.sending = true;
            this.error = null;
            try {
                const { data } = await api('POST', this.urls.orders, {
                    lines: this.draft.map((l) => ({ product_id: l.product_id, variant_id: l.variant_id, quantity: l.quantity })),
                });
                this.draft = [];
                this.flash(`Order ${data.reference} sent`);
                await this.refresh();
                this.panel = 'tab';
                this.$refs.draftSheet?.close();
            } catch (e) {
                this.error = e.message;
            } finally {
                this.sending = false;
            }
        },
        async refresh() {
            try {
                this.session = (await api('GET', this.urls.session)).data;
            } catch (e) {
                this.error = e.message;
            }
        },
        flash(message) {
            this.toast = message;
            clearTimeout(this._toastTimer);
            this._toastTimer = setTimeout(() => (this.toast = null), 2500);
        },
    }));
}
