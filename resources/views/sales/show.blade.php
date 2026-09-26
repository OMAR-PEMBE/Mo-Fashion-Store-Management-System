@php
    $methods = \App\Services\SaleService::PAYMENT_METHODS;
    $user = auth()->user();
    $history = collect()
        ->merge($user->can('returns.create') ? $sale->returns->map(fn ($r) => ['type' => 'Return', 'number' => $r->return_number, 'url' => route('returns.show', $r), 'status' => $r->status, 'amount' => null, 'date' => $r->created_at]) : [])
        ->merge($user->can('refunds.create') ? $sale->refunds->map(fn ($r) => ['type' => 'Refund', 'number' => $r->refund_number, 'url' => route('refunds.show', $r), 'status' => $r->status, 'amount' => $r->amount, 'date' => $r->created_at]) : [])
        ->merge($user->can('exchanges.create') ? $sale->exchanges->map(fn ($e) => ['type' => 'Exchange', 'number' => $e->exchange_number, 'url' => route('exchanges.show', $e), 'status' => $e->status, 'amount' => null, 'date' => $e->created_at]) : [])
        ->sortByDesc('date')->values();
    $afterSales = array_filter([
        'returns.create' => ['Return items', route('returns.create', ['sale_number' => $sale->sale_number]), 'Take items back into stock.'],
        'refunds.create' => ['Refund money', route('refunds.create', ['sale_number' => $sale->sale_number]), 'Give money back; needs administrator approval.'],
        'exchanges.create' => ['Exchange items', route('exchanges.create', ['sale_number' => $sale->sale_number]), 'Swap for other items within the exchange window.'],
    ], fn ($permission) => $user->can($permission), ARRAY_FILTER_USE_KEY);
