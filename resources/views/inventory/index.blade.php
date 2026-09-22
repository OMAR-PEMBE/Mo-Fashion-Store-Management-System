<x-layouts.app title="Inventory">
    <h1 class="text-3xl font-bold">Inventory</h1><p class="mb-7 mt-3 text-sm text-text-secondary">Available stock is physical stock minus reserved stock.</p>
    <form method="GET" class="mb-6 flex flex-wrap items-end gap-4 rounded-xl border border-border bg-surface p-5">
        <div class="min-w-0 flex-1"><x-input name="q" label="Search" :value="$filters['q'] ?? ''" placeholder="Product name or SKU" maxlength="191" /></div>
        <label class="flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" name="low_stock" value="1" @checked($filters['low_stock'] ?? false)> Low stock only</label>
        <x-button type="submit" variant="secondary">Filter</x-button><a href="{{ route('inventory.index') }}" class="py-3 text-sm underline">Clear</a>
    </form>
    <div class="overflow-hidden rounded-xl border border-border bg-surface">
        @if($variants->isEmpty())<div class="p-10 text-center"><h2 class="font-semibold">No inventory items found</h2><p class="mt-2 text-sm text-text-secondary">Add product variants or change your filters.</p></div>
        @else<div class="relative overflow-x-auto"><table class="w-full min-w-[700px] text-left text-sm"><caption class="sr-only">Stock by product variant</caption><thead class="border-b border-border bg-background"><tr>@foreach(['Product / SKU', 'Size / Colour', 'Physical', 'Reserved', 'Available', 'Stock level'] as $heading)<th scope="col" class="px-5 py-4 font-medium">{{ $heading }}</th>@endforeach @can('inventory.adjust')<th scope="col" class="px-5 py-4 font-medium">History</th>@endcan</tr></thead>
        <tbody class="divide-y divide-border">@foreach($variants as $variant)<tr>
            <th scope="row" class="min-w-40 max-w-xs break-words px-5 py-4 font-medium">{{ $variant->product->name }}<span class="mt-1 block break-all text-xs text-text-secondary">{{ $variant->sku }}</span>@if($variant->trashed() || $variant->product->trashed())<span class="block text-xs text-warning">Archived</span>@elseif(!$variant->is_active || !$variant->product->is_active)<span class="block text-xs text-warning">Inactive</span>@endif</th>
            <td class="px-5 py-4">{{ $variant->size?->name ?? 'One size' }} / {{ $variant->colour?->name ?? 'No colour' }}</td>
            @if($variant->inventory)
                <td class="px-5 py-4">{{ $variant->inventory->physical_quantity }}</td><td class="px-5 py-4">{{ $variant->inventory->reserved_quantity }}</td><td class="px-5 py-4 font-semibold">{{ $variant->inventory->available_quantity }}</td>
                <td class="px-5 py-4"><x-badge :tone="$variant->inventory->available_quantity <= $variant->low_stock_threshold ? 'warning' : 'success'">{{ $variant->inventory->available_quantity === 0 ? 'Out of stock' : ($variant->inventory->available_quantity <= $variant->low_stock_threshold ? 'Low stock' : 'In stock') }}</x-badge></td>
            @else<td colspan="4" class="px-5 py-4 text-danger">Balance unavailable. Contact your administrator.</td>@endif
            @can('inventory.adjust')<td class="px-5 py-4"><a href="{{ route('inventory.movements', $variant) }}" class="inline-flex min-h-11 items-center underline" aria-label="Movements for {{ $variant->sku }}">Movements</a></td>@endcan
        </tr>@endforeach</tbody></table></div><div class="border-t border-border p-5">{{ $variants->links() }}</div>@endif
    </div>
</x-layouts.app>
