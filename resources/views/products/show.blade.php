<x-layouts.app :title="$product->name">
    <a href="{{ route('products.index') }}" class="mb-5 inline-block text-sm underline underline-offset-4">Back to products</a>
    <div class="mb-7 flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0"><p class="mb-2 break-all text-sm text-text-secondary">{{ $product->product_code }}</p><h1 class="break-words text-3xl font-bold">{{ $product->name }}</h1><p class="mt-3 text-sm text-text-secondary">{{ $product->category->name }}</p></div>
        @can('update', $product)
            @if($product->trashed())<form method="POST" action="{{ route('products.restore', $product) }}">@csrf<x-button type="submit">Restore product</x-button></form>
            @else<x-action-link :href="route('products.edit', $product)" :secondary="true">Edit product</x-action-link>@endif
        @endcan
    </div>
    
    @error('status')<p role="alert" class="mb-5 text-sm text-danger">{{ $message }}</p>@enderror
    <x-card class="mb-8">
        <div class="flex flex-wrap items-center gap-4"><x-badge :tone="$product->is_active && !$product->trashed() ? 'success' : 'warning'">{{ $product->trashed() ? 'Archived' : ($product->is_active ? 'Active' : 'Inactive') }}</x-badge><span class="text-sm">Default price: {{ $product->default_selling_price !== null ? \App\Support\Money::format($product->default_selling_price) : 'Not set' }}</span></div>
        @if($product->description)<p class="mt-5 whitespace-pre-line break-words text-sm text-text-secondary">{{ $product->description }}</p>@endif
        @if(!$product->category->is_active || $product->category->trashed())<p class="mt-4 text-sm text-warning">This category is inactive. Choose an active category before activating this product or adding variants.</p>@endif
    </x-card>
    <div class="mb-5 flex flex-wrap items-center justify-between gap-4"><h2 class="text-xl font-bold">Variants</h2>@can('update', $product)@if(!$product->trashed() && $product->is_active && $product->category->is_active && !$product->category->trashed())<x-action-link :href="route('variants.create', $product)">Add variant</x-action-link>@endif@endcan</div>
    @can('update', $product)
        <form method="GET" class="mb-5 flex flex-wrap items-end gap-3"><x-select name="variant_status" label="Variant status"><option value="">Active and inactive</option>@foreach(['active' => 'Active', 'inactive' => 'Inactive', 'archived' => 'Archived'] as $value => $label)<option value="{{ $value }}" @selected(($filters['variant_status'] ?? '') === $value)>{{ $label }}</option>@endforeach</x-select><x-button type="submit" variant="secondary">Filter</x-button></form>
    @endcan
    <div class="overflow-hidden rounded-xl border border-border bg-surface">
        @if($variants->isEmpty())<div class="px-6 py-12 text-center"><h3 class="font-semibold">No variants found</h3><p class="mt-2 text-sm text-text-secondary">Each variant identifies one size and colour combination.</p></div>
        @else
        <div class="relative overflow-x-auto"><table class="w-full min-w-[700px] text-left text-sm">
            <caption class="sr-only">Variants for {{ $product->name }}</caption>
            <thead class="border-b border-border bg-background"><tr>@foreach(['SKU', 'Size / Colour', 'Selling price', 'Low stock', 'Status'] as $label)<th scope="col" class="px-5 py-4 font-medium">{{ $label }}</th>@endforeach @can('products.view_cost')<th scope="col" class="px-5 py-4 font-medium">Average cost</th>@endcan @can('update', $product)<th scope="col" class="px-5 py-4"><span class="sr-only">Actions</span></th>@endcan</tr></thead>
            <tbody class="divide-y divide-border">@foreach($variants as $variant)<tr>
                <th scope="row" class="min-w-40 max-w-xs break-all px-5 py-4 font-medium">{{ $variant->sku }}</th>
                <td class="px-5 py-4">{{ $variant->size?->name ?? 'One size' }} / {{ $variant->colour?->name ?? 'No colour' }}@if(($variant->size && !$variant->size->is_active) || ($variant->colour && !$variant->colour->is_active))<span class="mt-1 block text-xs text-warning">Inactive reference</span>@endif</td>
                <td class="whitespace-nowrap px-5 py-4">@money($variant->selling_price)</td>
                <td class="px-5 py-4">{{ $variant->low_stock_threshold }}</td>
                <td class="px-5 py-4"><x-badge :tone="$variant->is_active && !$variant->trashed() ? 'success' : 'warning'">{{ $variant->trashed() ? 'Archived' : ($variant->is_active ? 'Active' : 'Inactive') }}</x-badge></td>
                @can('products.view_cost')<td class="whitespace-nowrap px-5 py-4">@money($variant->weighted_average_cost)</td>@endcan
                @can('update', $product)<td class="px-5 py-4">
                    @if(!$product->trashed())
                        @if($variant->trashed())<form method="POST" action="{{ route('variants.restore', [$product, $variant->id]) }}">@csrf<x-button type="submit" variant="secondary">Restore</x-button></form>
                        @else<div class="flex items-center gap-4"><a href="{{ route('variants.edit', [$product, $variant->id]) }}" class="inline-flex min-h-11 items-center underline" aria-label="Edit {{ $variant->sku }}">Edit</a><button type="button" x-data @click="$dispatch('open-modal', 'archive-variant-{{ $variant->id }}')" class="min-h-11 text-danger underline" aria-label="Archive {{ $variant->sku }}">Archive</button></div>@endif
                    @endif
                </td>@endcan
            </tr>@endforeach</tbody>
        </table></div><div class="border-t border-border p-5">{{ $variants->links() }}</div>
        @endif
    </div>
    @can('update', $product)
        @if(!$product->trashed())
            <div class="mt-8"><x-button variant="danger" x-data @click="$dispatch('open-modal', 'archive-product')">Archive product</x-button></div>
            <x-modal name="archive-product" title="Archive this product?"><p class="mb-5 text-sm">{{ $product->name }} will be hidden from the active catalogue. Its variants are preserved. You can restore it from the Archived filter.</p><form method="POST" action="{{ route('products.destroy', $product) }}">@csrf @method('DELETE')<x-button type="submit" variant="danger">Confirm archive</x-button></form></x-modal>
            @foreach($variants as $variant)@if(!$variant->trashed())<x-modal :name="'archive-variant-'.$variant->id" title="Archive this variant?"><p class="mb-5 break-words text-sm">{{ $variant->sku }} will be hidden from the active catalogue. Its records are preserved.</p><form method="POST" action="{{ route('variants.destroy', [$product, $variant->id]) }}">@csrf @method('DELETE')<x-button type="submit" variant="danger">Confirm archive</x-button></form></x-modal>@endif@endforeach
        @endif
    @endcan
</x-layouts.app>