@endphp
<x-layouts.app :title="$sale->sale_number">
    <x-page-header :title="$sale->sale_number" :back="route('sales.index')" back-label="Sales history" class="print:hidden"
        :description="'Completed '.$sale->sale_date->format('j M Y, H:i').' by '.$sale->salesperson->name">
        <x-slot:badge><x-status :value="$sale->status" /></x-slot:badge>
        <x-slot:actions>
            <x-button variant="outline" x-data @click="window.print()">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9V3h12v6M6 18H4a1 1 0 0 1-1-1v-6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v6a1 1 0 0 1-1 1h-2"/><path d="M6 14h12v7H6z"/></svg>Print receipt</x-button>
            <x-action-link :href="route('sales.create')">New sale</x-action-link>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_340px] lg:items-start print:block">
        {{-- The receipt: what the customer gets, and the only thing printed --}}
        <article class="mx-auto w-full max-w-xl rounded-2xl lg:mx-0 border border-border bg-surface p-6 sm:p-8 print:max-w-none print:border-0 print:p-0" aria-labelledby="receipt-title">
            <header class="border-b border-dashed border-border pb-5 text-center">
                <p id="receipt-title" class="text-lg font-bold tracking-tight">{{ $business['business_name'] }}</p>
                @if($business['business_address'])<p class="mt-1 text-sm whitespace-pre-line text-text-secondary">{{ $business['business_address'] }}</p>@endif
                @if($business['business_phone'])<p class="text-sm text-text-secondary">{{ $business['business_phone'] }}</p>@endif
            </header>
            <dl class="grid grid-cols-2 gap-x-4 gap-y-2 border-b border-dashed border-border py-5 text-sm">
                <dt class="text-text-secondary">Receipt</dt><dd class="text-right font-semibold">{{ $sale->sale_number }}</dd>
                <dt class="text-text-secondary">Date</dt><dd class="text-right">{{ $sale->sale_date->format('j M Y, H:i') }}</dd>
                <dt class="text-text-secondary">Served by</dt><dd class="text-right">{{ $sale->salesperson->name }}</dd>
                <dt class="text-text-secondary">Customer</dt><dd class="text-right break-words">{{ $sale->customer?->full_name ?? 'Walk-in customer' }}</dd>
            </dl>
            <ul class="divide-y divide-border border-b border-dashed border-border">
                @foreach($sale->items as $item)
                    <li class="flex items-start justify-between gap-4 py-3 text-sm">
                        <div class="min-w-0">
                            <p class="font-semibold break-words">{{ $item->variant->product->name }}</p>
                            <p class="text-xs text-text-secondary">{{ collect([$item->variant->size?->name, $item->variant->colour?->name, $item->variant->sku])->filter()->join(' · ') }}</p>
                            <p class="text-xs text-text-secondary">{{ $item->quantity }} × @money($item->unit_price)@if($item->discount_amount !== '0.00') · discount @money($item->discount_amount)@endif</p>
                        </div>
                        <p class="shrink-0 font-semibold tabular-nums">@money($item->line_total)</p>
                    </li>
                @endforeach
            </ul>
            <dl class="space-y-1.5 py-5 text-sm">
                @if($sale->discount_total !== '0.00')
                    <div class="flex justify-between text-text-secondary"><dt>Subtotal</dt><dd class="tabular-nums">@money($sale->subtotal)</dd></div>
                    <div class="flex justify-between text-text-secondary"><dt>Discount</dt><dd class="tabular-nums">-@money($sale->discount_total)</dd></div>
                @endif
                <div class="flex items-baseline justify-between"><dt class="font-semibold">Total paid</dt><dd class="text-2xl font-bold tracking-tight tabular-nums">@money($sale->total_amount)</dd></div>
                <div class="flex justify-between text-text-secondary"><dt>Paid by</dt><dd>{{ $methods[$sale->payment_method] }}@if($sale->payment_reference) · <span class="break-all">{{ $sale->payment_reference }}</span>@endif</dd></div>
            </dl>
            @if($sale->notes)<p class="rounded-lg bg-background p-3 text-sm break-words print:hidden"><span class="text-text-secondary">Note:</span> {{ $sale->notes }}</p>@endif
            @if($business['receipt_footer'])<p class="mt-5 border-t border-dashed border-border pt-5 text-center text-sm whitespace-pre-line text-text-secondary">{{ $business['receipt_footer'] }}</p>@endif
        </article>

        <div class="space-y-6 print:hidden">
            @if($afterSales)
                <section class="rounded-2xl border border-border bg-surface p-5" aria-labelledby="after-sales-heading">
                    <h2 id="after-sales-heading" class="text-base font-semibold">After the sale</h2>
                    <div class="mt-4 space-y-2">
                        @foreach($afterSales as [$label, $url, $hint])
                            <a href="{{ $url }}" class="flex items-center justify-between gap-3 rounded-xl border border-border px-4 py-3 text-sm hover:bg-selected/60">
                                <span><span class="block font-semibold">{{ $label }}</span><span class="block text-xs text-text-secondary">{{ $hint }}</span></span>
                                <svg class="size-4 shrink-0 text-text-secondary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                            </a>
                        @endforeach
                    </div>
                    <h3 class="mt-6 text-sm font-semibold">History</h3>
                    @if($history->isEmpty())
                        <p class="mt-2 text-sm text-text-secondary">No returns, refunds or exchanges on this sale.</p>
                    @else
                        <ul class="mt-2 divide-y divide-border">
                            @foreach($history as $entry)
                                <li class="relative flex items-center justify-between gap-3 py-3 text-sm">
                                    <div class="min-w-0"><a href="{{ $entry['url'] }}" class="row-link">{{ $entry['type'] }} {{ $entry['number'] }}</a><p class="text-xs text-text-secondary">{{ $entry['date']->format('j M Y, H:i') }}@if($entry['amount']) · @money($entry['amount'])@endif</p></div>
                                    <x-status :value="$entry['status']" />
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endif
            @can('products.view_cost')
                <section class="rounded-2xl border border-border bg-surface p-5 text-sm" aria-labelledby="profit-heading">
                    <h2 id="profit-heading" class="text-base font-semibold">Profit on this sale</h2>
                    <dl class="mt-3 space-y-1.5">
                        <div class="flex justify-between"><dt class="text-text-secondary">Cost of goods</dt><dd class="tabular-nums">@money($sale->total_cogs)</dd></div>
                        <div class="flex justify-between font-semibold"><dt>Gross profit</dt><dd class="tabular-nums">@money($sale->gross_profit)</dd></div>
                    </dl>
                    <ul class="mt-3 space-y-1 border-t border-border pt-3 text-xs text-text-secondary">
                        @foreach($sale->items as $item)<li class="flex justify-between gap-3"><span class="min-w-0 truncate">{{ $item->variant->product->name }}</span><span class="shrink-0 tabular-nums">unit cost @money($item->unit_cost)</span></li>@endforeach
                    </ul>
                    <p class="mt-3 text-xs text-text-secondary">Before any returns or refunds. Staff without cost access do not see this.</p>
                </section>
            @endcan
        </div>
    </div>
</x-layouts.app>
