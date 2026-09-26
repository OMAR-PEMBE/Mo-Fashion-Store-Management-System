@php
    $conditions = ['SELLABLE' => 'Sellable', 'DAMAGED' => 'Damaged', 'DEFECTIVE' => 'Defective', 'OTHER' => 'Other'];
    $windowDays = (int) config('returns.window_days');
@endphp
<x-layouts.app title="New return">
    <x-page-header title="New return" :back="route('returns.index')" back-label="Returns"
        :description="'Take items back from a sale made in the last '.$windowDays.' days. Saving does not change stock or refund money; the return is approved and completed next.'" />

    @if($errors->any())
        <div role="alert" class="mb-6 rounded-xl border border-danger/30 bg-danger/5 p-4 text-sm text-danger">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>
    @endif

    @if(! $sale)
        <x-sale-picker :action="route('returns.create')" :recent="$recentSales" :value="request('sale_number', '')"
            :window-text="'Sales still inside the '.$windowDays.'-day return window.'" empty-text="No sales are inside the return window right now." />
    @else
        @php
            $initial = $sale->items->mapWithKeys(fn ($item, $index) => [$index => ['quantity' => (int) old('items.'.$index.'.quantity', 0), 'condition' => old('items.'.$index.'.condition', 'SELLABLE')]]);
        @endphp
        <form method="POST" action="{{ route('returns.store') }}" class="space-y-6" data-busy
            x-data="{ items: @js($initial), proof: @js(old('proof_type', 'SALE_RECORD')), reason: @js(old('reason', '')),
                get count() { return Object.values(this.items).reduce((sum, item) => sum + (Number(item.quantity) || 0), 0) },
                get blocker() { return this.count < 1 ? 'Choose at least one item to return.' : (! this.reason.trim() ? 'Give the reason for the return.' : '') } }">
            @csrf
            <input type="hidden" name="sale_id" value="{{ $sale->id }}"><input type="hidden" name="request_key" value="{{ $requestKey }}">

            <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-border bg-surface px-5 py-4 text-sm">
                <div>
                    <p class="font-semibold">{{ $sale->sale_number }} · {{ $sale->customer?->full_name ?? 'Walk-in customer' }}</p>
                    <p class="text-text-secondary">Sold {{ $sale->completed_at->format('j M Y, H:i') }} · return window closes <span class="font-semibold text-text-primary">{{ $deadline->format('j M, H:i') }}</span></p>
                </div>
                <a href="{{ route('returns.create') }}" class="font-semibold underline underline-offset-4">Choose another sale</a>
            </div>

            <section class="overflow-hidden rounded-2xl border border-border bg-surface" aria-labelledby="items-heading">
                <div class="border-b border-border px-5 py-4"><h2 id="items-heading" class="text-base font-semibold">What is coming back?</h2><p class="text-sm text-text-secondary">Only sellable items go back on sale when the return is completed.</p></div>
                <ul class="divide-y divide-border">
                    @foreach($sale->items as $index => $item)
                        @php $max = $remaining[$item->id]; @endphp
                        <li class="px-5 py-4" :class="items[{{ $index }}].quantity > 0 ? 'bg-selected/40' : ''">
                            <input type="hidden" name="items[{{ $index }}][sale_item_id]" value="{{ $item->id }}">
                            <div class="flex flex-wrap items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <p class="font-semibold break-words">{{ $item->variant->product->name }}</p>
                                    <p class="text-xs text-text-secondary">{{ collect([$item->variant->size?->name, $item->variant->colour?->name, $item->variant->sku])->filter()->join(' · ') }}</p>
                                    <p class="mt-1 text-xs text-text-secondary">Bought {{ $item->quantity }} · {{ $max > 0 ? $max.' can still be returned' : 'Nothing left to return' }}</p>
                                </div>
                                <div class="flex items-center rounded-lg border border-border bg-surface" role="group" aria-label="Quantity to return of {{ $item->variant->product->name }}">
                                    <button type="button" @click="items[{{ $index }}].quantity = Math.max(0, items[{{ $index }}].quantity - 1)" :disabled="items[{{ $index }}].quantity <= 0" class="flex size-10 items-center justify-center rounded-l-lg hover:bg-background disabled:opacity-40" aria-label="One fewer">
                                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M5 12h14"/></svg></button>
                                    <input name="items[{{ $index }}][quantity]" x-model.number="items[{{ $index }}].quantity" inputmode="numeric" @keydown.enter.prevent aria-label="Quantity to return" class="h-10 w-12 border-x border-border text-center text-sm font-semibold tabular-nums">
                                    <button type="button" @click="items[{{ $index }}].quantity = Math.min({{ $max }}, items[{{ $index }}].quantity + 1)" :disabled="items[{{ $index }}].quantity >= {{ $max }}" class="flex size-10 items-center justify-center rounded-r-lg hover:bg-background disabled:opacity-40" aria-label="One more">
                                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg></button>
                                </div>
                            </div>
                            <fieldset class="mt-3" x-show="items[{{ $index }}].quantity > 0">
                                <legend class="mb-2 text-xs font-medium">Condition</legend>
                                <div class="flex flex-wrap gap-2">
                                    @foreach($conditions as $value => $label)
                                        <label :class="items[{{ $index }}].condition === @js($value) ? 'border-text-primary bg-text-primary text-white' : 'border-border bg-surface hover:bg-background'" class="cursor-pointer rounded-lg border px-3 py-1.5 text-sm font-semibold has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-info">
                                            <input type="radio" name="items[{{ $index }}][condition]" value="{{ $value }}" x-model="items[{{ $index }}].condition" class="sr-only">{{ $label }}
                                        </label>
                                    @endforeach
                                </div>
                                <p class="mt-1 text-xs text-text-secondary" x-text="items[{{ $index }}].condition === 'SELLABLE' ? 'Goes back into stock on completion.' : 'Kept out of stock; its original cost stays in the accounts.'"></p>
                            </fieldset>
                        </li>
                    @endforeach
                </ul>
            </section>

            <section class="space-y-5 rounded-2xl border border-border bg-surface p-5" aria-labelledby="reason-heading">
                <h2 id="reason-heading" class="text-base font-semibold">Why is it coming back?</h2>
                <div>
                    <label for="reason" class="mb-2 block text-sm font-medium">Reason</label>
                    <input id="reason" name="reason" x-model="reason" required maxlength="255" placeholder="For example wrong size, faulty zip" class="min-h-11 w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm">
                </div>
                <fieldset>
                    <legend class="mb-2 text-sm font-medium">Proof of purchase</legend>
                    <div class="flex flex-wrap gap-2">
                        @foreach(['SALE_RECORD' => 'Sale in the system', 'RECEIPT' => 'Customer receipt'] as $value => $label)
                            <label :class="proof === @js($value) ? 'border-text-primary bg-text-primary text-white' : 'border-border bg-surface hover:bg-background'" class="cursor-pointer rounded-lg border px-3 py-2 text-sm font-semibold has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-info">
                                <input type="radio" name="proof_type" value="{{ $value }}" x-model="proof" class="sr-only">{{ $label }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>
                <div x-show="proof === 'RECEIPT'" x-cloak><x-input name="proof_reference" label="Receipt number" :value="old('proof_reference')" maxlength="191" /></div>
                <x-input name="notes" label="Notes (optional)" :value="old('notes')" maxlength="5000" />
            </section>

            <div class="flex flex-wrap items-center gap-4">
                <button type="submit" :disabled="blocker !== ''" data-busy-label="Saving…" class="inline-flex min-h-12 items-center rounded-xl bg-primary px-6 text-base font-bold text-text-primary hover:bg-primary-hover disabled:cursor-not-allowed disabled:opacity-50"
                    x-text="count > 0 ? 'Save return of ' + count + (count === 1 ? ' item' : ' items') : 'Save return'"></button>
                <p class="text-sm text-text-secondary" x-text="blocker || 'Next: an approver checks it, then it is completed.'"></p>
            </div>
        </form>
    @endif
</x-layouts.app>
