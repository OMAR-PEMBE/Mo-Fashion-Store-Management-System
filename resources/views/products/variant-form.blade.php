@php
    $editing = $variant->exists;
    $chip = 'cursor-pointer rounded-lg border border-border bg-surface px-3.5 py-2 text-sm font-semibold hover:bg-background has-[:checked]:border-text-primary has-[:checked]:bg-text-primary has-[:checked]:text-white has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-info';
    $sizeId = (string) old('size_id', $variant->size_id);
    $colourId = (string) old('colour_id', $variant->colour_id);
@endphp
<x-layouts.app :title="$editing ? 'Edit option' : 'Add size or colour'">
    <x-page-header :title="$editing ? 'Edit '.(collect([$variant->size?->name, $variant->colour?->name])->filter()->join(' · ') ?: $variant->sku) : 'Add size or colour'"
        :back="route('products.show', $product)" :back-label="$product->name" :description="$product->name.' · '.$product->product_code" />

    <form method="POST" action="{{ $editing ? route('variants.update', [$product, $variant->id]) : route('variants.store', $product) }}" class="max-w-2xl space-y-6" data-busy>
        @csrf @if($editing) @method('PUT') @endif
        @if($errors->any())<div role="alert" class="rounded-xl border border-danger/30 bg-danger/5 p-4 text-sm text-danger">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif

        <section class="space-y-5 rounded-2xl border border-border bg-surface p-5 sm:p-6" aria-labelledby="look-heading">
            <h2 id="look-heading" class="text-base font-semibold">Size and colour</h2>
            <fieldset>
                <legend class="mb-2 text-sm font-medium">Size</legend>
                <div class="flex flex-wrap gap-2">
                    <label class="{{ $chip }}"><input type="radio" name="size_id" value="" class="sr-only" @checked($sizeId === '')>One size</label>
                    @foreach($sizes as $size)<label class="{{ $chip }}"><input type="radio" name="size_id" value="{{ $size->id }}" class="sr-only" @checked($sizeId === (string) $size->id)>{{ $size->name }}{{ $size->is_active ? '' : ' (off)' }}</label>@endforeach
                </div>
                @error('size_id')<p class="mt-2 text-sm text-danger">{{ $message }}</p>@enderror
            </fieldset>
            <fieldset>
                <legend class="mb-2 text-sm font-medium">Colour</legend>
                <div class="flex flex-wrap gap-2">
                    <label class="{{ $chip }}"><input type="radio" name="colour_id" value="" class="sr-only" @checked($colourId === '')>No colour</label>
                    @foreach($colours as $colour)<label class="{{ $chip }}"><input type="radio" name="colour_id" value="{{ $colour->id }}" class="sr-only" @checked($colourId === (string) $colour->id)>{{ $colour->name }}{{ $colour->is_active ? '' : ' (off)' }}</label>@endforeach
                </div>
                @error('colour_id')<p class="mt-2 text-sm text-danger">{{ $message }}</p>@enderror
                @if($colours->isEmpty())<p role="status" class="mt-2 text-sm text-text-secondary">No active colours are available. Add one in Catalogue setup, then reload this page.</p>@endif
                @can('reference-data.manage')<a href="{{ route('reference.index', 'colours') }}" target="_blank" rel="noopener" class="mt-2 inline-block text-xs font-semibold underline underline-offset-4">Missing a colour? Add it (opens a new tab, then reload this page)</a>@endcan
            </fieldset>
            @if($editing)
                <x-input name="correction_reason" label="Reason for changing size or colour" :value="old('correction_reason')" maxlength="255" help="Needed once this option has stock or sales. Past receipts then show the corrected details; quantities, costs and totals do not change." />
            @endif
        </section>

        <section class="space-y-5 rounded-2xl border border-border bg-surface p-5 sm:p-6" aria-labelledby="sell-heading">
            <h2 id="sell-heading" class="text-base font-semibold">Selling</h2>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-input name="selling_price" label="Selling price (TZS)" :value="old('selling_price', $variant->selling_price)" inputmode="decimal" required maxlength="16" help="The price every salesperson charges." />
                <x-input name="low_stock_threshold" label="Restock at" type="number" :value="old('low_stock_threshold', $variant->low_stock_threshold)" required min="0" max="4294967295" step="1" help="Flagged as running low at this many or fewer." />
            </div>
            <x-input name="sku" label="Product code for this option (optional)" :value="old('sku', $variant->sku)" maxlength="150" :placeholder="$editing ? $variant->sku : 'Created automatically'"
                :help="$editing ? 'Leave blank to keep it. Changing size or colour does not rename it.' : 'Leave blank and it is made from the product code, colour and size, for example JEANS-001-BLUE-M.'" />
            <input type="hidden" name="is_active" value="0">
            <label class="flex items-start gap-3 text-sm"><input type="checkbox" name="is_active" value="1" class="mt-0.5 size-4" @checked((string) old('is_active', (int) $variant->is_active) === '1')><span><span class="block font-semibold">Available for sale</span><span class="block text-text-secondary">Switch off to hide it from the point of sale without deleting anything.</span></span></label>
        </section>

        <p class="text-sm text-text-secondary">Saving does not add stock. Stock comes in through purchases or opening stock.</p>
        <div class="flex flex-wrap items-center gap-3">
            <x-button type="submit" data-busy-label="Saving…">{{ $editing ? 'Save changes' : 'Save' }}</x-button>
            @unless($editing)<x-button type="submit" variant="outline" name="add_another" value="1">Save and add another</x-button>@endunless
            <a href="{{ route('products.show', $product) }}" class="ml-2 text-sm font-semibold underline underline-offset-4">Cancel</a>
        </div>
    </form>
</x-layouts.app>
