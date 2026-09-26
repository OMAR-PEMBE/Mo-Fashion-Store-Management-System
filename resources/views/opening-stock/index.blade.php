@php
    $groups = $variants->getCollection()->groupBy('product_id');
    $remaining = $variants->total();
    $hasSearch = filled($filters['q'] ?? null);
@endphp
<x-layouts.app title="Opening stock">
    <x-page-header title="Opening stock" description="Count what is already on your shelves and enter it once, when you start using the system. New deliveries go through Purchases." />

    @if($counted || $remaining)
        <div class="mb-6 rounded-2xl border border-border bg-surface p-5">
            <div class="flex flex-wrap items-baseline justify-between gap-2 text-sm">
                <p><span class="text-lg font-bold tabular-nums">{{ number_format($counted) }}</span> counted</p>
                <p class="text-text-secondary">{{ number_format($remaining) }} {{ $hasSearch ? 'found' : 'still to count' }}</p>
            </div>
            @unless($hasSearch)
                <div class="mt-3 h-2 overflow-hidden rounded-full bg-background" role="progressbar" aria-label="Opening stock counted" aria-valuemin="0" aria-valuemax="{{ $counted + $remaining }}" aria-valuenow="{{ $counted }}">
                    <div class="h-full rounded-full bg-primary" style="width: {{ $counted + $remaining ? round($counted / ($counted + $remaining) * 100, 1) : 0 }}%"></div>
                </div>
            @endunless
        </div>
    @endif

    <form method="GET" class="mb-5" role="search">
        <div class="relative max-w-md">
            <label for="opening-search" class="sr-only">Find items to count</label>
            <svg class="pointer-events-none absolute top-1/2 left-3.5 size-5 -translate-y-1/2 text-text-secondary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <input id="opening-search" name="q" type="search" value="{{ $filters['q'] ?? '' }}" maxlength="191" placeholder="Product name or code" class="min-h-11 w-full rounded-lg border border-border bg-surface py-2 pr-3 pl-11 text-sm">
        </div>
    </form>

    @if($variants->isEmpty())
        <div class="rounded-2xl border border-border bg-surface p-10 text-center">
            @if($hasSearch)
                <p class="font-semibold">Nothing to count matches “{{ $filters['q'] }}”.</p>
                <p class="mt-1 text-sm text-text-secondary">It may already be counted, or be switched off in Products.</p>
                <a href="{{ route('opening-stock.index') }}" class="mt-3 inline-block text-sm font-semibold underline underline-offset-4">Clear search</a>
            @elseif($counted)
                <p class="font-semibold">Everything is counted.</p>
                <p class="mt-1 text-sm text-text-secondary">From now on, stock comes in through Purchases.</p>
                @can('purchases.manage')<a href="{{ route('purchases.create') }}" class="mt-3 inline-block text-sm font-semibold underline underline-offset-4">Record a purchase</a>@endcan
            @else
                <p class="font-semibold">No products to count yet.</p>
                <p class="mt-1 text-sm text-text-secondary">Add your products with their sizes and colours first, then come back to enter what is on the shelves.</p>
                <a href="{{ route('products.index') }}" class="mt-3 inline-block text-sm font-semibold underline underline-offset-4">Go to Products</a>
            @endif
        </div>
    @else
        <form method="POST" action="{{ route('opening-stock.sheet.review') }}" x-data="openingSheet()" @submit="submitting = true" @input="refresh()" class="space-y-4">
            @csrf
            @if($hasSearch)<input type="hidden" name="q" value="{{ $filters['q'] }}">@endif
            @if($variants->currentPage() > 1)<input type="hidden" name="page" value="{{ $variants->currentPage() }}">@endif
            @if($errors->any())<div role="alert" class="rounded-xl border border-danger/30 bg-danger/5 p-4 text-sm text-danger"><p class="font-semibold">Please fix the highlighted items.</p>@error('items')<p>{{ $message }}</p>@enderror</div>@endif
            <p class="text-sm text-text-secondary">Enter how many are in the shop and what each one cost you (not the selling price). Leave items you have not counted blank; you can come back to them.</p>

            @foreach($groups as $productVariants)
                @php $product = $productVariants->first()->product; @endphp
                <section class="overflow-hidden rounded-2xl border border-border bg-surface" aria-labelledby="product-{{ $product->id }}" data-group>
                    <div class="grid gap-x-3 gap-y-1 border-b border-border bg-background/60 px-5 py-3 sm:grid-cols-[minmax(0,1fr)_140px_180px] sm:items-end">
                        <div class="min-w-0">
                            <h2 id="product-{{ $product->id }}" class="font-semibold break-words">{{ $product->name }} <span class="text-xs font-normal text-text-secondary">{{ $product->product_code }}</span></h2>
                            @if($productVariants->count() > 1)<button type="button" class="text-xs font-semibold underline underline-offset-4" @click="sameCost($el)">Copy the first cost to the other counted sizes</button>@endif
                        </div>
                        <p class="hidden text-xs font-medium text-text-secondary sm:block" aria-hidden="true">How many</p>
                        <p class="hidden text-xs font-medium text-text-secondary sm:block" aria-hidden="true">Cost each (TZS)</p>
                    </div>
                    <ul class="divide-y divide-border">
                        @foreach($productVariants as $variant)
                            @php
                                $name = collect([$variant->size?->name, $variant->colour?->name])->filter()->join(' · ') ?: 'One size';
                                $qtyError = $errors->first('items.'.$variant->id.'.quantity');
                                $costError = $errors->first('items.'.$variant->id.'.unit_cost');
                            @endphp
                            <li @class(["grid grid-cols-2 items-start gap-3 px-5 py-3 sm:grid-cols-[minmax(0,1fr)_140px_180px]", "bg-danger/5" => $qtyError || $costError])>
                                <div class="col-span-2 min-w-0 sm:col-span-1 sm:pt-2">
                                    <p class="text-sm font-semibold break-words">{{ $name }}</p>
                                    <p class="text-xs text-text-secondary">{{ $variant->sku }} · sells at @money($variant->selling_price)</p>
                                </div>
                                <div>
                                    <label for="qty-{{ $variant->id }}" class="mb-1 block text-xs font-medium sm:sr-only">How many for {{ $name }}</label>
                                    <input id="qty-{{ $variant->id }}" name="items[{{ $variant->id }}][quantity]" value="{{ old('items.'.$variant->id.'.quantity') }}" type="number" min="1" max="2147483647" step="1" inputmode="numeric" placeholder="Count" data-qty
                                        class="min-h-11 w-full rounded-lg border bg-surface px-3 py-2 text-sm tabular-nums {{ $qtyError ? 'border-danger' : 'border-border' }}" @if($qtyError) aria-invalid="true" aria-describedby="qty-error-{{ $variant->id }}" @endif>
                                    @if($qtyError)<p id="qty-error-{{ $variant->id }}" class="mt-1 text-xs text-danger">{{ $qtyError }}</p>@endif
                                </div>
                                <div>
                                    <label for="cost-{{ $variant->id }}" class="mb-1 block text-xs font-medium sm:sr-only">Cost each for {{ $name }} (TZS)</label>
                                    <input id="cost-{{ $variant->id }}" name="items[{{ $variant->id }}][unit_cost]" value="{{ old('items.'.$variant->id.'.unit_cost') }}" inputmode="decimal" maxlength="16" placeholder="Cost each (TZS)" data-cost
                                        class="min-h-11 w-full rounded-lg border bg-surface px-3 py-2 text-sm tabular-nums {{ $costError ? 'border-danger' : 'border-border' }}" @if($costError) aria-invalid="true" aria-describedby="cost-error-{{ $variant->id }}" @endif>
                                    @if($costError)<p id="cost-error-{{ $variant->id }}" class="mt-1 text-xs text-danger">{{ $costError }}</p>@endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach

            <div class="sticky bottom-0 z-10 -mx-4 flex flex-wrap items-center justify-between gap-3 border-t border-border bg-surface/95 px-4 py-3 backdrop-blur sm:mx-0 sm:rounded-2xl sm:border">
                <p class="text-sm" aria-live="polite"><span class="font-bold tabular-nums" x-text="items"></span> <span x-text="items === 1 ? 'item' : 'items'"></span> entered · <span class="font-bold tabular-nums" x-text="units.toLocaleString()"></span> units · value <span class="font-bold tabular-nums" x-text="formatMoney((cents / 100).toFixed(2))"></span></p>
                <button type="submit" :disabled="items === 0" class="inline-flex min-h-11 items-center rounded-xl bg-primary px-5 text-sm font-bold text-text-primary hover:bg-primary-hover disabled:cursor-not-allowed disabled:opacity-50">Review these counts</button>
            </div>
        </form>
        @if($variants->hasPages())<div class="mt-4">{{ $variants->links() }}</div>@endif
    @endif
</x-layouts.app>
