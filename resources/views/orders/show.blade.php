@use('App\Enums\OrderStatus')
@php
    $status = $order->status;
    $flow = [OrderStatus::New, OrderStatus::Confirmed, OrderStatus::PaymentReceived, OrderStatus::Preparing, OrderStatus::OutForDelivery, OrderStatus::Delivered];
    $position = array_search($status, $flow, true);
    $cancellable = $order->payment_status === 'UNPAID' && in_array($status, [OrderStatus::New, OrderStatus::Confirmed], true);
    $canSeeSale = $order->sale && auth()->user()->hasPermission('sales.create') && (auth()->user()->hasPermission('sales.view_all') || $order->sale->salesperson_id === auth()->id());
    $next = $order->sale ? match ($status) {
        OrderStatus::PaymentReceived => [OrderStatus::Preparing, 'Start preparing', 'The sale is recorded. Mark the order as being prepared when you start packing it.'],
        OrderStatus::Preparing => [OrderStatus::OutForDelivery, 'Mark out for delivery', 'Mark the order as sent once it leaves the shop.'],
        OrderStatus::OutForDelivery => [OrderStatus::Delivered, 'Mark delivered', 'Mark the order as delivered once the customer has it.'],
        default => null,
    } : null;
    $events = [
        'CREATE_ORDER' => 'Order created', 'CONFIRM_ORDER' => 'Confirmed and stock reserved', 'ORDER_PAYMENT_RECEIVED' => 'Full payment recorded', 'CANCEL_ORDER' => 'Order cancelled', 'CONVERT_ORDER_TO_SALE' => 'Sale completed',
    ];
    $press = 'transition-transform duration-150 ease-out active:scale-[0.97] motion-reduce:transition-none';
