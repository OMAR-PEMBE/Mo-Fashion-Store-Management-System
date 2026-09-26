@use('App\Enums\PurchaseStatus')
@php
    $status = $purchase->status;
    $units = $purchase->items->sum('quantity');
    $canReceive = auth()->user()->can('inventory.adjust');
@endphp
<x-layouts.app :title="$purchase->purchase_number">
    <x-page-header :title="$purchase->purchase_number" :back="route('purchases.index')" back-label="Purchases"
        :description="$purchase->supplier->name.' · '.$purchase->purchase_date->format('j M Y').' · recorded by '.$purchase->creator->name">
        <x-slot:badge><x-badge :tone="match ($status->value) { 'DRAFT' => 'warning', 'CONFIRMED' => 'success', default => 'neutral' }">{{ match ($status->value) { 'DRAFT' => 'Draft, not received', 'CONFIRMED' => 'Received', default => 'Cancelled' } }}</x-badge></x-slot:badge>
    </x-page-header>

    @if($errors->any())
        <div role="alert" class="mb-6 rounded-xl border border-danger/30 bg-danger/5 p-4 text-sm text-danger">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>
    @endif

    @if($status === PurchaseStatus::Draft)
        <x-next-step :title="$canReceive ? 'Check the goods, then receive them' : 'Waiting to be received'"
            :description="'Receiving adds '.number_format($units).' units to stock and updates average costs. It cannot be undone here, so check quantities and costs first.'">
            @if($canReceive)<x-button @click="$dispatch('open-modal', 'confirm-purchase')">Receive stock</x-button>@endif
            <a href="{{ route('purchases.edit', $purchase) }}" class="inline-flex min-h-11 items-center rounded-lg border border-border bg-surface px-4 text-sm font-semibold hover:bg-background">Edit draft</a>
            <x-button variant="outline" @click="$dispatch('open-modal', 'cancel-purchase')">Cancel draft</x-button>
        </x-next-step>
    @elseif($status === PurchaseStatus::Confirmed)
        <x-next-step tone="done" title="Stock received" :description="number_format($units).' units added to stock'.($purchase->confirmed_at ? ' on '.$purchase->confirmed_at->format('j M Y, H:i').' by '.$purchase->confirmer->name : '').'.'" />
    @else
        <x-next-step tone="closed" title="Draft cancelled" description="Nothing was added to stock. It stays here for the record." />
    @endif

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px] lg:items-start">
        <section class="overflow-hidden rounded-2xl border border-border bg-surface" aria-labelledby="items-heading">
            <h2 id="items-heading" class="border-b border-border px-5 py-4 text-base font-semibold">What was bought</h2>
            <ul class="divide-y divide-border">
                @foreach($purchase->items as $item)
                    <li class="flex items-start justify-between gap-4 px-5 py-4 text-sm">
                        <div class="min-w-0">
                            <p class="font-semibold break-words">{{ $item->variant->product->name }}</p>
                            <p class="text-xs text-text-secondary">{{ collect([$item->variant->size?->name, $item->variant->colour?->name, $item->variant->sku])->filter()->join(' · ') }}</p>
                            <p class="mt-1 text-xs text-text-secondary">{{ number_format($item->quantity) }} × @money($item->unit_cost) each</p>
                        </div>
                        <p class="shrink-0 font-semibold tabular-nums">@money($item->line_total)</p>
                    </li>
                @endforeach
            </ul>
            <div class="flex items-baseline justify-between border-t border-border bg-background/60 px-5 py-4 text-sm"><span class="font-semibold">Total cost · {{ number_format($units) }} units</span><span class="text-xl font-bold tabular-nums">@money($purchase->total_amount)</span></div>
        </section>
        <section class="rounded-2xl border border-border bg-surface p-5 text-sm" aria-labelledby="details-heading">
            <h2 id="details-heading" class="text-base font-semibold">Details</h2>
            <dl class="mt-3 space-y-3">
                <div><dt class="text-text-secondary">Supplier</dt><dd class="font-semibold break-words"><a href="{{ route('suppliers.show', $purchase->supplier) }}" class="underline underline-offset-4">{{ $purchase->supplier->name }}</a></dd></div>
                <div><dt class="text-text-secondary">Supplier's invoice</dt><dd class="break-words">{{ $purchase->supplier_invoice_number ?? 'Not recorded' }}</dd></div>
                <div><dt class="text-text-secondary">Supplier paid?</dt><dd><x-status :value="$purchase->payment_status" /> <span class="text-xs text-text-secondary">(for your records only)</span></dd></div>
                @if($purchase->notes)<div><dt class="text-text-secondary">Notes</dt><dd class="whitespace-pre-line break-words">{{ $purchase->notes }}</dd></div>@endif
            </dl>
        </section>
    </div>

    @if($status === PurchaseStatus::Draft)
        @if($canReceive)
            <x-modal name="confirm-purchase" title="Receive this stock?"><p class="mb-5 text-sm">Add <strong>{{ number_format($units) }} units</strong> costing <strong>@money($purchase->total_amount)</strong> to stock and update average costs. This cannot be undone here.</p><form method="POST" action="{{ route('purchases.confirm', $purchase) }}" data-busy>@csrf<input type="hidden" name="revision" value="{{ $purchase->revision }}"><x-button type="submit" data-busy-label="Receiving…">Receive stock</x-button></form></x-modal>
        @endif
        <x-modal name="cancel-purchase" title="Cancel this draft?"><p class="mb-5 text-sm">Nothing is added to stock. The draft stays in the purchase history.</p><form method="POST" action="{{ route('purchases.cancel', $purchase) }}" data-busy>@csrf<input type="hidden" name="revision" value="{{ $purchase->revision }}"><x-button type="submit" variant="danger" data-busy-label="Cancelling…">Cancel draft</x-button></form></x-modal>
    @endif
</x-layouts.app>
