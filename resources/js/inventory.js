// Inventory screens. The stock-in line editor only previews; InventoryService computes the stored figures.

const MONEY = /^\d{1,10}(\.\d{0,2})?$/;
const QUANTITY = /^\d{1,10}(\.\d{0,3})?$/;

/** Whole centavos for a typed peso amount, or null. */
const cents = (text) => {
    const value = String(text ?? '').trim();
    if (!MONEY.test(value)) return null;
    const [whole, fraction = ''] = value.split('.');
    return Number(whole) * 100 + Number(fraction.padEnd(2, '0'));
};

const peso = (centavos) => '₱' + Math.floor(centavos / 100).toLocaleString('en-PH') + '.' + String(centavos % 100).padStart(2, '0');

/** "48.000" → "48", "2.500" → "2.5". */
const number = (text) => String(text).replace(/(\.\d*?)0+$/, '$1').replace(/\.$/, '');

export function registerInventory(Alpine) {
    Alpine.data('stockIn', ({ items, lines, blank, errors }) => ({
        items,
        // A stable key per row, so removing a middle row keeps the others' inputs.
        lines: lines.map((line, i) => ({ ...blank, ...line, key: i })),
        nextKey: lines.length,
        errors,
        sending: false,
        number,

        itemOf(line) {
            return this.items.find((item) => String(item.id) === String(line.inventory_item_id)) ?? null;
        },
        unitOf(line) {
            return this.itemOf(line)?.units.find((unit) => String(unit.id) === String(line.inventory_unit_id)) ?? null;
        },
        error(i, field) {
            return this.errors[`lines.${i}.${field}`]?.[0] ?? null;
        },
        add() {
            this.lines.push({ ...blank, key: this.nextKey++ });
        },
        remove(i) {
            this.lines.splice(i, 1);
            this.errors = {}; // row numbers shifted; the old messages no longer point at the right rows
        },
        /** "= 48 bottle at about ₱30.00 each" while the line is complete enough to say it. */
        preview(line) {
            const item = this.itemOf(line);
            const quantity = String(line.quantity ?? '').trim();
            if (!item || !QUANTITY.test(quantity) || Number(quantity) <= 0) return '';
            const base = Number(quantity) * Number(this.unitOf(line)?.factor ?? 1);
            const cost = cents(line.line_cost);
            const each = cost === null ? '' : ` at about ${peso(Math.round(cost / base))} each`;
            return `= ${number(base.toFixed(3))} ${item.base_unit}${each}`;
        },
        total() {
            return peso(this.lines.reduce((sum, line) => sum + (cents(line.line_cost) ?? 0), 0));
        },
    }));

    // Product page: one editable recipe per way the product is sold.
    Alpine.data('recipeEditor', ({ rows, blank }) => ({
        rows: rows.length ? rows.map((row, i) => ({ ...row, key: i })) : [{ ...blank, key: 0 }],
        nextKey: Math.max(rows.length, 1),
        add() {
            this.rows.push({ ...blank, key: this.nextKey++ });
        },
        remove(i) {
            this.rows.splice(i, 1);
        },
    }));
}
