@php $editing = $product->exists; @endphp
<x-layouts.app :title="$editing ? 'Edit product' : 'Add product'">
    <x-page-header :title="$editing ? 'Edit '.$product->name : 'Add product'" :back="$editing ? route('products.show', $product) : route('products.index')" :back-label="$editing ? $product->name : 'Products'"
        :description="$editing ? null : 'Add the product first; its sizes and colours, each with a price, come next.'" />

    <form method="POST" action="{{ $editing ? route('products.update', $product) : route('products.store') }}" class="max-w-2xl space-y-6" data-busy>
        @csrf @if($editing) @method('PUT') @endif
        @if($categories->isEmpty())<div role="status" class="rounded-xl border border-warning bg-warning/10 p-4 text-sm">Create an active category in Catalogue setup before adding a product.</div>@endif
        @if($errors->any())<div role="alert" class="rounded-xl border border-danger/30 bg-danger/5 p-4 text-sm text-danger">Please correct the fields marked below.</div>@endif

        <section class="space-y-5 rounded-2xl border border-border bg-surface p-5 sm:p-6">
            <x-input name="name" label="Product name" :value="old('name', $product->name)" required maxlength="191" autofocus placeholder="For example Boyfriend Jeans" />
            <div class="grid gap-5 sm:grid-cols-2" x-data="{ suggestions: @js($suggestions), auto: @js(! $editing && blank(old('product_code'))) }"
                x-init="const picked = $el.querySelector('select').value; if (auto && $refs.code && suggestions[picked]) $refs.code.value = suggestions[picked]">
                <x-select name="category_id" label="Category" required x-on:change="if (auto && suggestions[$event.target.value]) $refs.code.value = suggestions[$event.target.value]"><option value="">Choose a category</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(old('category_id', $product->category_id) == $category->id)>{{ $category->name }}{{ $category->is_active ? '' : ' (switched off)' }}</option>@endforeach</x-select>
                @if($codeLocked)
                    <x-input name="product_code" label="Product code" :value="$product->product_code" required maxlength="100" readonly class="bg-background" help="Fixed: it is already on receipts and stock records." />
                @else
                    <x-input name="product_code" label="Product code" :value="old('product_code', $product->product_code)" :required="$editing" maxlength="100" x-ref="code" x-on:input="auto = false"
                        :placeholder="$editing ? null : 'Choose a category first'"
                        :help="$editing ? 'Letters, numbers, - and _; saved in capitals. It becomes fixed once the product has stock or sales.' : 'Filled in from the category, for example DRE-001. Type your own if you prefer.'" />
                @endif
            </div>
            <x-input name="default_selling_price" label="Usual selling price (TZS, optional)" :value="old('default_selling_price', $product->default_selling_price)" inputmode="decimal" maxlength="16" help="Filled in for each new size or colour. Changing it later does not change existing prices." />
            <div><label for="description" class="mb-2 block text-sm font-medium">Description (optional)</label><textarea id="description" name="description" rows="3" maxlength="5000" class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm" @if($errors->has('description')) aria-invalid="true" aria-describedby="description-error" @endif>{{ old('description', $product->description) }}</textarea>@error('description')<p id="description-error" class="mt-2 text-sm text-danger">{{ $message }}</p>@enderror</div>
            <input type="hidden" name="is_active" value="0">
            <label class="flex items-start gap-3 text-sm"><input type="checkbox" name="is_active" value="1" class="mt-0.5 size-4" @checked((string) old('is_active', (int) ($editing ? $product->is_active : 1)) === '1')><span><span class="block font-semibold">Available for sale</span><span class="block text-text-secondary">Switch off to hide the product and all its options from the point of sale.</span></span></label>
        </section>

        <div class="flex flex-wrap items-center gap-4">
            <x-button type="submit" data-busy-label="Saving…">{{ $editing ? 'Save changes' : 'Save and add sizes' }}</x-button>
            <a href="{{ $editing ? route('products.show', $product) : route('products.index') }}" class="text-sm font-semibold underline underline-offset-4">Cancel</a>
        </div>
    </form>
</x-layouts.app>
