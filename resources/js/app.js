// Livewire initializes its bundled Alpine instance through @livewireScripts.
import './pos';
window.saleForm = (customer, lines, lookupUrl, allowUnavailable = false) => ({
    customer, lines, query: '', customersQuery: '', results: [], customers: [], error: '', searchRequest: 0,
    async search(kind) {
        const request = ++this.searchRequest;
        this.error = '';
        try {
            const response = await fetch(lookupUrl + '?' + new URLSearchParams({kind, q: kind === 'variant' ? this.query : this.customersQuery}), {headers: {Accept: 'application/json'}});
            if (!response.ok) throw new Error();
            const results = await response.json();
            if(request !== this.searchRequest) return;
            if(kind === 'variant') this.results = results; else this.customers = results;
            if(!results.length) this.error = 'No matches. Try another name or code.';
        } catch { this.error = 'Search failed. Please try again.'; }
    },
    add(result) {
        if(result.available < 1 && !allowUnavailable) return;
        const line = this.lines.find(line => Number(line.product_variant_id) === result.id);
        if(line) line.quantity = String(Number(line.quantity) + 1);
        else if(this.lines.length < 100) this.lines.push({product_variant_id:result.id,label:result.label,name:result.name,variant:result.variant,sku:result.sku,quantity:'1',unit_price:result.unit_price,discount_amount:'0.00'});
    },
});
window.purchaseForm = (supplier, lines, lookupUrl) => ({
    supplier: { ...supplier, query: '', results: [], error: '', searched: '', request: 0 },
    lines: lines.map(line => ({ ...line, key: crypto.randomUUID(), query: '', results: [], error: '', searched: '', request: 0 })),
    init() { if (!this.lines.length) this.add(); },
    add() {
        if (this.lines.length >= 100) return;
        this.lines.push({ key: crypto.randomUUID(), product_variant_id: '', label: '', quantity: '1', unit_cost: '', query: '', results: [], error: '', searched: '', request: 0 });
    },
    async search(target, kind) {
        const request = ++target.request;
        target.error = '';
        try {
            const response = await fetch(lookupUrl + '?' + new URLSearchParams({ kind, q: target.query }), { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error();
            const results = await response.json();
            if (request === target.request) { target.results = results; target.searched = target.query.trim(); }
        } catch {
            if (request === target.request) { target.results = []; target.error = 'Search failed. Please try again.'; }
        }
    },
    choose(target, result, field) {
        target[field] = result.id;
        target.label = result.label;
        target.query = '';
        target.results = [];
        target.request++;
    },
});

// Temporary staff passwords: 12 characters without look-alikes (0/O, 1/l/I), always with
// letters and numbers, grouped in threes so they are easy to read out.
window.suggestPassword = () => {
    const letters = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';
    const digits = '23456789';
    const pick = (set, n) => Array.from(crypto.getRandomValues(new Uint32Array(n)), v => set[v % set.length]);
    const chars = [...pick(letters, 7), ...pick(digits, 3), ...pick(letters + digits, 2)];
    for (let i = chars.length - 1; i > 0; i--) {
        const j = crypto.getRandomValues(new Uint32Array(1))[0] % (i + 1);
        [chars[i], chars[j]] = [chars[j], chars[i]];
    }
    return chars.join('').match(/.{3}/g).join('-');
};
// Opening stock count sheet: live totals, "same cost for every size", and a warning before
// leaving the page with counts that have not been reviewed yet.
window.openingSheet = () => ({
    items: 0, units: 0, cents: 0, submitting: false,
    init() {
        this.refresh();
        window.addEventListener('beforeunload', event => {
            if (this.items > 0 && !this.submitting) event.preventDefault();
        });
    },
    refresh() {
        let items = 0, units = 0, cents = 0;
        this.$root.querySelectorAll('[data-qty]').forEach(qty => {
            const cost = qty.closest('li').querySelector('[data-cost]');
            const count = parseInt(qty.value, 10);
            const each = Number(cost.value.trim());
            if (count > 0 && cost.value.trim() !== '' && Number.isFinite(each) && each >= 0) {
                items++; units += count; cents += count * Math.round(each * 100);
            }
        });
        Object.assign(this, { items, units, cents });
    },
    sameCost(button) {
        const costs = [...button.closest('[data-group]').querySelectorAll('[data-cost]')];
        const first = costs.find(input => input.value.trim() !== '');
        if (!first) { costs[0]?.focus(); return; }
        // Only sizes with a count: a cost on an uncounted row would read as a half-filled line.
        costs.forEach(input => {
            const counted = input.closest('li').querySelector('[data-qty]').value.trim() !== '';
            if (counted && input.value.trim() === '') input.value = first.value.trim();
        });
        this.refresh();
    },
});
// Show/hide toggles for <x-input revealable>. Buttons stay hidden until this script runs.
document.querySelectorAll('[data-password-toggle]').forEach(button => { button.hidden = false; });
document.addEventListener('click', event => {
    const button = event.target.closest('[data-password-toggle]');
    const input = button && document.getElementById(button.dataset.passwordToggle);
    if (!input) return;
    const reveal = input.type === 'password';
    input.type = reveal ? 'text' : 'password';
    button.setAttribute('aria-pressed', String(reveal));
    button.setAttribute('aria-label', reveal ? 'Hide password' : 'Show password');
    button.querySelector('[data-icon="show"]').classList.toggle('hidden', reveal);
    button.querySelector('[data-icon="hide"]').classList.toggle('hidden', !reveal);
    input.focus();
});

// Forms marked data-busy disable their submit button once sent, preventing double submissions.
document.addEventListener('submit', event => {
    // Revealed passwords go back to password fields so browsers do not store them as form history.
    event.target.querySelectorAll('[data-password-toggle]').forEach(toggle => {
        const input = document.getElementById(toggle.dataset.passwordToggle);
        if (input) input.type = 'password';
    });
    const button = event.target.matches('[data-busy]') && event.target.querySelector('[data-busy-label]');
    if (!button || event.defaultPrevented) return;
    button.dataset.idleLabel = button.textContent;
    button.textContent = button.dataset.busyLabel;
    button.setAttribute('aria-busy', 'true');
    // Deferred so the browser has already captured the submission.
    setTimeout(() => { button.disabled = true; });
});
// Pages restored from the back/forward cache must be usable again.
window.addEventListener('pageshow', () => {
    document.querySelectorAll('[data-busy-label][aria-busy="true"]').forEach(button => {
        button.disabled = false;
        button.removeAttribute('aria-busy');
        button.textContent = button.dataset.idleLabel;
    });
});

// Mirrors App\Support\Money::format for amounts drawn in the browser: "TZS 45,000", cents only when present.
window.formatMoney = (value) => {
    const [whole, cents = '00'] = String(value ?? '0').replace('-', '').split('.');
    const text = 'TZS ' + Number(whole).toLocaleString('en-US') + (cents.padEnd(2, '0').slice(0, 2) === '00' ? '' : '.' + cents.padEnd(2, '0').slice(0, 2));
    return String(value ?? '').trim().startsWith('-') ? '-' + text : text;
};
