<x-layouts.app title="Review sale">
    <div class="mx-auto max-w-3xl">
        <a href="{{ route('sales.create') }}" class="inline-flex items-center gap-2 text-sm font-semibold underline underline-offset-4">
            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>Back to cart</a>
        <h1 class="mt-4 text-2xl font-bold tracking-tight sm:text-3xl">Review sale</h1>

        <div class="mt-6 overflow-hidden rounded-2xl border border-border bg-surface">
            {{-- The amount to collect, first and largest --}}
            <div class="flex flex-wrap items-end justify-between gap-4 border-b border-border bg-selected/60 px-6 py-5">
                <div>
                    <p class="text-sm text-text-secondary">Amount to collect</p>
                    <p class="mt-1 text-4xl font-bold tracking-tight tabular-nums">@money($totals['total_amount'])</p>
                </div>
                <dl class="text-right text-sm">
                    <div><dt class="inline text-text-secondary">Customer:</dt> <dd class="inline font-semibold">{{ $customer?->full_name ?? 'Walk-in customer' }}</dd></div>
                    <div class="mt-1"><dt class="inline text-text-secondary">Payment:</dt> <dd class="inline font-semibold">{{ \App\Services\SaleService::PAYMENT_METHODS[$data['payment_method']] }}</dd></div>
                    @if($data['payment_reference'])<div class="mt-1 break-words"><dt class="inline text-text-secondary">Reference:</dt> <dd class="inline">{{ $data['payment_reference'] }}</dd></div>@endif
                </dl>
            </div>
            <ul class="divide-y divide-border">
                @foreach($totals['items'] as $line)
                    @php $variant = $variants[$line['product_variant_id']]; @endphp
                    <li class="flex items-start justify-between gap-4 px-6 py-4 text-sm">
                        <div class="min-w-0">
                            <p class="font-semibold break-words">{{ $variant->product->name }}</p>
                            <p class="text-xs text-text-secondary">{{ collect([$variant->size?->name, $variant->colour?->name, $variant->sku])->filter()->join(' · ') }}</p>
                            <p class="mt-1 text-xs text-text-secondary">{{ $line['quantity'] }} × @money($line['unit_price'])@if($line['discount_amount'] !== '0.00') · discount @money($line['discount_amount'])@endif</p>
                        </div>
                        <p class="shrink-0 font-semibold tabular-nums">@money($line['line_total'])</p>
                    </li>
                @endforeach
            </ul>
            @if($totals['discount_total'] !== '0.00')
                <dl class="space-y-1 border-t border-border px-6 py-4 text-sm text-text-secondary">
                    <div class="flex justify-between"><dt>Subtotal</dt><dd class="tabular-nums">@money($totals['subtotal'])</dd></div>
                    <div class="flex justify-between"><dt>Discount</dt><dd class="tabular-nums">-@money($totals['discount_total'])</dd></div>
                    @if($data['notes'])<div class="pt-1"><dt class="inline">Reason:</dt> <dd class="inline break-words text-text-primary">{{ $data['notes'] }}</dd></div>@endif
                </dl>
            @endif
        </div>

        <form method="POST" action="{{ route('sales.store') }}" class="mt-6 space-y-4" data-busy>@csrf
            @foreach(['request_key', 'customer_id', 'payment_method', 'payment_reference', 'notes'] as $field)<input type="hidden" name="{{ $field }}" value="{{ $data[$field] }}">@endforeach
            @foreach($data['items'] as $index => $item)@foreach($item as $field => $value)<input type="hidden" name="items[{{ $index }}][{{ $field }}]" value="{{ $value }}">@endforeach @endforeach
            <div class="rounded-xl border border-border bg-surface p-4 text-sm" x-data="{ send: @js((bool) old('send_receipt', $autoReceipt && filled($receiptNumber))) }">
                <label class="flex cursor-pointer items-start gap-4">
                    <input type="checkbox" name="send_receipt" value="1" x-model="send" class="mt-0.5 size-5 shrink-0">
                    <span><span class="block font-semibold">Send the receipt on WhatsApp</span><span class="mt-0.5 block text-text-secondary">A short message with a link to this receipt. The sale does not wait for it.</span></span>
                </label>
                <div x-show="send" x-cloak class="mt-3 pl-9">
                    <label for="receipt_whatsapp" class="mb-1 block text-xs font-medium">WhatsApp number</label>
                    <input id="receipt_whatsapp" name="receipt_whatsapp" type="tel" inputmode="tel" maxlength="30" value="{{ old('receipt_whatsapp', $receiptNumber) }}" placeholder="0755 123 456" autocomplete="off" class="min-h-11 w-full max-w-xs rounded-lg border border-border bg-surface px-3 py-2 text-sm">
                </div>
            </div>
            <label class="flex cursor-pointer items-start gap-4 rounded-xl border border-border bg-surface p-4 text-sm has-[:checked]:border-success has-[:checked]:bg-success/5">
                <input type="checkbox" required name="payment_collected" value="1" class="mt-0.5 size-5 shrink-0 accent-[#2E7D32]">
                <span><span class="block font-semibold">I have collected @money($totals['total_amount'])</span><span class="mt-0.5 block text-text-secondary">and checked the items with the customer.</span></span>
            </label>
            <button type="submit" data-busy-label="Completing sale…" class="flex min-h-13 w-full items-center justify-center rounded-xl bg-primary px-5 py-3 text-base font-bold text-text-primary transition-transform duration-150 ease-out hover:bg-primary-hover active:scale-[0.98] motion-reduce:transition-none">Complete sale</button>
            <p class="text-center text-xs text-text-secondary">Completing records the sale permanently. Cancelling a completed sale is not available yet.</p>
        </form>
    </div>
</x-layouts.app>
