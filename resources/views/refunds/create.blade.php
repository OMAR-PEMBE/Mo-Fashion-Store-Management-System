<x-layouts.app title="Request refund">
    <x-page-header title="Request refund" :back="route('refunds.index')" back-label="Refunds"
        description="Ask to give money back for a sale. An administrator approves it and records the payment. Stock is handled separately through Returns." />

    @if($errors->any())
        <div role="alert" class="mb-6 rounded-xl border border-danger/30 bg-danger/5 p-4 text-sm text-danger">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>
    @endif

    @if(! $sale)
        <x-sale-picker :action="route('refunds.create')" :recent="$recentSales" :value="request('sale_number', '')"
            window-text="Sales from the last two weeks. Older sales can be found by number." empty-text="No sales in the last two weeks." />
    @else
        @php
            $amounts = $sale->items->mapWithKeys(fn ($item, $index) => [$index => (string) old('items.'.$index.'.amount', '0')]);
            $limitsByIndex = $sale->items->mapWithKeys(fn ($item, $index) => [$index => $limits[$item->id]]);
        @endphp
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-border bg-surface px-5 py-4 text-sm">
            <div>
                <p class="font-semibold">{{ $sale->sale_number }} · {{ $sale->customer?->full_name ?? 'Walk-in customer' }}</p>
                <p class="text-text-secondary">Paid @money($sale->total_amount) by {{ \App\Services\SaleService::PAYMENT_METHODS[$sale->payment_method] }} on {{ $sale->completed_at->format('j M Y') }}</p>
            </div>
            <a href="{{ route('refunds.create') }}" class="font-semibold underline underline-offset-4">Choose another sale</a>
        </div>

        @if($sale->returns->isNotEmpty())
            <form method="GET" class="mb-6 rounded-2xl border border-border bg-surface p-5" x-data>
                <input type="hidden" name="sale_number" value="{{ $sale->sale_number }}">
                <x-select name="return_id" label="Is this refund for a completed return?" x-on:change="$el.form.submit()">
                    <option value="">No, not linked to a return</option>
                    @foreach($sale->returns as $return)<option value="{{ $return->id }}" @selected($returnId === $return->id)>Yes, return {{ $return->return_number }}</option>@endforeach
                </x-select>
                <p class="mt-2 text-xs text-text-secondary">Linking a return limits the refund to the returned items. The page updates when you choose.</p>
                <noscript><x-button type="submit" variant="secondary" class="mt-3">Update limits</x-button></noscript>
            </form>
        @endif

        <form method="POST" action="{{ route('refunds.store') }}" class="space-y-6" data-busy
            x-data="{ amounts: @js($amounts), limits: @js($limitsByIndex), reason: @js(old('reason', '')),
                cents(v) { const n = Number(String(v).trim() || 0); return Number.isFinite(n) && n >= 0 ? Math.round(n * 100) : NaN },
                get total() { return Object.values(this.amounts).reduce((sum, v) => sum + (this.cents(v) || 0), 0) },
                get blocker() {
                    for (const [i, v] of Object.entries(this.amounts)) { if (Number.isNaN(this.cents(v))) return 'Check the amounts: numbers only.'; if (this.cents(v) > this.cents(this.limits[i])) return 'An amount is more than can be refunded for that item.'; }
                    return this.total <= 0 ? 'Enter an amount for at least one item.' : (! this.reason.trim() ? 'Give the reason for the refund.' : '');
                } }">
            @csrf
            <input type="hidden" name="sale_id" value="{{ $sale->id }}"><input type="hidden" name="return_id" value="{{ $returnId }}"><input type="hidden" name="request_key" value="{{ $requestKey }}">
            <section class="overflow-hidden rounded-2xl border border-border bg-surface" aria-labelledby="amounts-heading">
                <div class="border-b border-border px-5 py-4"><h2 id="amounts-heading" class="text-base font-semibold">How much for each item?</h2><p class="text-sm text-text-secondary">Limits already exclude earlier refunds and refunds waiting for payment.</p></div>
                <ul class="divide-y divide-border">
                    @foreach($sale->items as $index => $item)
                        <li class="flex flex-wrap items-end justify-between gap-4 px-5 py-4 text-sm">
                            <input type="hidden" name="items[{{ $index }}][sale_item_id]" value="{{ $item->id }}">
                            <div class="min-w-0">
                                <p class="font-semibold break-words">{{ $item->variant->product->name }}</p>
                                <p class="text-xs text-text-secondary">{{ collect([$item->variant->size?->name, $item->variant->colour?->name, $item->variant->sku])->filter()->join(' · ') }}</p>
                                <p class="mt-1 text-xs text-text-secondary">Paid @money($item->line_total) · @if($limits[$item->id] === '0.00')nothing more can be refunded @else up to <span class="font-semibold text-text-primary">@money($limits[$item->id])</span> can be refunded @endif</p>
                            </div>
                            <div class="flex items-end gap-2">
                                <div>
                                    <label for="amount-{{ $index }}" class="mb-1 block text-xs font-medium">Refund (TZS)</label>
                                    <input id="amount-{{ $index }}" name="items[{{ $index }}][amount]" x-model="amounts[{{ $index }}]" inputmode="decimal" maxlength="16" @keydown.enter.prevent @readonly($limits[$item->id] === '0.00')
                                        class="min-h-10 w-36 rounded-lg border border-border bg-surface px-3 py-2 text-right text-sm tabular-nums read-only:bg-background read-only:text-text-secondary">
                                </div>
                                @if($limits[$item->id] !== '0.00')<button type="button" @click="amounts[{{ $index }}] = limits[{{ $index }}]" class="min-h-10 rounded-lg border border-border px-3 text-xs font-semibold hover:bg-background">Full amount</button>@endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>

            <section class="rounded-2xl border border-border bg-surface p-5">
                <label for="reason" class="mb-2 block text-sm font-medium">Reason for the refund</label>
                <input id="reason" name="reason" x-model="reason" required maxlength="255" placeholder="For example item returned faulty, price agreed later" class="min-h-11 w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm">
            </section>

            <div class="flex flex-wrap items-center gap-4">
                <button type="submit" :disabled="blocker !== ''" data-busy-label="Sending…" class="inline-flex min-h-12 items-center rounded-xl bg-primary px-6 text-base font-bold text-text-primary hover:bg-primary-hover disabled:cursor-not-allowed disabled:opacity-50"
                    x-text="total > 0 ? 'Send for approval · ' + formatMoney((total / 100).toFixed(2)) : 'Send for approval'"></button>
                <p class="text-sm text-text-secondary" x-text="blocker || 'This does not pay the customer yet; an administrator approves it first.'"></p>
            </div>
        </form>
    @endif
</x-layouts.app>
