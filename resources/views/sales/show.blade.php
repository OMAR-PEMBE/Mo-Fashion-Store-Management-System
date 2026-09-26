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
        <x-receipt :sale="$sale" :business="$business" />

        <div class="space-y-6 print:hidden">
            <section class="rounded-2xl border border-border bg-surface p-5 text-sm" aria-labelledby="whatsapp-heading">
                <h2 id="whatsapp-heading" class="text-base font-semibold">Receipt on WhatsApp</h2>
                @if($messages->isNotEmpty())
                    <ul class="mt-3 space-y-2">
                        @foreach($messages as $message)
                            <li class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="font-medium">{{ $message->recipientDisplay() }}</p>
                                    <p class="text-xs text-text-secondary">{{ ($message->sent_at ?? $message->failed_at ?? $message->created_at)->format('j M Y, H:i') }}</p>
                                    @if($message->status === 'failed' && $message->error)<p class="mt-0.5 text-xs break-words text-danger">{{ \Illuminate\Support\Str::limit($message->error, 160) }}</p>@endif
                                </div>
                                <x-badge :tone="$message->statusTone()">{{ $message->statusLabel() }}</x-badge>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="mt-1 text-text-secondary">Not sent yet.</p>
                @endif
                <form method="POST" action="{{ route('sales.receipt.whatsapp', $sale) }}" class="mt-4 flex flex-wrap items-end gap-2" data-busy>
                    @csrf
                    <div class="min-w-0 flex-1">
                        <label for="receipt_whatsapp" class="mb-1 block text-xs font-medium">WhatsApp number</label>
                        <input id="receipt_whatsapp" name="receipt_whatsapp" type="tel" inputmode="tel" maxlength="30" required value="{{ old('receipt_whatsapp', $receiptNumber) }}" placeholder="0755 123 456" autocomplete="off"
                            class="min-h-11 w-full rounded-lg border bg-surface px-3 py-2 text-sm {{ $errors->has('receipt_whatsapp') ? 'border-danger' : 'border-border' }}" @error('receipt_whatsapp') aria-invalid="true" aria-describedby="receipt-whatsapp-error" @enderror>
                    </div>
                    <x-button type="submit" variant="outline" data-busy-label="Sending…">{{ $messages->isEmpty() ? 'Send' : 'Send again' }}</x-button>
                    @error('receipt_whatsapp')<p id="receipt-whatsapp-error" class="w-full text-xs text-danger">{{ $message }}</p>@enderror
                </form>
                @if(config('messaging.whatsapp.driver') === 'log')<p class="mt-3 text-xs text-text-secondary">Test mode: messages are written to the system log, not sent. They go out for real once WhatsApp is connected.</p>@endif
            </section>
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
