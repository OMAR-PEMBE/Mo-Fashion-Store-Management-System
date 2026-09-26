<x-layouts.app title="Products">
    <div class="mb-8 flex flex-wrap items-start justify-between gap-4">
        <div><p class="mb-2 text-sm text-text-secondary">Catalogue</p><h1 class="text-3xl font-bold">Products</h1><p class="mt-3 text-sm text-text-secondary">Find your products and their size and colour options.</p></div>
        @can('create', \App\Models\Product::class)<x-action-link :href="route('products.create')">Add product</x-action-link>@endcan
    </div>
    
    <form method="GET" class="mb-6 grid items-end gap-4 rounded-xl border border-border bg-surface p-5 md:grid-cols-2 xl:grid-cols-4">
        <x-input name="q" label="Search" :value="$filters['q'] ?? ''" placeholder="Name, product code or SKU" maxlength="191" />
        <x-select name="category_id" label="Category"><option value="">All categories</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(($filters['category_id'] ?? '') == $category->id)>{{ $category->name }}</option>@endforeach</x-select>
        @can('products.update')
        <x-select name="status" label="Status"><option value="">Active and inactive</option>@foreach(['active' => 'Active', 'inactive' => 'Inactive', 'archived' => 'Archived'] as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</x-select>
        @endcan
        <div class="flex items-center gap-3"><x-button type="submit" variant="secondary">Filter</x-button><a href="{{ route('products.index') }}" class="text-sm underline">Clear</a></div>
    </form>
    <div class="overflow-hidden rounded-xl border border-border bg-surface">
        @if($products->isEmpty())
            <div class="px-6 py-14 text-center"><h2 class="font-semibold">No products found</h2><p class="mt-2 text-sm text-text-secondary">Try different filters.@can('create', \App\Models\Product::class) You can also add your first product.@endcan</p></div>
        @else
        <div class="relative overflow-x-auto"><table class="w-full min-w-[700px] text-left text-sm">
            <caption class="sr-only">Product catalogue</caption>
            <thead class="border-b border-border bg-background"><tr>@foreach(['Product', 'Category', 'Default price', 'Status', ''] as $heading)<th scope="col" class="px-5 py-4 font-medium">{{ $heading }}@if(!$heading)<span class="sr-only">Actions</span>@endif</th>@endforeach</tr></thead>
            <tbody class="divide-y divide-border">@foreach($products as $product)<tr class="hover:bg-selected/50">
                <th scope="row" class="max-w-xs break-words px-5 py-4 font-medium">{{ $product->name }}<span class="mt-1 block break-all text-xs font-normal text-text-secondary">{{ $product->product_code }}</span></th>
                <td class="px-5 py-4">{{ $product->category->name }}</td>
                <td class="whitespace-nowrap px-5 py-4">{{ $product->default_selling_price !== null ? \App\Support\Money::format($product->default_selling_price) : 'Not set' }}</td>
                <td class="px-5 py-4"><x-badge :tone="$product->is_active && !$product->trashed() ? 'success' : 'warning'">{{ $product->trashed() ? 'Archived' : ($product->is_active ? 'Active' : 'Inactive') }}</x-badge></td>
                <td class="px-5 py-4"><a href="{{ route('products.show', $product) }}" class="inline-flex min-h-11 items-center underline underline-offset-4" aria-label="View {{ $product->name }}">View</a></td>
            </tr>@endforeach</tbody>
        </table></div>
        <div class="border-t border-border p-5">{{ $products->links() }}</div>
        @endif
    </div>
</x-layouts.app>
