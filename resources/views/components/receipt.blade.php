@props(['sale', 'business', 'internal' => true])
{{-- The receipt: what the customer gets. Shared by the sale page (printed) and the link sent to the customer.
     $internal shows staff-only notes, such as a discount reason. --}}
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
        <div class="flex justify-between text-text-secondary"><dt>Paid by</dt><dd>{{ \App\Services\SaleService::PAYMENT_METHODS[$sale->payment_method] ?? $sale->payment_method }}@if($sale->payment_reference) · <span class="break-all">{{ $sale->payment_reference }}</span>@endif</dd></div>
    </dl>
    @if($internal && $sale->notes)<p class="rounded-lg bg-background p-3 text-sm break-words print:hidden"><span class="text-text-secondary">Note:</span> {{ $sale->notes }}</p>@endif
    @if($business['receipt_footer'])<p class="mt-5 border-t border-dashed border-border pt-5 text-center text-sm whitespace-pre-line text-text-secondary">{{ $business['receipt_footer'] }}</p>@endif
</article>
