@php
    $canHistory = auth()->user()->can('inventory.adjust');
    $level = function ($variant) {
        $available = $variant->inventory?->available_quantity;
        return match (true) {
            $available === null => ['Balance missing', 'danger'],
            $available <= 0 => ['Out of stock', 'danger'],
            $available <= $variant->low_stock_threshold => ['Running low', 'warning'],
            default => ['In stock', 'success'],
        };
    };
@endphp
<x-layouts.app title="Inventory">
    <x-page-header title="Inventory" description="Ready to sell = in the shop minus what is held for confirmed orders. Items run low when they reach their restock level." />

    <form method="GET" class="mb-4" role="search">
        @if($filters['stock'] ?? null)<input type="hidden" name="stock" value="{{ $filters['stock'] }}">@endif
        <label for="inventory-search" class="sr-only">Search stock by product name or code</label>
        <div class="relative max-w-md">
            <svg class="pointer-events-none absolute top-1/2 left-3.5 size-5 -translate-y-1/2 text-text-secondary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <input id="inventory-search" name="q" type="search" value="{{ $filters['q'] ?? '' }}" maxlength="191" placeholder="Product name or code" class="min-h-11 w-full rounded-lg border border-border bg-surface py-2 pr-3 pl-11 text-sm">
        </div>
    </form>
    <x-filter-tabs :options="['' => 'All items', 'low' => 'Needs restock', 'out' => 'Out of stock']" :counts="$counts" param="stock" label="Filter by stock level" class="mb-5" />

    <div class="overflow-hidden rounded-2xl border border-border bg-surface">
        @if($variants->isEmpty())
            <div class="p-10 text-center">
                <p class="font-semibold">{{ ($filters['stock'] ?? null) === 'low' ? 'Nothing needs restocking.' : (($filters['stock'] ?? null) === 'out' ? 'Nothing is out of stock.' : 'No stock items found.') }}</p>
                <p class="mt-1 text-sm text-text-secondary">{{ ($filters['q'] ?? null) ? 'Try another name or code.' : 'Stock appears here once products have variants.' }}</p>
            </div>
        @else
            <ul class="divide-y divide-border sm:hidden">
                @foreach($variants as $variant)
                    @php [$label, $tone] = $level($variant); @endphp
                    <li class="relative px-4 py-4 hover:bg-selected/50">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                @if($canHistory)<a href="{{ route('inventory.movements', $variant) }}" class="row-link break-words">{{ $variant->product->name }}</a>@else<p class="font-semibold break-words">{{ $variant->product->name }}</p>@endif
                                <p class="text-xs text-text-secondary">{{ collect([$variant->size?->name, $variant->colour?->name, $variant->sku])->filter()->join(' · ') }}</p>
                                @if($variant->inventory)<p class="text-xs text-text-secondary">{{ $variant->inventory->physical_quantity }} in the shop · {{ $variant->inventory->reserved_quantity }} held</p>@endif
                            </div>
                            <div class="shrink-0 text-right"><p class="text-lg font-bold tabular-nums">{{ $variant->inventory?->available_quantity ?? '?' }}</p><x-badge :tone="$tone">{{ $label }}</x-badge></div>
                        </div>
                    </li>
                @endforeach
            </ul>
            <div class="hidden overflow-x-auto sm:block">
                <table class="data-table">
                    <caption class="sr-only">Stock by item</caption>
                    <thead><tr><th scope="col">Item</th><th scope="col" class="num">Ready to sell</th><th scope="col" class="num">In the shop</th><th scope="col" class="num">Held for orders</th><th scope="col" class="num">Restock at</th><th scope="col">Level</th></tr></thead>
                    <tbody>
                        @foreach($variants as $variant)
                            @php [$label, $tone] = $level($variant); @endphp
                            <tr>
                                <th scope="row" class="font-normal">
                                    @if($canHistory)<a href="{{ route('inventory.movements', $variant) }}" class="row-link break-words" aria-label="Stock history for {{ $variant->product->name }} {{ $variant->sku }}">{{ $variant->product->name }}</a>@else<span class="font-semibold break-words">{{ $variant->product->name }}</span>@endif
                                    <span class="block text-xs text-text-secondary">{{ collect([$variant->size?->name, $variant->colour?->name, $variant->sku])->filter()->join(' · ') }}@if($variant->trashed() || $variant->product->trashed()) · Archived @elseif(! $variant->is_active || ! $variant->product->is_active) · Inactive @endif</span>
                                </th>
                                @if($variant->inventory)
                                    <td class="num text-base font-bold">{{ number_format($variant->inventory->available_quantity) }}</td>
                                    <td class="num">{{ number_format($variant->inventory->physical_quantity) }}</td>
                                    <td class="num">{{ number_format($variant->inventory->reserved_quantity) }}</td>
                                @else
                                    <td colspan="3" class="text-danger">Balance missing. Tell an administrator.</td>
                                @endif
                                <td class="num text-text-secondary">{{ $variant->low_stock_threshold }}</td>
                                <td><x-badge :tone="$tone">{{ $label }}</x-badge></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        @if($variants->hasPages())<div class="border-t border-border p-4">{{ $variants->links() }}</div>@endif
    </div>
    @if($canHistory)<p class="mt-3 text-xs text-text-secondary">Open an item to see every stock change and what caused it.</p>@endif
</x-layouts.app>
