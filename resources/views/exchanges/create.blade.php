@php
    $conditions = ['SELLABLE' => 'Sellable', 'DAMAGED' => 'Damaged', 'DEFECTIVE' => 'Defective', 'OTHER' => 'Other'];
    $windowDays = (int) config('exchanges.window_days');
    $chip = 'cursor-pointer rounded-lg border px-3 py-1.5 text-sm font-semibold has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-info';
@endphp
<x-layouts.app title="New exchange">
    <x-page-header title="New exchange" :back="route('exchanges.index')" back-label="Exchanges"
        :description="'Swap items from a sale made in the last '.$windowDays.' days. Saving does not move stock; the exchange is completed next.'" />

    @if($errors->any())
        <div role="alert" class="mb-6 rounded-xl border border-danger/30 bg-danger/5 p-4 text-sm text-danger">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>
    @endif

    @if(! $sale)
        <x-sale-picker :action="route('exchanges.create')" :recent="$recentSales" :value="request('sale_number', '')"
            :window-text="'Sales still inside the '.$windowDays.'-day exchange window.'" empty-text="No sales are inside the exchange window right now." />
    @else
        @php
            // Discounted value per unit, used only for the on-screen estimate. The server calculates the real amounts.
            $returned = $sale->items->mapWithKeys(fn ($item, $index) => [$index => ['quantity' => (int) old('returned_items.'.$index.'.quantity', 0), 'condition' => old('returned_items.'.$index.'.condition', 'SELLABLE'),
                'unit' => $item->quantity > 0 ? round((float) $item->line_total / $item->quantity, 2) : 0, 'max' => $remaining[$item->id]]]);
        @endphp
        <form method="POST" action="{{ route('exchanges.store') }}" class="space-y-6" data-busy
            x-data="{ ...saleForm({id:'',label:''}, @js($lines), @js(route('exchanges.lookup')), true), returned: @js($returned), reason: @js(old('reason', '')),
                get returnedValue() { return Object.values(this.returned).reduce((s, r) => s + (Number(r.quantity) || 0) * r.unit, 0) },
                get replacementValue() { return this.lines.reduce((s, l) => s + (Number(l.quantity) || 0) * Number(l.unit_price), 0) },
                get returnedCount() { return Object.values(this.returned).reduce((s, r) => s + (Number(r.quantity) || 0), 0) },
                get blocker() { return this.returnedCount < 1 ? 'Choose what the customer is bringing back.' : (! this.lines.length ? 'Add the replacement items.' : (! this.reason.trim() ? 'Give the reason for the exchange.' : '')) } }"
            x-init="search('variant')">
            @csrf
            <input type="hidden" name="sale_id" value="{{ $sale->id }}"><input type="hidden" name="request_key" value="{{ $requestKey }}">

            <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-border bg-surface px-5 py-4 text-sm">
                <div>
                    <p class="font-semibold">{{ $sale->sale_number }} · {{ $sale->customer?->full_name ?? 'Walk-in customer' }}</p>
                    <p class="text-text-secondary">Sold {{ $sale->completed_at->format('j M Y, H:i') }} · exchange window closes <span class="font-semibold text-text-primary">{{ $deadline->format('j M, H:i') }}</span></p>
                </div>
                <a href="{{ route('exchanges.create') }}" class="font-semibold underline underline-offset-4">Choose another sale</a>
            </div>

            <div class="grid gap-6 lg:grid-cols-2 lg:items-start">
                {{-- 1. What comes back --}}
                <section class="overflow-hidden rounded-2xl border border-border bg-surface" aria-labelledby="returned-heading">
                    <h2 id="returned-heading" class="border-b border-border px-5 py-4 text-base font-semibold">1. What is the customer bringing back?</h2>
                    <ul class="divide-y divide-border">
                        @foreach($sale->items as $index => $item)
                            @php $max = $remaining[$item->id]; @endphp
                            <li class="px-5 py-4" :class="returned[{{ $index }}].quantity > 0 ? 'bg-selected/40' : ''">
                                <input type="hidden" name="returned_items[{{ $index }}][sale_item_id]" value="{{ $item->id }}">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div class="min-w-0 text-sm">
                                        <p class="font-semibold break-words">{{ $item->variant->product->name }}</p>
                                        <p class="text-xs text-text-secondary">{{ collect([$item->variant->size?->name, $item->variant->colour?->name, $item->variant->sku])->filter()->join(' · ') }}</p>
                                        <p class="mt-1 text-xs text-text-secondary">{{ $max }} of {{ $item->quantity }} can be exchanged · worth @money($item->quantity > 0 ? number_format((float) $item->line_total / $item->quantity, 2, '.', '') : '0') each</p>
                                    </div>
                                    <div class="flex items-center rounded-lg border border-border bg-surface" role="group" aria-label="Quantity coming back">
                                        <button type="button" @click="returned[{{ $index }}].quantity = Math.max(0, returned[{{ $index }}].quantity - 1)" :disabled="returned[{{ $index }}].quantity <= 0" class="flex size-10 items-center justify-center rounded-l-lg hover:bg-background disabled:opacity-40" aria-label="One fewer"><svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M5 12h14"/></svg></button>
                                        <input name="returned_items[{{ $index }}][quantity]" x-model.number="returned[{{ $index }}].quantity" inputmode="numeric" @keydown.enter.prevent aria-label="Quantity coming back" class="h-10 w-12 border-x border-border text-center text-sm font-semibold tabular-nums">
                                        <button type="button" @click="returned[{{ $index }}].quantity = Math.min({{ $max }}, returned[{{ $index }}].quantity + 1)" :disabled="returned[{{ $index }}].quantity >= {{ $max }}" class="flex size-10 items-center justify-center rounded-r-lg hover:bg-background disabled:opacity-40" aria-label="One more"><svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg></button>
                                    </div>
                                </div>
                                <fieldset class="mt-3" x-show="returned[{{ $index }}].quantity > 0">
                                    <legend class="mb-2 text-xs font-medium">Condition</legend>
                                    <div class="flex flex-wrap gap-2">
                                        @foreach($conditions as $value => $label)
                                            <label :class="returned[{{ $index }}].condition === @js($value) ? 'border-text-primary bg-text-primary text-white' : 'border-border bg-surface hover:bg-background'" class="{{ $chip }}"><input type="radio" name="returned_items[{{ $index }}][condition]" value="{{ $value }}" x-model="returned[{{ $index }}].condition" class="sr-only">{{ $label }}</label>
                                        @endforeach
                                    </div>
                                </fieldset>
                            </li>
                        @endforeach
                    </ul>
                </section>

                {{-- 2. What they take instead --}}
                <section class="rounded-2xl border border-border bg-surface" aria-labelledby="replacement-heading">
                    <h2 id="replacement-heading" class="border-b border-border px-5 py-4 text-base font-semibold">2. What are they taking instead?</h2>
                    <div class="p-5">
                        <label for="product-search" class="sr-only">Search replacement products</label>
                        <div class="relative">
                            <svg class="pointer-events-none absolute top-1/2 left-3.5 size-5 -translate-y-1/2 text-text-secondary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                            <input id="product-search" x-model="query" @input.debounce.300ms="search('variant')" @keydown.enter.prevent="search('variant')" type="search" autocomplete="off" maxlength="191" placeholder="Search name or code" class="min-h-11 w-full rounded-lg border border-border bg-surface py-2 pr-3 pl-11 text-sm">
                        </div>
                        <p role="status" class="mt-2 text-xs text-text-secondary" x-text="error"></p>
                        <div class="mt-3 grid max-h-72 grid-cols-2 gap-2 overflow-y-auto">
                            <template x-for="result in results" :key="result.id">
                                <button type="button" @click="add(result)" class="rounded-xl border border-border p-3 text-left text-sm transition-transform duration-150 ease-out hover:border-primary hover:bg-selected/40 active:scale-[0.97] motion-reduce:transition-none">
                                    <span class="block font-semibold break-words" x-text="result.name"></span>
                                    <span class="block text-xs text-text-secondary" x-text="result.variant || 'Standard'"></span>
                                    <span class="mt-1 block font-bold tabular-nums" x-text="formatMoney(result.unit_price)"></span>
                                    <span class="block text-xs" :class="result.available < 1 ? 'font-semibold text-danger' : 'text-text-secondary'" x-text="result.available < 1 ? 'Out of stock now' : result.available + ' in stock'"></span>
                                </button>
                            </template>
                        </div>
                    </div>
                    <div class="border-t border-border px-5 py-4">
                        <p x-show="! lines.length" class="text-sm text-text-secondary">Tap a product above to add it.</p>
                        <ul class="divide-y divide-border">
                            <template x-for="(line, index) in lines" :key="line.product_variant_id">
                                <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                                    <input type="hidden" :name="`replacement_items[${index}][product_variant_id]`" :value="line.product_variant_id">
                                    <div class="min-w-0 text-sm">
                                        <p class="font-semibold break-words" x-text="line.name || line.label"></p>
                                        <p class="text-xs text-text-secondary" x-text="[line.variant, line.sku].filter(Boolean).join(' · ') + ' · ' + formatMoney(line.unit_price) + ' each'"></p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <input :name="`replacement_items[${index}][quantity]`" x-model="line.quantity" @keydown.enter.prevent inputmode="numeric" :aria-label="'Quantity of ' + (line.name || line.label)" class="h-10 w-14 rounded-lg border border-border text-center text-sm font-semibold tabular-nums">
                                        <button type="button" @click="lines.splice(index, 1)" :aria-label="'Remove ' + (line.name || line.label)" class="flex size-9 items-center justify-center rounded-lg text-text-secondary hover:bg-background hover:text-danger"><svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg></button>
                                    </div>
                                </li>
                            </template>
                        </ul>
                    </div>
                </section>
            </div>

            {{-- 3. The difference and the reason --}}
            <section class="grid gap-5 rounded-2xl border border-border bg-surface p-5 md:grid-cols-2" aria-labelledby="difference-heading">
                <div>
                    <h2 id="difference-heading" class="text-base font-semibold">3. The difference</h2>
                    <dl class="mt-3 space-y-1.5 text-sm">
                        <div class="flex justify-between"><dt class="text-text-secondary">Coming back</dt><dd class="tabular-nums" x-text="formatMoney(returnedValue.toFixed(2))"></dd></div>
                        <div class="flex justify-between"><dt class="text-text-secondary">Going out</dt><dd class="tabular-nums" x-text="formatMoney(replacementValue.toFixed(2))"></dd></div>
                        <div class="flex items-baseline justify-between border-t border-border pt-2"><dt class="font-semibold" x-text="replacementValue > returnedValue ? 'Customer pays about' : (replacementValue < returnedValue ? 'Refund due, about' : 'Same value')"></dt><dd class="text-xl font-bold tabular-nums" x-text="formatMoney(Math.abs(replacementValue - returnedValue).toFixed(2))"></dd></div>
                    </dl>
                    <p class="mt-2 text-xs text-text-secondary">An estimate. The exact amount is calculated when you save; a refund needs an administrator.</p>
                </div>
                <div class="space-y-4">
                    <div><label for="reason" class="mb-2 block text-sm font-medium">Reason for the exchange</label><input id="reason" name="reason" x-model="reason" required maxlength="255" placeholder="For example wrong size" class="min-h-11 w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm"></div>
                    <x-input name="notes" label="Notes (optional)" :value="old('notes')" maxlength="5000" />
                </div>
            </section>

            <div class="flex flex-wrap items-center gap-4">
                <button type="submit" :disabled="blocker !== ''" data-busy-label="Saving…" class="inline-flex min-h-12 items-center rounded-xl bg-primary px-6 text-base font-bold text-text-primary hover:bg-primary-hover disabled:cursor-not-allowed disabled:opacity-50">Save exchange</button>
                <p class="text-sm text-text-secondary" x-text="blocker || 'Next: complete it once the items are checked and any payment is settled.'"></p>
            </div>
        </form>
    @endif
</x-layouts.app>
