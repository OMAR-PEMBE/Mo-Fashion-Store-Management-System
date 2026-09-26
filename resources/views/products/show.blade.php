@php
    $canUpdate = auth()->user()->can('update', $product);
    $canCost = auth()->user()->can('products.view_cost');
    $categoryUsable = $product->category->is_active && ! $product->category->trashed();
    $canAddVariant = $canUpdate && ! $product->trashed() && $product->is_active && $categoryUsable;
@endphp
<x-layouts.app :title="$product->name">
    <x-page-header :title="$product->name" :back="route('products.index')" back-label="Products"
        :description="$product->product_code.' · '.$product->category->name.($product->default_selling_price !== null ? ' · usual price '.\App\Support\Money::format($product->default_selling_price) : '')">
        <x-slot:badge>@if($product->trashed())<x-badge tone="neutral">Archived</x-badge>@elseif(! $product->is_active)<x-badge tone="neutral">Inactive</x-badge>@endif</x-slot:badge>
        <x-slot:actions>
            @if($canUpdate)
                @if($product->trashed())<form method="POST" action="{{ route('products.restore', $product) }}" data-busy>@csrf<x-button type="submit" data-busy-label="Restoring…">Restore product</x-button></form>
                @else<a href="{{ route('products.edit', $product) }}" class="inline-flex min-h-11 items-center rounded-lg border border-border bg-surface px-4 text-sm font-semibold hover:bg-background">Edit product</a>@endif
                @if($canAddVariant)<x-action-link :href="route('variants.create', $product)">Add size or colour</x-action-link>@endif
            @endif
        </x-slot:actions>
    </x-page-header>

    @error('status')<div role="alert" class="mb-6 rounded-xl border border-danger/30 bg-danger/5 p-4 text-sm text-danger">{{ $message }}</div>@enderror
    @if(! $categoryUsable)
        <div class="mb-6 rounded-xl border border-warning bg-warning/10 p-4 text-sm">The category <strong>{{ $product->category->name }}</strong> is switched off. Choose an active category before activating this product or adding options.</div>
    @endif
    @if($product->description)<p class="mb-6 max-w-3xl text-sm whitespace-pre-line break-words text-text-secondary">{{ $product->description }}</p>@endif

    <section class="overflow-hidden rounded-2xl border border-border bg-surface" aria-labelledby="options-heading">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border px-5 py-4">
            <h2 id="options-heading" class="text-base font-semibold">Sizes and colours</h2>
            @if($canUpdate)
                <form method="GET" x-data class="flex items-center gap-2 text-sm">
                    <label for="variant_status" class="text-text-secondary">Showing</label>
                    <select id="variant_status" name="variant_status" x-on:change="$el.form.submit()" class="min-h-9 rounded-lg border border-border bg-surface px-2 text-sm">
                        <option value="">Active and inactive</option>@foreach(['active' => 'Active only', 'inactive' => 'Inactive only', 'archived' => 'Archived'] as $value => $label)<option value="{{ $value }}" @selected(($filters['variant_status'] ?? '') === $value)>{{ $label }}</option>@endforeach
                    </select>
                    <noscript><x-button type="submit" variant="secondary">Apply</x-button></noscript>
                </form>
            @endif
        </div>
        @if($variants->isEmpty())
            <div class="p-10 text-center">
                <p class="font-semibold">No sizes or colours yet.</p>
                <p class="mt-1 text-sm text-text-secondary">Each option is one size and colour with its own price and stock.@if($canAddVariant) Add the first one to start selling this product.@endif</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="data-table">
                    <caption class="sr-only">Sizes and colours of {{ $product->name }}</caption>
                    <thead><tr><th scope="col">Option</th><th scope="col" class="num">Price</th><th scope="col" class="num">Ready to sell</th><th scope="col" class="num">Restock at</th>@if($canCost)<th scope="col" class="num">Average cost</th>@endif @if($canUpdate)<th scope="col"><span class="sr-only">Actions</span></th>@endif</tr></thead>
                    <tbody>
                        @foreach($variants as $variant)
                            @php
                                $ready = $variant->inventory?->available_quantity;
                                $inactiveRef = ($variant->size && ! $variant->size->is_active) || ($variant->colour && ! $variant->colour->is_active);
                            @endphp
                            <tr>
                                <th scope="row" class="font-normal">
                                    <span class="block font-semibold">{{ collect([$variant->size?->name, $variant->colour?->name])->filter()->join(' · ') ?: 'Standard' }}</span>
                                    <span class="block text-xs text-text-secondary break-all">{{ $variant->sku }}@if($variant->trashed()) · Archived @elseif(! $variant->is_active) · Inactive @endif @if($inactiveRef) · uses a switched-off size or colour @endif</span>
                                </th>
                                <td class="num">@money($variant->selling_price)</td>
                                <td class="num">
                                    <span class="font-semibold">{{ $ready ?? '?' }}</span>
                                    @if($ready !== null && $ready <= 0)<x-badge tone="danger" class="ml-2">Out</x-badge>@elseif($ready !== null && $ready <= $variant->low_stock_threshold)<x-badge tone="warning" class="ml-2">Low</x-badge>@endif
                                </td>
                                <td class="num text-text-secondary">{{ $variant->low_stock_threshold }}</td>
                                @if($canCost)<td class="num">@money($variant->weighted_average_cost)</td>@endif
                                @if($canUpdate)
                                    <td class="text-right whitespace-nowrap">
                                        @if(! $product->trashed())
                                            @if($variant->trashed())
                                                <form method="POST" action="{{ route('variants.restore', [$product, $variant->id]) }}" class="inline" data-busy>@csrf<button type="submit" class="font-semibold underline underline-offset-4" data-busy-label="Restoring…">Restore</button></form>
                                            @else
                                                <a href="{{ route('variants.edit', [$product, $variant->id]) }}" class="font-semibold underline underline-offset-4" aria-label="Edit {{ $variant->sku }}">Edit</a>
                                                <button type="button" x-data @click="$dispatch('open-modal', 'archive-variant-{{ $variant->id }}')" class="ml-4 font-semibold text-danger underline underline-offset-4" aria-label="Archive {{ $variant->sku }}">Archive</button>
                                            @endif
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        @if($variants->hasPages())<div class="border-t border-border p-4">{{ $variants->links() }}</div>@endif
    </section>

    @if($canUpdate && ! $product->trashed())
        <section class="mt-8 flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-border bg-surface p-5 text-sm" aria-labelledby="archive-heading">
            <div><h2 id="archive-heading" class="font-semibold">Stop selling this product</h2><p class="text-text-secondary">Archiving hides it from sale and search. Its history is kept and it can be restored.</p></div>
            <x-button variant="outline" class="text-danger" x-data @click="$dispatch('open-modal', 'archive-product')">Archive product</x-button>
        </section>
        <x-modal name="archive-product" title="Archive {{ $product->name }}?"><p class="mb-5 text-sm">It disappears from the point of sale and product search. Sales history and stock records are kept, and you can restore it from the Archived filter.</p><form method="POST" action="{{ route('products.destroy', $product) }}" data-busy>@csrf @method('DELETE')<x-button type="submit" variant="danger" data-busy-label="Archiving…">Archive product</x-button></form></x-modal>
        @foreach($variants as $variant)
            @if(! $variant->trashed())
                <x-modal :name="'archive-variant-'.$variant->id" title="Archive this option?"><p class="mb-5 break-words text-sm">{{ collect([$variant->size?->name, $variant->colour?->name])->filter()->join(' · ') ?: 'Standard' }} ({{ $variant->sku }}) stops being sold. Its records are kept.</p><form method="POST" action="{{ route('variants.destroy', [$product, $variant->id]) }}" data-busy>@csrf @method('DELETE')<x-button type="submit" variant="danger" data-busy-label="Archiving…">Archive option</x-button></form></x-modal>
            @endif
        @endforeach
    @endif
</x-layouts.app>
