<x-layouts.app :title="$variant->exists ? 'Edit variant' : 'Add variant'">
    <a href="{{ route('products.show', $product) }}" class="mb-5 inline-block text-sm underline underline-offset-4">Back to {{ $product->name }}</a>
    <h1 class="text-3xl font-bold">{{ $variant->exists ? 'Edit variant' : 'Add variant' }}</h1>
    <p class="mb-7 mt-3 text-sm text-text-secondary">{{ $product->name }} · {{ $product->product_code }}</p>
    <x-card class="max-w-2xl">
        <form method="POST" action="{{ $variant->exists ? route('variants.update', [$product, $variant->id]) : route('variants.store', $product) }}" class="space-y-6">
            @csrf @if($variant->exists) @method('PUT') @endif
            @if($errors->any())<p role="alert" class="text-sm text-danger">Please correct the highlighted fields.</p>@endif
            <x-input name="sku" label="SKU" :value="old('sku', $variant->sku)" required maxlength="150" autofocus help="Unique across all products, for example JEANS-BLUE-M. Use letters, numbers, hyphens or underscores." />
            <div class="grid gap-6 sm:grid-cols-2">
                <x-select name="size_id" label="Size"><option value="">No size / one size</option>@foreach($sizes as $size)<option value="{{ $size->id }}" @selected(old('size_id', $variant->size_id) == $size->id)>{{ $size->name }}{{ $size->is_active ? '' : ' (inactive)' }}</option>@endforeach</x-select>
                <x-select name="colour_id" label="Colour"><option value="">No colour</option>@foreach($colours as $colour)<option value="{{ $colour->id }}" @selected(old('colour_id', $variant->colour_id) == $colour->id)>{{ $colour->name }}{{ $colour->is_active ? '' : ' (inactive)' }}</option>@endforeach</x-select>
            </div>
            <x-input name="selling_price" label="Selling price (TZS)" :value="old('selling_price', $variant->selling_price)" inputmode="decimal" required maxlength="16" help="Zero or greater, with up to two decimal places." />
            <x-input name="low_stock_threshold" label="Low-stock threshold" type="number" :value="old('low_stock_threshold', $variant->low_stock_threshold)" required min="0" max="4294967295" step="1" help="The quantity at which this variant should be flagged as low stock." />
            <x-select name="is_active" label="Status"><option value="1" @selected((string) old('is_active', (int) $variant->is_active) === '1')>Active</option><option value="0" @selected((string) old('is_active', (int) $variant->is_active) === '0')>Inactive</option></x-select>
            <p class="text-sm text-text-secondary">Saving a variant does not add stock. Stock will be recorded through opening balances and purchases.</p>
            <div class="flex items-center gap-5 border-t border-border pt-5"><x-button type="submit">{{ $variant->exists ? 'Save changes' : 'Create variant' }}</x-button><a href="{{ route('products.show', $product) }}" class="text-sm underline">Cancel</a></div>
        </form>
    </x-card>
</x-layouts.app>
