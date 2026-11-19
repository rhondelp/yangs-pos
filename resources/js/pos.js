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

const MONEY = /^\d{1,10}(\.\d{0,2})?$/;
/** Whole centavos for a typed amount, or null when it is not a valid peso amount. */
const typedCents = (text) => (MONEY.test(text) ? toCents(text) : null);
const centsText = (cents) => `${Math.floor(cents / 100)}.${String(cents % 100).padStart(2, '0')}`;

/** UUID v4 from getRandomValues: crypto.randomUUID needs HTTPS, and the bar's tablets may use plain HTTP on the LAN. */
const uuid = () => {
    const b = crypto.getRandomValues(new Uint8Array(16));
    b[6] = (b[6] & 0x0f) | 0x40;
    b[8] = (b[8] & 0x3f) | 0x80;
    const h = [...b].map((x) => x.toString(16).padStart(2, '0')).join('');
    return `${h.slice(0, 8)}-${h.slice(8, 12)}-${h.slice(12, 16)}-${h.slice(16, 20)}-${h.slice(20)}`;
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

    // Bill screen: take one or more payments, then show the change and the receipt link.
    Alpine.data('billScreen', (boot) => ({
        session: boot.session,
        methods: boot.methods,
        urls: boot.urls,
        methodId: boot.methods[0]?.id ?? null,
        target: boot.methods[0]?.is_cash ? 'tendered' : 'amount', // field the keypad types into
        amount: '', // blank: the whole balance
        tendered: '', // blank: exact cash
        reference: '',
        // One key per payment attempt. Kept after a failed or lost request, so a resend can never pay twice.
        key: uuid(),
        busy: false,
        error: null,
        changeDue: 0,
        discount: { type: 'percent', value: '', reason: '', error: null },
        voiding: { kind: 'item', target: null, quantity: 1, reason: '', approver_username: '', approver_pin: '', key: null, error: null },
        peso,
        clock,

        init() {
            document.addEventListener('visibilitychange', () => document.visibilityState === 'visible' && !this.closed && this.refresh());
        },

        get closed() {
            return this.session.status === 'closed';
        },
        get free() {
            return this.session.bill.balance === '0.00'; // nothing to pay on a live tab: settle (or add items)
        },
        get method() {
            return this.methods.find((m) => m.id === this.methodId) ?? null;
        },
        get ordersWithItems() {
            return this.session.bill.orders
                .map((o) => ({ ...o, items: this.session.bill.items.filter((i) => i.order_id === o.id) }))
                .filter((o) => o.items.length);
        },
        // Voids are for tabs that already took money; before that, items are cancelled on the order screen.
        get canVoid() {
            return !!this.urls.voidItem && !this.closed && this.session.bill.paid !== '0.00';
        },
        get amountCents() {
            return this.amount.trim() === '' ? toCents(this.session.bill.balance) : (typedCents(this.amount.trim()) ?? 0);
        },
        get amountText() {
            return centsText(this.amountCents);
        },
        get changeCents() {
            const tendered = this.tendered.trim() === '' ? this.amountCents : (typedCents(this.tendered.trim()) ?? 0);
            return tendered - this.amountCents;
        },
        get quickCash() {
            const a = this.amountCents;
            const up = (step) => Math.ceil(a / step) * step;
            return [...new Set([a, up(10000), up(50000), up(100000)])].filter((c) => c > 0).slice(0, 4);
        },

        choose(m) {
            this.methodId = m.id;
            this.target = m.is_cash ? 'tendered' : 'amount';
            this.error = null;
        },
        press(k) {
            const field = this.target === 'amount' ? 'amount' : 'tendered';
            let v = this[field];
            if (k === '⌫') v = v.slice(0, -1);
            else if (k === '.') v = v.includes('.') ? v : (v || '0') + '.';
            else if (/\.\d{2}$/.test(v) || (!v.includes('.') && v.length >= 10)) return;
            else v = (v === '0' ? '' : v) + k;
            this[field] = v;
        },

        async pay() {
            if (this.busy || !this.method) return;
            this.busy = true;
            this.error = null;
            const cash = this.method.is_cash;
            const tendered = this.tendered.trim();
            try {
                const res = await api('POST', this.urls.payments, {
                    method_id: this.method.id,
                    idempotency_key: this.key,
                    // Always the amount on the button: a blank would let the server charge a balance another tablet raised.
                    amount: this.amountText,
                    // Normalised ("50." becomes "50.00"); text that is not a number goes as typed so the server names the error.
                    tendered: !cash || tendered === '' ? null : typedCents(tendered) === null ? tendered : centsText(typedCents(tendered)),
                    reference_no: cash ? null : this.reference.trim() || null,
                });
                this.key = uuid();
                this.session = res.data;
                this.changeDue = toCents(res.payment.change_given);
                this.amount = this.tendered = this.reference = '';
            } catch (e) {
                this.error = e.message;
                await this.refresh(true); // another tablet may have paid meanwhile
            } finally {
                this.busy = false;
            }
        },
        async settle() {
            await this.send('POST', this.urls.settle, undefined, (m) => (this.error = m));
        },

        openDiscount() {
            const b = this.session.bill;
            this.discount = { type: b.bill_discount_type ?? 'percent', value: b.bill_discount_value ?? '', reason: b.bill_discount_reason ?? '', error: null };
            this.$refs.discount.showModal();
        },
        async applyDiscount() {
            const { type, value, reason } = this.discount;
            const ok = await this.send('POST', this.urls.discount, { type, value: value.trim(), reason: reason.trim() }, (m) => (this.discount.error = m));
            if (ok) this.$refs.discount.close();
        },
        async removeDiscount() {
            await this.send('DELETE', this.urls.discount, undefined, (m) => (this.error = m));
        },

        openVoid(kind, target) {
            // One key per dialog: a retry after a lost reply returns the first void instead of voiding again.
            this.voiding = { ...this.voiding, kind, target, quantity: 1, reason: '', approver_pin: '', key: uuid(), error: null };
            this.$refs.void.showModal();
        },
        async submitVoid() {
            if (this.busy) return;
            const { kind, target, quantity, reason, approver_username, approver_pin, key } = this.voiding;
            const url = (kind === 'order' ? this.urls.voidOrder : this.urls.voidItem).replace('__ID__', target.id);
            this.busy = true;
            this.voiding.error = null;
            try {
                const body = { reason: reason.trim(), approver_username, approver_pin, idempotency_key: key, ...(kind === 'item' ? { quantity } : {}) };
                this.session = (await api('POST', url, body)).bill;
                this.$refs.void.close();
            } catch (e) {
                this.voiding.error = e.message;
            } finally {
                this.busy = false;
            }
        },

        /** Sends a bill change and takes the bill from the reply. Returns whether it worked. */
        async send(method, url, body, onError) {
            if (this.busy) return false;
            this.busy = true;
            try {
                this.session = (await api(method, url, body)).data;
                return true;
            } catch (e) {
                onError(e.message);
                return false;
            } finally {
                this.busy = false;
            }
        },
        async refresh(quiet = false) {
            try {
                this.session = (await api('GET', this.urls.bill)).data;
            } catch (e) {
                if (!quiet) this.error = e.message;
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
        cancelling: { item: null, quantity: 1, reason: '', error: null }, // item null: the whole tab
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
            return this.session.orders.flatMap((o) => o.items).filter((i) => i.status === 'active' && i.quantity > i.voided_quantity);
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
        openCancel(item) {
            this.cancelling = { item, quantity: item.quantity, reason: '', error: null };
            this.$refs.cancel.showModal();
        },
        openCancelTab() {
            this.cancelling = { item: null, quantity: 1, reason: '', error: null };
            this.$refs.cancel.showModal();
        },
        async submitCancel() {
            if (this.sending) return;
            const { item, quantity, reason } = this.cancelling;
            this.sending = true;
            this.cancelling.error = null;
            try {
                if (item) {
                    await api('POST', this.urls.cancelItem.replace('__ID__', item.id), { reason: reason.trim(), quantity });
                    this.flash(`${item.product_name} cancelled`);
                    await this.refresh();
                    this.$refs.cancel.close();
                } else {
                    await api('POST', this.urls.cancelSession, { reason: reason.trim() });
                    window.location.href = this.urls.floor;
                }
            } catch (e) {
                this.cancelling.error = e.message;
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
