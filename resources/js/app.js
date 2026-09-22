// Livewire initializes its bundled Alpine instance through @livewireScripts.
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