@endphp
<x-layouts.app :title="$order->order_number">
    <x-page-header :title="$order->order_number" :back="route('orders.index')" back-label="Orders"
        :description="'Created '.$order->created_at->format('j M Y, H:i').' by '.($order->salesperson?->name ?? 'a former staff member')">
        <x-slot:badge><x-status :value="$status" /></x-slot:badge>
    </x-page-header>

    @if($errors->any())
        <div role="alert" class="mb-6 rounded-xl border border-danger/30 bg-danger/5 p-4 text-sm text-danger">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>
    @endif

    {{-- Where the order is in its journey --}}
    @if($status === OrderStatus::Cancelled)
        <div class="mb-6 rounded-2xl border border-border bg-surface p-5 text-sm"><p class="font-semibold">This order was cancelled.</p><p class="mt-1 text-text-secondary">Any reserved stock was released. The details below are kept for the record.</p></div>
    @else
        <x-workflow :steps="collect($flow)->map(fn ($step) => \App\Support\Status::label($step))->all()" :current="$status === OrderStatus::Delivered ? count($flow) : $position" label="Order progress" />
    @endif

    {{-- The one action that moves this order forward --}}
    @php
        $panel = match (true) {
            $status === OrderStatus::New => ['Confirm the order', 'Confirming reserves the items below for this customer. Physical stock does not change until the sale is completed.'],
            $status === OrderStatus::Confirmed => ['Record the full payment', 'Once the customer has paid in full, record it here. The system does not check mobile-money or bank payments.'],
            $status === OrderStatus::PaymentReceived && ! $order->sale => ['Complete the sale', 'Completing deducts the reserved stock and records the sale of '.\App\Support\Money::format($order->total_amount).'.'],
            $next !== null => [$next[1], $next[2]],
            $status === OrderStatus::Delivered => null,
            default => null,
        };
    @endphp
    @if($panel)
        <section x-data class="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-primary/40 bg-selected/60 p-5" aria-labelledby="next-step-heading">
            <div class="max-w-xl">
                <p class="text-xs font-semibold tracking-wide text-text-secondary uppercase">Next step</p>
                <h2 id="next-step-heading" class="mt-1 text-lg font-bold">{{ $panel[0] }}</h2>
                <p class="mt-1 text-sm text-text-secondary">{{ $panel[1] }}</p>
            </div>
            <div class="flex flex-wrap gap-3">
                @if($status === OrderStatus::New)
                    <x-button class="{{ $press }}" @click="$dispatch('open-modal', 'confirm-order')">Confirm and reserve stock</x-button>
                @elseif($status === OrderStatus::Confirmed)
                    <x-button class="{{ $press }}" @click="$dispatch('open-modal', 'order-payment')">Record full payment</x-button>
                @elseif($status === OrderStatus::PaymentReceived && ! $order->sale)
                    @can('sales.create')<x-button class="{{ $press }}" @click="$dispatch('open-modal', 'convert-order')">Complete sale</x-button>@else<p class="text-sm font-semibold">Ask someone who can record sales to complete it.</p>@endcan
                @elseif($next)
                    <form method="POST" action="{{ route('orders.status', $order) }}" data-busy>@csrf<input type="hidden" name="status" value="{{ $next[0]->value }}"><x-button type="submit" class="{{ $press }}" data-busy-label="Saving…">{{ $next[1] }}</x-button></form>
                @endif
                @if($cancellable)<x-button variant="outline" @click="$dispatch('open-modal', 'cancel-order')">Cancel order</x-button>@endif
            </div>
        </section>
    @elseif($status === OrderStatus::Delivered)
        <div class="mb-6 flex items-center gap-3 rounded-2xl border border-success/30 bg-success/5 p-5 text-sm">
            <svg class="size-5 shrink-0 text-success" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/></svg>
            <p><span class="font-semibold">Delivered.</span> Nothing more to do for this order.</p>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_340px] lg:items-start">
        <section class="overflow-hidden rounded-2xl border border-border bg-surface" aria-labelledby="items-heading">
            <h2 id="items-heading" class="border-b border-border px-5 py-4 text-base font-semibold">Items</h2>
            <ul class="divide-y divide-border">
                @foreach($order->items as $item)
                    @php $reservation = $order->reservations->firstWhere('order_item_id', $item->id); @endphp
                    <li class="flex items-start justify-between gap-4 px-5 py-4 text-sm">
                        <div class="min-w-0">
                            <p class="font-semibold break-words">{{ $item->variant->product->name }}</p>
                            <p class="text-xs text-text-secondary">{{ collect([$item->variant->size?->name, $item->variant->colour?->name, $item->variant->sku])->filter()->join(' · ') }}</p>
                            <p class="mt-1 text-xs text-text-secondary">{{ $item->quantity }} × @money($item->unit_price)@if($item->discount_amount !== '0.00') · discount @money($item->discount_amount)@endif</p>
                            @if($reservation)<x-status :value="$reservation->status" :label="['ACTIVE' => 'Reserved', 'RELEASED' => 'Released', 'COMPLETED' => 'Sold'][$reservation->status] ?? null" class="mt-2" />@elseif($status !== OrderStatus::Cancelled)<x-badge tone="neutral" class="mt-2">Not reserved</x-badge>@endif
                        </div>
                        <p class="shrink-0 font-semibold tabular-nums">@money($item->line_total)</p>
                    </li>
                @endforeach
            </ul>
            <dl class="space-y-1 border-t border-border bg-background/60 px-5 py-4 text-sm">
                @if($order->discount_total !== '0.00')
                    <div class="flex justify-between text-text-secondary"><dt>Subtotal</dt><dd class="tabular-nums">@money($order->subtotal)</dd></div>
                    <div class="flex justify-between text-text-secondary"><dt>Discount</dt><dd class="tabular-nums">-@money($order->discount_total)</dd></div>
                @endif
                <div class="flex items-baseline justify-between"><dt class="font-semibold">Total</dt><dd class="text-xl font-bold tabular-nums">@money($order->total_amount)</dd></div>
            </dl>
        </section>

        <div class="space-y-6">
            <section class="rounded-2xl border border-border bg-surface p-5 text-sm" aria-labelledby="customer-heading">
                <h2 id="customer-heading" class="text-base font-semibold">Customer and delivery</h2>
                <p class="mt-3 font-semibold break-words">{{ $order->customer->full_name }}</p>
                @if($order->customer->phone)<p class="text-text-secondary">{{ \App\Support\Phone::display($order->customer->phone) }}</p>@endif
                <p class="mt-3 break-words">{{ $order->delivery_address ?: 'Collect from the shop (no delivery address).' }}</p>
                @if($order->notes)<p class="mt-3 rounded-lg bg-background p-3 break-words">{{ $order->notes }}</p>@endif
            </section>
            <section class="rounded-2xl border border-border bg-surface p-5 text-sm" aria-labelledby="payment-heading">
                <div class="flex items-center justify-between gap-3"><h2 id="payment-heading" class="text-base font-semibold">Payment</h2><x-status :value="$order->payment_status" /></div>
                @if($order->payment_method)<p class="mt-3">{{ \App\Services\SaleService::PAYMENT_METHODS[$order->payment_method] }}@if($order->payment_reference) · <span class="break-all">{{ $order->payment_reference }}</span>@endif</p>@endif
                <p class="mt-3 text-text-secondary">
                    @if($order->sale)Sale @if($canSeeSale)<a class="font-semibold text-text-primary underline underline-offset-4" href="{{ route('sales.show', $order->sale) }}">{{ $order->sale->sale_number }}</a>@else{{ $order->sale->sale_number }}@endif recorded.
                    @else No sale recorded yet.@endif
                </p>
            </section>
            <section class="rounded-2xl border border-border bg-surface p-5" aria-labelledby="timeline-heading">
                <h2 id="timeline-heading" class="text-base font-semibold">History</h2>
                <ol class="mt-4 space-y-4">
                    @foreach($timeline as $event)
                        @php
                            $values = json_decode($event->new_values ?? '{}', true) ?: [];
                            $title = $event->action === 'ORDER_STATUS_CHANGED' ? 'Marked '.strtolower(\App\Support\Status::label($values['status'] ?? '')) : ($events[$event->action] ?? ucfirst(strtolower(str_replace('_', ' ', $event->action))));
                        @endphp
                        <li class="relative border-l-2 border-border pl-4">
                            <span class="absolute top-1 -left-[5px] size-2 rounded-full bg-primary" aria-hidden="true"></span>
                            <p class="text-sm font-semibold">{{ $title }}</p>
                            <p class="text-xs text-text-secondary">{{ \Illuminate\Support\Carbon::parse($event->created_at)->format('j M Y, H:i') }} · {{ $event->name ?? 'Former staff member' }}</p>
                            @if($values['reason'] ?? null)<p class="mt-1 text-sm break-words">{{ $values['reason'] }}</p>@endif
                        </li>
                    @endforeach
                </ol>
            </section>
        </div>
    </div>

    {{-- Confirmation dialogs --}}
    @if($status === OrderStatus::New)
        <x-modal name="confirm-order" title="Confirm this order?"><p class="mb-5 text-sm">Reserve the items for {{ $order->customer->full_name }}. Physical stock stays the same until the sale is completed.</p><form method="POST" action="{{ route('orders.confirm', $order) }}" data-busy>@csrf<x-button type="submit" data-busy-label="Reserving…">Reserve stock</x-button></form></x-modal>
    @endif
    @if($status === OrderStatus::Confirmed)
        <x-modal name="order-payment" title="Record full payment">
            <form method="POST" action="{{ route('orders.paid', $order) }}" class="space-y-5" x-data="{ method: @js(old('payment_method', '')) }" data-busy>@csrf
                <p class="text-sm">Record that <strong>@money($order->total_amount)</strong> was collected. The system does not check the payment itself.</p>
                <fieldset>
                    <legend class="mb-2 text-sm font-medium">Payment method</legend>
                    <div class="flex flex-wrap gap-2">
                        @foreach(\App\Services\SaleService::PAYMENT_METHODS as $value => $label)
                            <label :class="method === @js($value) ? 'border-text-primary bg-text-primary text-white' : 'border-border bg-surface hover:bg-background'" class="cursor-pointer rounded-lg border px-3 py-2 text-sm font-semibold has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-info">
                                <input type="radio" name="payment_method" value="{{ $value }}" x-model="method" class="sr-only" required>{{ $label }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>
                <div x-show="method && method !== 'CASH'"><x-input name="payment_reference" label="Transaction reference (optional)" maxlength="191" /></div>
                <label class="flex items-start gap-3 text-sm"><input type="checkbox" required class="mt-0.5 size-4"> I confirm the full payment was received.</label>
                <x-button type="submit" x-bind:disabled="! method" data-busy-label="Saving…">Record payment</x-button>
            </form>
        </x-modal>
    @endif
    @if($status === OrderStatus::PaymentReceived && ! $order->sale)
        @can('sales.create')<x-modal name="convert-order" title="Complete the sale?"><p class="mb-5 text-sm">Deduct the reserved stock and record the sale of <strong>@money($order->total_amount)</strong>.</p><form method="POST" action="{{ route('orders.convert', $order) }}" data-busy>@csrf<x-button type="submit" data-busy-label="Completing…">Complete sale</x-button></form></x-modal>@endcan
    @endif
    @if($cancellable)
        <x-modal name="cancel-order" title="Cancel this unpaid order?"><form method="POST" action="{{ route('orders.cancel', $order) }}" class="space-y-5" data-busy>@csrf<p class="text-sm">Any stock reserved for this order goes back on sale.</p><x-input name="reason" label="Reason for cancelling" required maxlength="255" /><x-button type="submit" variant="danger" data-busy-label="Cancelling…">Cancel order and release stock</x-button></form></x-modal>
    @endif
</x-layouts.app>
