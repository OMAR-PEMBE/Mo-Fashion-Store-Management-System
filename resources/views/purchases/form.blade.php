@php
    $editing = $purchase->exists;
    $payment = old('payment_status', $purchase->payment_status);
    $chip = 'cursor-pointer rounded-lg border border-border bg-surface px-3 py-2 text-sm font-semibold hover:bg-background has-[:checked]:border-text-primary has-[:checked]:bg-text-primary has-[:checked]:text-white has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-info';
@endphp
<x-layouts.app :title="$editing ? 'Edit purchase draft' : 'New purchase'">
    <x-page-header :title="$editing ? 'Edit draft '.$purchase->purchase_number : 'New purchase'" :back="$editing ? route('purchases.show', $purchase) : route('purchases.index')" :back-label="$editing ? $purchase->purchase_number : 'Purchases'"
        description="Save it as a draft first. Stock is only added when you receive it on the next page." />

    <form method="POST" action="{{ $editing ? route('purchases.update', $purchase) : route('purchases.store') }}" class="space-y-6" data-busy
        x-data="{ ...purchaseForm(@js($selectedSupplier), @js($lines), @js(route('purchases.lookup'))), payment: @js((string) $payment),
            cents(v) { const n = Number(String(v ?? '').trim()); return Number.isFinite(n) && n >= 0 ? Math.round(n * 100) : 0 },
            lineCents(line) { return (parseInt(line.quantity, 10) || 0) * this.cents(line.unit_cost) },
            get totalCents() { return this.lines.reduce((sum, line) => sum + this.lineCents(line), 0) },
            get units() { return this.lines.reduce((sum, line) => sum + (parseInt(line.quantity, 10) || 0), 0) },
            get blocker() { return ! this.supplier.id ? 'Choose the supplier.' : (! this.payment ? 'Say whether the supplier has been paid.' : (! this.lines.length ? 'Add at least one item.' : (this.lines.some(line => ! line.product_variant_id) ? 'Choose a product for every item, or remove empty ones.' : ''))) } }">
        @csrf @if($editing) @method('PUT')<input type="hidden" name="revision" value="{{ old('revision', $purchase->revision) }}"> @endif
        @if($errors->any())<div role="alert" class="rounded-xl border border-danger/30 bg-danger/5 p-4 text-sm text-danger"><p class="font-semibold">Please fix these:</p><ul class="mt-1 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <noscript><p role="alert" class="rounded-lg border border-warning bg-warning/10 p-4 text-sm">Turn on JavaScript to search suppliers and products.</p></noscript>

        <section class="space-y-5 rounded-2xl border border-border bg-surface p-5 sm:p-6" aria-labelledby="supplier-heading">
            <h2 id="supplier-heading" class="text-base font-semibold">Supplier and invoice</h2>
            <div class="grid gap-5 md:grid-cols-2">
                <div class="min-w-0">
                    <label for="supplier-search" class="mb-2 block text-sm font-medium">Supplier</label>
                    <input type="hidden" name="supplier_id" :value="supplier.id">
                    <div x-show="supplier.id" class="flex min-h-11 items-center justify-between gap-3 rounded-lg border border-border bg-background px-3 text-sm">
                        <span class="font-semibold break-words" x-text="supplier.label"></span>
                        <button type="button" @click="supplier.id = ''; supplier.label = ''; $nextTick(() => $refs.supplierSearch.focus())" class="text-xs font-semibold underline underline-offset-4">Change</button>
                    </div>
                    <div x-show="! supplier.id">
                        <input id="supplier-search" x-ref="supplierSearch" x-model="supplier.query" @input.debounce.300ms="search(supplier, 'supplier')" @keydown.enter.prevent="search(supplier, 'supplier')" autocomplete="off" maxlength="191" placeholder="Type a supplier name or code" class="min-h-11 w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm">
                        <p class="mt-1 text-xs text-danger" role="status" x-text="supplier.error"></p><p class="mt-1 text-xs text-text-secondary" x-show="supplier.searched && supplier.searched === supplier.query.trim() && ! supplier.results.length && ! supplier.error" x-text="'No active supplier matches “' + supplier.searched + '”.'"></p>
                        <ul class="mt-1 max-h-48 overflow-y-auto rounded-lg border border-border" x-show="supplier.results.length"><template x-for="result in supplier.results" :key="result.id"><li><button type="button" class="w-full px-3 py-2.5 text-left text-sm hover:bg-selected" @click="choose(supplier, result, 'id')" x-text="result.label"></button></li></template></ul>
                        @if(Route::has('suppliers.create'))@can('suppliers.manage')<a href="{{ route('suppliers.create') }}" target="_blank" rel="noopener" class="mt-1 inline-block text-xs font-semibold underline underline-offset-4">New supplier? Add them (opens a new tab)</a>@endcan @endif
                    </div>
                </div>
                <x-input name="purchase_date" label="Date bought" type="date" :value="old('purchase_date', $purchase->purchase_date?->format('Y-m-d') ?? now()->format('Y-m-d'))" required />
                <x-input name="supplier_invoice_number" label="Supplier's invoice number (optional)" :value="old('supplier_invoice_number', $purchase->supplier_invoice_number)" maxlength="150" />
                <fieldset>
                    <legend class="mb-2 text-sm font-medium">Supplier paid? <span class="font-normal text-text-secondary">(for your records)</span></legend>
                    <div class="flex flex-wrap gap-2">
                        @foreach(['PAID' => 'Paid', 'PARTIALLY_PAID' => 'Partly paid', 'UNPAID' => 'Not yet'] as $value => $label)
                            <label class="{{ $chip }}"><input type="radio" name="payment_status" value="{{ $value }}" x-model="payment" class="sr-only" @checked($payment === $value)>{{ $label }}</label>
                        @endforeach
                    </div>
                    @error('payment_status')<p class="mt-2 text-sm text-danger">{{ $message }}</p>@enderror
                </fieldset>
            </div>
            <div><label for="notes" class="mb-2 block text-sm font-medium">Notes (optional)</label><textarea id="notes" name="notes" rows="2" maxlength="5000" class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm">{{ old('notes', $purchase->notes) }}</textarea></div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-border bg-surface" aria-labelledby="items-heading">
            <div class="border-b border-border px-5 py-4"><h2 id="items-heading" class="text-base font-semibold">What was bought</h2><p class="text-sm text-text-secondary">Find each size and colour, then enter how many and what each one cost.</p></div>
            <ol class="divide-y divide-border">
                <template x-for="(line, index) in lines" :key="line.key">
                    <li class="grid grid-cols-2 gap-4 px-5 py-4 md:grid-cols-[minmax(0,1fr)_110px_160px_130px_auto] md:items-start">
                        <div class="col-span-2 min-w-0 md:col-span-1">
                            <label :for="'variant-' + line.key" class="mb-1 block text-xs font-medium" x-text="'Item ' + (index + 1)"></label>
                            <input type="hidden" :name="`items[${index}][product_variant_id]`" :value="line.product_variant_id">
                            <div x-show="line.product_variant_id" class="flex min-h-10 items-center justify-between gap-3 rounded-lg border border-border bg-background px-3 text-sm">
                                <span class="font-semibold break-words" x-text="line.label"></span>
                                <button type="button" @click="line.product_variant_id = ''; line.label = ''" class="shrink-0 text-xs font-semibold underline underline-offset-4">Change</button>
                            </div>
                            <div x-show="! line.product_variant_id">
                                <input :id="'variant-' + line.key" x-model="line.query" @input.debounce.300ms="search(line, 'variant')" @keydown.enter.prevent="search(line, 'variant')" autocomplete="off" maxlength="191" placeholder="Type a product name or code" class="min-h-10 w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm">
                                <p class="mt-1 text-xs text-danger" role="status" x-text="line.error"></p><p class="mt-1 text-xs text-text-secondary" x-show="line.searched && line.searched === line.query.trim() && ! line.results.length && ! line.error" x-text="'Nothing on sale matches “' + line.searched + '”.'"></p>
                                <ul class="mt-1 max-h-48 overflow-y-auto rounded-lg border border-border" x-show="line.results.length"><template x-for="result in line.results" :key="result.id"><li><button type="button" class="w-full px-3 py-2.5 text-left text-sm hover:bg-selected" @click="choose(line, result, 'product_variant_id')" x-text="result.label"></button></li></template></ul>
                            </div>
                        </div>
                        <div><label :for="'quantity-' + line.key" class="mb-1 block text-xs font-medium">How many</label><input :id="'quantity-' + line.key" :name="`items[${index}][quantity]`" x-model="line.quantity" type="number" min="1" max="2147483647" step="1" required @keydown.enter.prevent class="min-h-10 w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm tabular-nums"></div>
                        <div><label :for="'cost-' + line.key" class="mb-1 block text-xs font-medium">Cost each (TZS)</label><input :id="'cost-' + line.key" :name="`items[${index}][unit_cost]`" x-model="line.unit_cost" inputmode="decimal" required maxlength="16" @keydown.enter.prevent class="min-h-10 w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm tabular-nums"></div>
                        <div class="md:text-right"><p class="mb-1 text-xs font-medium text-text-secondary">Line total</p><p class="flex min-h-10 items-center font-semibold tabular-nums md:justify-end" x-text="formatMoney((lineCents(line) / 100).toFixed(2))"></p></div>
                        <div class="self-end justify-self-end md:self-auto md:pt-5"><button type="button" class="flex size-10 items-center justify-center rounded-lg text-text-secondary hover:bg-background hover:text-danger" @click="lines.splice(index, 1)" :aria-label="'Remove item ' + (index + 1)"><svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg></button></div>
                    </li>
                </template>
            </ol>
            <div class="flex flex-wrap items-center justify-between gap-4 border-t border-border bg-background/60 px-5 py-4">
                <x-button type="button" variant="outline" @click="add(); $nextTick(() => document.getElementById('variant-' + lines[lines.length - 1].key)?.focus())" x-bind:disabled="lines.length >= 100">+ Add another item</x-button>
                <p class="text-sm"><span class="text-text-secondary" x-text="units + (units === 1 ? ' unit' : ' units') + ' · total cost'"></span> <span class="ml-2 text-xl font-bold tabular-nums" x-text="formatMoney((totalCents / 100).toFixed(2))"></span></p>
            </div>
        </section>

        <div class="flex flex-wrap items-center gap-4">
            <button type="submit" :disabled="blocker !== ''" data-busy-label="Saving…" class="inline-flex min-h-12 items-center rounded-xl bg-primary px-6 text-base font-bold text-text-primary hover:bg-primary-hover disabled:cursor-not-allowed disabled:opacity-50">Save draft</button>
            <p class="text-sm text-text-secondary" x-text="blocker || 'Next you check it and receive the stock.'"></p>
        </div>
    </form>
</x-layouts.app>
