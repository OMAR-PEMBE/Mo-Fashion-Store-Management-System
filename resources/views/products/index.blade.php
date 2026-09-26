@php
    $canManage = auth()->user()->can('products.update');
    $filtered = ($filters['q'] ?? null) || ($filters['category_id'] ?? null) || ($filters['status'] ?? null);
    $state = fn ($product) => $product->trashed() ? ['Archived', 'neutral'] : ($product->is_active ? null : ['Inactive', 'neutral']);
    $price = function ($product) {
        if (! $product->variants_count) {
            return $product->default_selling_price !== null ? \App\Support\Money::format($product->default_selling_price) : 'No price yet';
        }
        return $product->variants_min_selling_price === $product->variants_max_selling_price
            ? \App\Support\Money::format($product->variants_min_selling_price)
            : \App\Support\Money::format($product->variants_min_selling_price).' to '.\App\Support\Money::format($product->variants_max_selling_price, false);
    };
@endphp
<x-layouts.app title="Products">
    <x-page-header title="Products" description="Everything the shop sells. Each product has one or more size and colour options with their own price and stock.">
        <x-slot:actions>@can('create', \App\Models\Product::class)<x-action-link :href="route('products.create')">Add product</x-action-link>@endcan</x-slot:actions>
    </x-page-header>

    <form method="GET" class="mb-5 flex flex-wrap items-end gap-3" x-data>
        <div class="w-full max-w-md">
            <label for="product-search" class="sr-only">Search products</label>
            <div class="relative">
                <svg class="pointer-events-none absolute top-1/2 left-3.5 size-5 -translate-y-1/2 text-text-secondary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <input id="product-search" name="q" type="search" value="{{ $filters['q'] ?? '' }}" maxlength="191" placeholder="Name or code" class="min-h-11 w-full rounded-lg border border-border bg-surface py-2 pr-3 pl-11 text-sm">
            </div>
        </div>
        <div class="w-48"><x-select name="category_id" label="Category" x-on:change="$el.form.submit()"><option value="">All categories</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(($filters['category_id'] ?? '') == $category->id)>{{ $category->name }}</option>@endforeach</x-select></div>
        @if($canManage)
            <div class="w-44"><x-select name="status" label="Showing" x-on:change="$el.form.submit()"><option value="">Active and inactive</option>@foreach(['active' => 'Active only', 'inactive' => 'Inactive only', 'archived' => 'Archived'] as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</x-select></div>
        @endif
        <noscript><x-button type="submit" variant="secondary">Apply</x-button></noscript>
        @if($filtered)<a href="{{ route('products.index') }}" class="py-3 text-sm font-semibold underline underline-offset-4">Clear filters</a>@endif
    </form>

    <div class="overflow-hidden rounded-2xl border border-border bg-surface">
        @if($products->isEmpty())
            <div class="p-10 text-center">
                <p class="font-semibold">{{ $filtered ? 'No products match these filters.' : 'No products yet.' }}</p>
                <p class="mt-1 text-sm text-text-secondary">{{ $filtered ? 'Try another name or category.' : 'Add your first product, then its sizes and colours.' }}</p>
            </div>
        @else
            <ul class="divide-y divide-border sm:hidden">
                @foreach($products as $product)
                    <li class="relative px-4 py-4 hover:bg-selected/50">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <a href="{{ route('products.show', $product) }}" class="row-link break-words">{{ $product->name }}</a>
                                <p class="text-xs text-text-secondary">{{ $product->category->name }} · {{ $product->variants_count }} {{ \Illuminate\Support\Str::plural('option', $product->variants_count) }}</p>
                                <p class="text-xs text-text-secondary">{{ $price($product) }}</p>
                            </div>
                            <div class="shrink-0 text-right"><p class="font-bold tabular-nums">{{ number_format((int) $product->ready_units) }}</p><p class="text-xs text-text-secondary">ready</p>@if($s = $state($product))<x-badge :tone="$s[1]" class="mt-1">{{ $s[0] }}</x-badge>@endif</div>
                        </div>
                    </li>
                @endforeach
            </ul>
            <div class="hidden overflow-x-auto sm:block">
                <table class="data-table">
                    <caption class="sr-only">Products</caption>
                    <thead><tr><th scope="col">Product</th><th scope="col">Category</th><th scope="col">Price</th><th scope="col" class="num">Options</th><th scope="col" class="num">Ready to sell</th></tr></thead>
                    <tbody>
                        @foreach($products as $product)
                            <tr>
                                <th scope="row" class="font-normal"><a href="{{ route('products.show', $product) }}" class="row-link break-words">{{ $product->name }}</a>
                                    <span class="block text-xs text-text-secondary">{{ $product->product_code }}@if($s = $state($product)) · {{ $s[0] }}@endif</span></th>
                                <td>{{ $product->category->name }}</td>
                                <td class="whitespace-nowrap">{{ $price($product) }}</td>
                                <td class="num">{{ $product->variants_count }}</td>
                                <td @class(['num font-semibold', 'text-danger' => $product->variants_count && (int) $product->ready_units <= 0])>{{ number_format((int) $product->ready_units) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        @if($products->hasPages())<div class="border-t border-border p-4">{{ $products->links() }}</div>@endif
    </div>
</x-layouts.app>
