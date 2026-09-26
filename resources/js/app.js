// Livewire initializes its bundled Alpine instance through @livewireScripts.
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
        else if(this.lines.length < 100) this.lines.push({product_variant_id:result.id,label:result.label,quantity:'1',unit_price:result.unit_price,discount_amount:'0.00'});
    },
});
window.purchaseForm = (supplier, lines, lookupUrl) => ({
    supplier: { ...supplier, query: '', results: [], error: '', request: 0 },
    lines: lines.map(line => ({ ...line, key: crypto.randomUUID(), query: '', results: [], error: '', request: 0 })),
    init() { if (!this.lines.length) this.add(); },
    add() {
        if (this.lines.length >= 100) return;
        this.lines.push({ key: crypto.randomUUID(), product_variant_id: '', label: '', quantity: '1', unit_cost: '', query: '', results: [], error: '', request: 0 });
    },
    async search(target, kind) {
        const request = ++target.request;
        target.error = '';
        try {
            const response = await fetch(lookupUrl + '?' + new URLSearchParams({ kind, q: target.query }), { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error();
            const results = await response.json();
            if (request === target.request) target.results = results;
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
