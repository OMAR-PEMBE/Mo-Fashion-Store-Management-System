@php
    $tabs = ['' => 'All'] + collect(\App\Enums\OrderStatus::cases())->mapWithKeys(fn ($status) => [$status->value => \App\Support\Status::label($status)])->all();
@endphp
<x-layouts.app title="Orders">
    <x-page-header title="Orders" description="Orders placed for later collection or delivery. Confirming an order reserves its stock.">
        <x-slot:actions><x-action-link :href="route('orders.create')">New order</x-action-link></x-slot:actions>
    </x-page-header>

    <form method="GET" class="mb-4" role="search">
        @if($filters['status'] ?? null)<input type="hidden" name="status" value="{{ $filters['status'] }}">@endif
        <label for="order-search" class="sr-only">Search orders by number or customer</label>
        <div class="relative max-w-md">
            <svg class="pointer-events-none absolute top-1/2 left-3.5 size-5 -translate-y-1/2 text-text-secondary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <input id="order-search" name="q" type="search" value="{{ $filters['q'] ?? '' }}" maxlength="191" placeholder="Order number or customer name" class="min-h-11 w-full rounded-lg border border-border bg-surface py-2 pr-3 pl-11 text-sm">
        </div>
    </form>
    <x-filter-tabs :options="$tabs" :counts="$counts" class="mb-5" />

    <div class="overflow-hidden rounded-2xl border border-border bg-surface">
        @if($orders->isEmpty())
            <div class="p-10 text-center">
                <p class="font-semibold">{{ ($filters['q'] ?? null) || ($filters['status'] ?? null) ? 'No orders match these filters.' : 'No orders yet.' }}</p>
                <p class="mt-1 text-sm text-text-secondary">{{ ($filters['q'] ?? null) || ($filters['status'] ?? null) ? 'Try another name or status.' : 'Create an order when a customer wants items set aside or delivered.' }}</p>
            </div>
        @else
            {{-- Phones: one tappable card per order --}}
            <ul class="divide-y divide-border sm:hidden">
                @foreach($orders as $order)
                    <li class="relative px-4 py-4 hover:bg-selected/50">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <a href="{{ route('orders.show', $order) }}" class="row-link break-words">{{ $order->customer->full_name }}</a>
                                <p class="mt-0.5 text-xs text-text-secondary">{{ $order->order_number }} · {{ $order->created_at->format('j M, H:i') }}</p>
                            </div>
                            <p class="shrink-0 font-semibold tabular-nums">@money($order->total_amount)</p>
                        </div>
                        <div class="mt-2 flex flex-wrap gap-2"><x-status :value="$order->status" /><x-status :value="$order->payment_status" /></div>
                    </li>
                @endforeach
            </ul>
            <div class="hidden overflow-x-auto sm:block">
                <table class="data-table">
                    <caption class="sr-only">Customer orders</caption>
                    <thead><tr><th scope="col">Order</th><th scope="col">Customer</th><th scope="col">Items</th><th scope="col">Status</th><th scope="col">Payment</th><th scope="col" class="num">Total (TZS)</th></tr></thead>
                    <tbody>
                        @foreach($orders as $order)
                            <tr>
                                <th scope="row" class="font-normal"><a href="{{ route('orders.show', $order) }}" class="row-link">{{ $order->order_number }}</a><span class="block text-xs text-text-secondary">{{ $order->created_at->format('j M Y, H:i') }}</span></th>
                                <td class="break-words">{{ $order->customer->full_name }}</td>
                                <td class="tabular-nums">{{ $order->items_count }}</td>
                                <td><x-status :value="$order->status" /></td>
                                <td><x-status :value="$order->payment_status" /></td>
                                <td class="num font-semibold">{{ \App\Support\Money::format($order->total_amount, false) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        @if($orders->hasPages())<div class="border-t border-border p-4">{{ $orders->links() }}</div>@endif
    </div>
</x-layouts.app>
