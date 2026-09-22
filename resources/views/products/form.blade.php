<x-layouts.app :title="$product->exists ? 'Edit product' : 'Add product'">
    <a href="{{ $product->exists ? route('products.show', $product) : route('products.index') }}" class="mb-5 inline-block text-sm underline underline-offset-4">Back to {{ $product->exists ? 'product' : 'products' }}</a>
    <h1 class="mb-7 text-3xl font-bold">{{ $product->exists ? 'Edit product' : 'Add product' }}</h1>
    <x-card class="max-w-2xl">
        @if($categories->isEmpty())<p role="status" class="mb-5 text-sm text-warning">Create an active category in Catalogue setup before adding a product.</p>@endif
        <form method="POST" action="{{ $product->exists ? route('products.update', $product) : route('products.store') }}" class="space-y-6">
            @csrf @if($product->exists) @method('PUT') @endif
            @if($errors->any())<p role="alert" class="text-sm text-danger">Please correct the highlighted fields.</p>@endif
            <x-input name="name" label="Product name" :value="old('name', $product->name)" required maxlength="191" autofocus />
            <x-input name="product_code" label="Product code" :value="old('product_code', $product->product_code)" required maxlength="100" help="A unique code, such as JEANS-001. Saved in uppercase; use letters, numbers, hyphens or underscores." />
            <x-select name="category_id" label="Category" required><option value="">Choose a category</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(old('category_id', $product->category_id) == $category->id)>{{ $category->name }}{{ $category->is_active ? '' : ' (inactive)' }}</option>@endforeach</x-select>
            <div><label for="description" class="mb-2 block text-sm font-medium">Description (optional)</label><textarea id="description" name="description" rows="4" maxlength="5000" class="w-full rounded-lg border border-border px-3 py-2 text-sm" @if($errors->has('description')) aria-invalid="true" aria-describedby="description-error" @endif>{{ old('description', $product->description) }}</textarea>@error('description')<p id="description-error" class="mt-2 text-sm text-danger">{{ $message }}</p>@enderror</div>
            <x-input name="default_selling_price" label="Default selling price (TZS, optional)" :value="old('default_selling_price', $product->default_selling_price)" inputmode="decimal" maxlength="16" help="Used to prefill new variants. Up to two decimal places; existing variant prices stay unchanged." />
            <x-select name="is_active" label="Status"><option value="1" @selected((string) old('is_active', (int) $product->is_active) === '1')>Active</option><option value="0" @selected((string) old('is_active', (int) $product->is_active) === '0')>Inactive</option></x-select>
            <div class="flex items-center gap-5 border-t border-border pt-5"><x-button type="submit">{{ $product->exists ? 'Save changes' : 'Create product' }}</x-button><a href="{{ route('products.index') }}" class="text-sm underline">Cancel</a></div>
        </form>
    </x-card>
</x-layouts.app>
