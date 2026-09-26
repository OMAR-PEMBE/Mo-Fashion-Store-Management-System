@php
    $tabs = ['' => 'All', 'DRAFT' => 'Drafts', 'CONFIRMED' => 'Received', 'CANCELLED' => 'Cancelled'];
    $statusBadge = fn ($status) => match ($status->value) { 'DRAFT' => ['Draft, not received', 'warning'], 'CONFIRMED' => ['Received', 'success'], default => ['Cancelled', 'neutral'] };
    $advanced = ($filters['payment_status'] ?? null) || ($filters['date_from'] ?? null) || ($filters['date_to'] ?? null);
    $filtered = $advanced || ($filters['q'] ?? null) || ($filters['status'] ?? null) || ($filters['supplier_id'] ?? null);
@endphp
<x-layouts.app title="Purchases">
    <x-page-header title="Purchases" description="Stock bought from suppliers. A purchase adds stock and updates average costs only when it is received.">
        <x-slot:actions><x-action-link :href="route('purchases.create')">New purchase</x-action-link></x-slot:actions>
    </x-page-header>

    <form method="GET" class="mb-4 space-y-3" role="search">
        @foreach(['status', 'supplier_id'] as $keep)@if($filters[$keep] ?? null)<input type="hidden" name="{{ $keep }}" value="{{ $filters[$keep] }}">@endif @endforeach
        <div class="relative max-w-md">
            <label for="purchase-search" class="sr-only">Search purchases</label>
            <svg class="pointer-events-none absolute top-1/2 left-3.5 size-5 -translate-y-1/2 text-text-secondary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <input id="purchase-search" name="q" type="search" value="{{ $filters['q'] ?? '' }}" maxlength="191" placeholder="Purchase number, invoice or supplier" class="min-h-11 w-full rounded-lg border border-border bg-surface py-2 pr-3 pl-11 text-sm">
        </div>
        <details class="rounded-2xl border border-border bg-surface" @if($advanced) open @endif>
            <summary class="cursor-pointer px-5 py-3 text-sm font-semibold">Payment and date filters</summary>
            <div class="grid gap-4 border-t border-border p-5 sm:grid-cols-2 lg:grid-cols-4">
                <x-select name="payment_status" label="Supplier paid?"><option value="">Any</option>@foreach(['PAID' => 'Paid', 'PARTIALLY_PAID' => 'Partly paid', 'UNPAID' => 'Unpaid'] as $value => $label)<option value="{{ $value }}" @selected(($filters['payment_status'] ?? '') === $value)>{{ $label }}</option>@endforeach</x-select>
                <x-input name="date_from" label="From" type="date" :value="$filters['date_from'] ?? ''" />
                <x-input name="date_to" label="To" type="date" :value="$filters['date_to'] ?? ''" />
                <div class="flex items-end gap-4"><x-button type="submit" variant="secondary">Apply</x-button>@if($filtered)<a href="{{ route('purchases.index') }}" class="py-3 text-sm font-semibold underline underline-offset-4">Clear all</a>@endif</div>
            </div>
        </details>
    </form>
    <x-filter-tabs :options="$tabs" :counts="$counts" class="mb-5" />

    <div class="overflow-hidden rounded-2xl border border-border bg-surface">
        @if($purchases->isEmpty())
            <div class="p-10 text-center">
                <p class="font-semibold">{{ $filtered ? 'No purchases match these filters.' : 'No purchases yet.' }}</p>
                <p class="mt-1 text-sm text-text-secondary">{{ $filtered ? 'Try a wider date range or clear the filters.' : 'Record stock bought from a supplier to add it to inventory.' }}</p>
            </div>
        @else
            <ul class="divide-y divide-border sm:hidden">
                @foreach($purchases as $purchase)
                    @php [$label, $tone] = $statusBadge($purchase->status); @endphp
                    <li class="relative px-4 py-4 hover:bg-selected/50">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <a href="{{ route('purchases.show', $purchase) }}" class="row-link break-words">{{ $purchase->supplier->name }}</a>
                                <p class="mt-0.5 text-xs text-text-secondary">{{ $purchase->purchase_number }} · {{ $purchase->purchase_date->format('j M Y') }}</p>
                                <p class="text-xs text-text-secondary">{{ (int) $purchase->items_sum_quantity }} units · supplier {{ strtolower(\App\Support\Status::label($purchase->payment_status)) }}</p>
                            </div>
                            <div class="shrink-0 text-right"><p class="font-semibold tabular-nums">@money($purchase->total_amount)</p><x-badge :tone="$tone" class="mt-1">{{ $label }}</x-badge></div>
                        </div>
                    </li>
                @endforeach
            </ul>
            <div class="hidden overflow-x-auto sm:block">
                <table class="data-table">
                    <caption class="sr-only">Purchases</caption>
                    <thead><tr><th scope="col">Purchase</th><th scope="col">Supplier</th><th scope="col" class="num">Units</th><th scope="col">Stock</th><th scope="col">Supplier paid</th><th scope="col" class="num">Total (TZS)</th></tr></thead>
                    <tbody>
                        @foreach($purchases as $purchase)
                            @php [$label, $tone] = $statusBadge($purchase->status); @endphp
                            <tr>
                                <th scope="row" class="font-normal"><a href="{{ route('purchases.show', $purchase) }}" class="row-link">{{ $purchase->purchase_number }}</a><span class="block text-xs text-text-secondary">{{ $purchase->purchase_date->format('j M Y') }}@if($purchase->supplier_invoice_number) · invoice {{ $purchase->supplier_invoice_number }}@endif</span></th>
                                <td class="break-words">{{ $purchase->supplier->name }}</td>
                                <td class="num">{{ number_format((int) $purchase->items_sum_quantity) }}</td>
                                <td><x-badge :tone="$tone">{{ $label }}</x-badge></td>
                                <td><x-status :value="$purchase->payment_status" /></td>
                                <td class="num font-semibold">{{ \App\Support\Money::format($purchase->total_amount, false) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        @if($purchases->hasPages())<div class="border-t border-border p-4">{{ $purchases->links() }}</div>@endif
    </div>
</x-layouts.app>
