<x-layouts.app :title="$purchase->exists ? 'Edit purchase draft' : 'New purchase'">
    <a href="{{ $purchase->exists ? route('purchases.show', $purchase) : route('purchases.index') }}" class="mb-5 inline-block text-sm underline">Back to purchases</a>
    <h1 class="text-3xl font-bold">{{ $purchase->exists ? 'Edit purchase draft' : 'New purchase' }}</h1>
    <p class="mb-7 mt-3 text-sm text-text-secondary">Save a draft, review the quantities and costs, then confirm to receive stock.</p>
    <form method="POST" action="{{ $purchase->exists ? route('purchases.update', $purchase) : route('purchases.store') }}" x-data="purchaseForm(@js($selectedSupplier), @js($lines), @js(route('purchases.lookup')))" class="space-y-6">
        @csrf @if($purchase->exists) @method('PUT')<input type="hidden" name="revision" value="{{ old('revision', $purchase->revision) }}"> @endif
        @if($errors->any())<div role="alert" class="rounded-xl border border-danger bg-surface p-5 text-sm text-danger"><p class="font-semibold">Please correct these fields:</p><ul class="mt-2 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <noscript><p role="alert">Enable JavaScript to search suppliers and add purchase items.</p></noscript>
        <x-card title="Purchase details">
            <div class="grid gap-6 md:grid-cols-2">
                <div class="min-w-0"><label for="supplier-search" class="mb-2 block text-sm font-medium">Find supplier</label><div class="flex gap-2"><input id="supplier-search" x-model="supplier.query" @keydown.enter.prevent="search(supplier, 'supplier')" class="min-w-0 w-full rounded-lg border border-border px-3 py-2 text-sm" placeholder="Name or code" maxlength="191"><x-button type="button" variant="secondary" @click="search(supplier, 'supplier')">Search</x-button></div>
                    <input type="hidden" name="supplier_id" :value="supplier.id">
                    <p class="mt-2 break-words text-sm" x-text="supplier.label || 'No supplier selected'"></p>
                    <p class="mt-2 text-sm text-danger" role="status" x-text="supplier.error"></p>
                    <ul class="mt-2 max-h-48 overflow-y-auto"><template x-for="result in supplier.results" :key="result.id"><li><button type="button" class="w-full border-b border-border px-2 py-3 text-left text-sm hover:bg-selected" @click="choose(supplier, result, 'id')" x-text="result.label"></button></li></template></ul>
                </div>
                <x-input name="purchase_date" label="Purchase date" type="date" :value="old('purchase_date', $purchase->purchase_date?->format('Y-m-d') ?? now()->format('Y-m-d'))" required />
                <x-input name="supplier_invoice_number" label="Supplier invoice / reference (optional)" :value="old('supplier_invoice_number', $purchase->supplier_invoice_number)" maxlength="150" />
                <x-select name="payment_status" label="Payment status (informational)"><option value="">Choose a status</option>@foreach(['PAID' => 'Paid', 'PARTIALLY_PAID' => 'Partially paid', 'UNPAID' => 'Unpaid'] as $value => $label)<option value="{{ $value }}" @selected(old('payment_status', $purchase->payment_status) === $value)>{{ $label }}</option>@endforeach</x-select>
            </div>
            <div class="mt-6"><label for="notes" class="mb-2 block text-sm font-medium">Notes (optional)</label><textarea id="notes" name="notes" rows="3" maxlength="5000" class="w-full rounded-lg border border-border px-3 py-2 text-sm">{{ old('notes', $purchase->notes) }}</textarea></div>
        </x-card>
        <x-card title="Items">
            <p class="mb-5 text-sm text-text-secondary">Search for each variant and choose a result. Costs are in TZS. Totals appear on the review page.</p>
            <div class="space-y-5"><template x-for="(line, index) in lines" :key="line.key"><fieldset class="min-w-0 rounded-xl border border-border p-4">
                <legend class="px-2 text-sm font-semibold" x-text="'Item ' + (index + 1)"></legend>
                <div class="grid gap-4 md:grid-cols-2"><div class="min-w-0"><label :for="'variant-' + line.key" class="mb-2 block text-sm font-medium">Find product / SKU</label><div class="flex gap-2"><input :id="'variant-' + line.key" x-model="line.query" @keydown.enter.prevent="search(line, 'variant')" maxlength="191" class="min-w-0 w-full rounded-lg border border-border px-3 py-2 text-sm"><x-button type="button" variant="secondary" @click="search(line, 'variant')">Search</x-button></div>
                    <input type="hidden" :name="`items[${index}][product_variant_id]`" :value="line.product_variant_id">
                    <p class="mt-2 break-words text-sm" x-text="line.label || 'No variant selected'"></p><p class="mt-2 text-sm text-danger" role="status" x-text="line.error"></p>
                    <ul class="mt-2 max-h-48 overflow-y-auto"><template x-for="result in line.results" :key="result.id"><li><button type="button" class="w-full border-b border-border px-2 py-3 text-left text-sm hover:bg-selected" @click="choose(line, result, 'product_variant_id')" x-text="result.label"></button></li></template></ul>
                </div><div class="grid grid-cols-2 gap-4"><div><label :for="'quantity-' + line.key" class="mb-2 block text-sm font-medium">Quantity</label><input :id="'quantity-' + line.key" :name="`items[${index}][quantity]`" x-model="line.quantity" type="number" min="1" max="2147483647" step="1" required class="w-full min-w-0 rounded-lg border border-border px-3 py-2 text-sm"></div><div><label :for="'cost-' + line.key" class="mb-2 block text-sm font-medium">Unit cost (TZS)</label><input :id="'cost-' + line.key" :name="`items[${index}][unit_cost]`" x-model="line.unit_cost" inputmode="decimal" required maxlength="16" class="w-full min-w-0 rounded-lg border border-border px-3 py-2 text-sm"></div></div></div>
                <button type="button" class="mt-3 min-h-11 text-sm underline" @click="lines.splice(index, 1)">Remove item</button>
            </fieldset></template></div>
            <x-button type="button" variant="secondary" class="mt-5" @click="add()" x-bind:disabled="lines.length >= 100">Add item</x-button>
        </x-card>
        <div class="flex flex-wrap gap-5"><x-button type="submit">Save draft and review</x-button><a href="{{ route('purchases.index') }}" class="py-3 text-sm underline">Back to list</a></div>
    </form>
</x-layouts.app>
