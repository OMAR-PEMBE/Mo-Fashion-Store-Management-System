// Exact money arithmetic for the counter: amounts are handled as BigInt cents, never floats.
const toCents = (value) => {
    const text = String(value ?? '').trim();
    if (!/^\d+(\.\d{1,2})?$/.test(text)) return null;
    const [whole, fraction = ''] = text.split('.');
    return BigInt(whole) * 100n + BigInt(fraction.padEnd(2, '0'));
};
const fromCents = (cents) => {
    const negative = cents < 0n;
    const absolute = negative ? -cents : cents;
    return (negative ? '-' : '') + (absolute / 100n) + '.' + String(absolute % 100n).padStart(2, '0');
};
const wholeNumber = (value) => /^[1-9]\d*$/.test(String(value));

// Point of sale and new-order cart. The server recalculates and rechecks everything on submit;
// totals here are for display so staff can answer "how much?" at any moment.
window.posForm = ({ customer, lines, lookupUrl, customerUrl, csrf, canOverridePrice, isOrder, payment, notes }) => ({
    customer,
    lines: lines.map((line) => ({ ...line, adjust: (toCents(line.discount_amount) ?? 0n) > 0n || toCents(line.unit_price) !== toCents(line.catalogue_price) })),
    payment: payment || '',
    notes: notes || '',
    query: '', results: [], searching: false, searchError: '', searchRequest: 0, searchTimer: null,
    customerOpen: false, customerQuery: '', customerResults: [], customerSearching: false, customerError: '', customerRequest: 0, customerTimer: null,
    quick: { open: false, full_name: '', phone: '', allow_duplicate: false, needsDuplicate: false, errors: {}, saving: false },
    cartOpen: false, flashId: null, announcement: '',

    init() {
        // Restored carts sell at catalogue price unless this person may change prices.
        if (!canOverridePrice) this.lines.forEach((line) => { line.unit_price = line.catalogue_price; });
        this.search();
    },

    // Product search: type to search; Enter on an exact code (or a single match) adds it.
    queueSearch() {
        clearTimeout(this.searchTimer);
        this.searchTimer = setTimeout(() => this.search(), 250);
    },
    async search() {
        const request = ++this.searchRequest;
        this.searching = true;
        this.searchError = '';
        try {
            const response = await fetch(lookupUrl + '?' + new URLSearchParams({ kind: 'variant', q: this.query.trim() }), { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error();
            const results = await response.json();
            if (request !== this.searchRequest) return;
            this.results = results;
        } catch {
            if (request === this.searchRequest) {
                this.results = [];
                this.searchError = 'Search failed. Check the connection and try again.';
            }
        } finally {
            if (request === this.searchRequest) this.searching = false;
        }
    },
    async submitSearch() {
        clearTimeout(this.searchTimer);
        const code = this.query.trim().toUpperCase();
        await this.search();
        const exact = this.results.find((result) => result.sku.toUpperCase() === code);
        const pick = exact || (this.results.length === 1 ? this.results[0] : null);
        if (pick && pick.available > 0) {
            this.add(pick);
            this.query = '';
            this.search();
        }
    },
    clearSearch() {
        this.query = '';
        this.search();
        this.$refs.search.focus();
    },

    inCart(id) {
        return this.lines.find((line) => Number(line.product_variant_id) === Number(id));
    },
    add(result) {
        if (result.available < 1) return;
        const existing = this.inCart(result.id);
        if (existing) {
            if (isOrder || Number(existing.quantity) < result.available) existing.quantity = String(Number(existing.quantity) + 1);
        } else if (this.lines.length < 100) {
            this.lines.push({ product_variant_id: result.id, name: result.name, variant: result.variant, sku: result.sku, quantity: '1',
                unit_price: result.unit_price, catalogue_price: result.unit_price, discount_amount: '0.00', available: result.available, adjust: false });
        }
        this.flashId = result.id;
        setTimeout(() => { if (this.flashId === result.id) this.flashId = null; }, 700);
        this.announcement = 'Added ' + result.name + (result.variant ? ', ' + result.variant : '') + '. Total ' + formatMoney(this.total) + '.';
    },
    increase(line) {
        if (isOrder || Number(line.quantity) < line.available) line.quantity = String((Number(line.quantity) || 0) + 1);
    },
    decrease(line) {
        if (Number(line.quantity) > 1) line.quantity = String(Number(line.quantity) - 1);
    },
    remove(index) {
        const [line] = this.lines.splice(index, 1);
        this.announcement = 'Removed ' + line.name + '.';
    },
    overStock(line) {
        return !isOrder && Number(line.quantity) > line.available;
    },
    priceChanged(line) {
        const price = toCents(line.unit_price);
        return price !== null && price !== toCents(line.catalogue_price);
    },

    lineTotal(line) {
        const price = toCents(line.unit_price);
        const discount = toCents(line.discount_amount || '0');
        if (price === null || discount === null || !wholeNumber(line.quantity)) return null;
        return fromCents(price * BigInt(line.quantity) - discount);
    },
    get subtotal() {
        return fromCents(this.lines.reduce((sum, line) => {
            const price = toCents(line.unit_price);
            return price === null || !wholeNumber(line.quantity) ? sum : sum + price * BigInt(line.quantity);
        }, 0n));
    },
    get discountTotal() {
        return fromCents(this.lines.reduce((sum, line) => sum + (toCents(line.discount_amount || '0') ?? 0n), 0n));
    },
    get total() {
        return fromCents(toCents(this.subtotal) - toCents(this.discountTotal));
    },
    get itemCount() {
        return this.lines.reduce((count, line) => count + (Number(line.quantity) || 0), 0);
    },
    get hasDiscount() {
        return this.lines.some((line) => (toCents(line.discount_amount || '0') ?? 0n) > 0n);
    },

    // The first thing still needed, shown under the checkout button.
    get blocker() {
        if (!this.lines.length) return 'Add at least one item.';
        for (const line of this.lines) {
            if (!wholeNumber(line.quantity)) return 'Enter a whole-number quantity for ' + line.name + '.';
            if (this.overStock(line)) return 'Only ' + line.available + ' of ' + line.name + ' in stock.';
            if (toCents(line.unit_price) === null) return 'Check the price for ' + line.name + '.';
            const discount = toCents(line.discount_amount || '0');
            if (discount === null) return 'Check the discount for ' + line.name + '.';
            if (discount > toCents(line.unit_price) * BigInt(line.quantity)) return 'The discount on ' + line.name + ' is larger than its price.';
            if (!canOverridePrice && this.priceChanged(line)) return 'Only an administrator can change a price.';
        }
        if (isOrder && !this.customer.id) return 'Choose a registered customer for the order.';
        if (!isOrder && !this.payment) return 'Choose a payment method.';
        if (this.hasDiscount && !this.notes.trim()) return 'Give a reason for the discount.';
        return '';
    },

    // Customer: search, walk-in, or quick registration without leaving the sale.
    queueCustomerSearch() {
        clearTimeout(this.customerTimer);
        this.customerTimer = setTimeout(() => this.searchCustomers(), 250);
    },
    async searchCustomers() {
        const request = ++this.customerRequest;
        const q = this.customerQuery.trim();
        if (!q) {
            this.customerResults = [];
            this.customerError = '';
            return;
        }
        this.customerSearching = true;
        this.customerError = '';
        try {
            const response = await fetch(lookupUrl + '?' + new URLSearchParams({ kind: 'customer', q }), { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error();
            const results = await response.json();
            if (request !== this.customerRequest) return;
            this.customerResults = results;
            if (!results.length) this.customerError = 'No customer found. You can register them now.';
        } catch {
            if (request === this.customerRequest) this.customerError = 'Search failed. Try again.';
        } finally {
            if (request === this.customerRequest) this.customerSearching = false;
        }
    },
    chooseCustomer(result) {
        this.customer = { id: result.id, label: result.name, detail: result.detail };
        this.customerOpen = false;
        this.customerQuery = '';
        this.customerResults = [];
        this.quick.open = false;
    },
    walkIn() {
        this.customer = { id: '', label: 'Walk-in customer', detail: '' };
        this.customerOpen = false;
    },
    startQuickCustomer() {
        this.quick = { open: true, full_name: this.customerQuery.trim(), phone: '', allow_duplicate: false, needsDuplicate: false, errors: {}, saving: false };
        this.$nextTick(() => this.$refs.quickName?.focus());
    },
    async saveQuickCustomer() {
        if (this.quick.saving) return;
        this.quick.saving = true;
        this.quick.errors = {};
        try {
            const response = await fetch(customerUrl, {
                method: 'POST',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ full_name: this.quick.full_name, phone: this.quick.phone || null, allow_duplicate: this.quick.allow_duplicate ? 1 : 0 }),
            });
            const body = await response.json().catch(() => ({}));
            if (response.status === 422) {
                this.quick.errors = body.errors || {};
                this.quick.needsDuplicate = Boolean(this.quick.errors.allow_duplicate);
                return;
            }
            if (!response.ok) throw new Error();
            this.chooseCustomer(body);
            this.announcement = 'Customer ' + body.name + ' registered and selected.';
        } catch {
            this.quick.errors = { full_name: ['Could not save the customer. Try again.'] };
        } finally {
            this.quick.saving = false;
        }
    },
});
