@php
    $types = [
        'OPENING_BALANCE' => 'Opening stock', 'PURCHASE' => 'Received from supplier', 'SALE' => 'Sold', 'RESERVATION' => 'Held for an order',
        'RESERVATION_RELEASE' => 'Order hold released', 'RETURN' => 'Returned by customer', 'EXCHANGE_IN' => 'Came back in an exchange',
        'EXCHANGE_OUT' => 'Went out in an exchange', 'DAMAGE' => 'Written off as damaged', 'LOSS' => 'Written off as lost',
        'ADJUSTMENT_IN' => 'Count corrected up', 'ADJUSTMENT_OUT' => 'Count corrected down', 'REVERSAL' => 'Reversal',
    ];
    $sources = ['sale' => 'Sale', 'order' => 'Order', 'purchase' => 'Purchase', 'return' => 'Return', 'exchange' => 'Exchange', 'opening_stock' => 'Opening stock'];
    $variantLabel = collect([$variant->size?->name, $variant->colour?->name, $variant->sku])->filter()->join(' · ');
@endphp
<x-layouts.app title="Stock history">
    <x-page-header :title="$variant->product->name" :back="route('inventory.index')" back-label="Inventory" :description="$variantLabel.' · every stock change, newest first'" />

    @if($variant->inventory)
        <dl class="mb-6 grid grid-cols-3 gap-3">
            <div class="rounded-2xl border border-border bg-surface p-5"><dt class="text-sm text-text-secondary">Ready to sell</dt><dd class="mt-1 text-2xl font-bold tabular-nums">{{ number_format($variant->inventory->available_quantity) }}</dd></div>
            <div class="rounded-2xl border border-border bg-surface p-5"><dt class="text-sm text-text-secondary">In the shop</dt><dd class="mt-1 text-2xl font-bold tabular-nums">{{ number_format($variant->inventory->physical_quantity) }}</dd></div>
            <div class="rounded-2xl border border-border bg-surface p-5"><dt class="text-sm text-text-secondary">Held for orders</dt><dd class="mt-1 text-2xl font-bold tabular-nums">{{ number_format($variant->inventory->reserved_quantity) }}</dd></div>
        </dl>
    @endif

    <div class="overflow-hidden rounded-2xl border border-border bg-surface">
        @if($movements->isEmpty())
            <p class="p-10 text-center text-sm text-text-secondary">No stock changes recorded for this item yet.</p>
        @else
            <ol class="divide-y divide-border">
                @foreach($movements as $movement)
                    @php
                        $reference = $references[$movement->reference_type][$movement->reference_id] ?? null;
                        $change = $movement->quantity_change;
                        $reservedChanged = $movement->reserved_quantity_before !== $movement->reserved_quantity_after;
                    @endphp
                    <li class="flex flex-wrap items-start justify-between gap-4 px-5 py-4 text-sm">
                        <div class="min-w-0">
                            <p class="font-semibold">{{ $types[$movement->movement_type->value] ?? ucfirst(strtolower(str_replace('_', ' ', $movement->movement_type->value))) }}</p>
                            <p class="text-xs text-text-secondary">
                                {{ $movement->created_at->format('j M Y, H:i') }} · {{ $movement->actor->name }} ·
                                @if($reference)<a href="{{ $reference['url'] }}" class="font-semibold text-text-primary underline underline-offset-4">{{ $reference['number'] }}</a>
                                @else{{ $sources[$movement->reference_type] ?? ucfirst(str_replace('_', ' ', (string) $movement->reference_type)) }}@endif
                            </p>
                            @if($movement->reason)<p class="mt-1 text-xs break-words">{{ $movement->reason }}</p>@endif
                            @if($movement->notes)<p class="mt-1 text-xs break-words text-text-secondary">{{ $movement->notes }}</p>@endif
                        </div>
                        <div class="shrink-0 text-right">
                            @if($change !== 0)
                                <p @class(['text-lg font-bold tabular-nums', 'text-success' => $change > 0, 'text-danger' => $change < 0])>{{ $change > 0 ? '+' : '' }}{{ $change }}</p>
                                <p class="text-xs text-text-secondary">in the shop {{ $movement->physical_quantity_before }} → {{ $movement->physical_quantity_after }}</p>
                            @endif
                            @if($reservedChanged)<p class="text-xs text-text-secondary">held {{ $movement->reserved_quantity_before }} → {{ $movement->reserved_quantity_after }}</p>@endif
                        </div>
                    </li>
                @endforeach
            </ol>
        @endif
        @if($movements->hasPages())<div class="border-t border-border p-4">{{ $movements->links() }}</div>@endif
    </div>
</x-layouts.app>
