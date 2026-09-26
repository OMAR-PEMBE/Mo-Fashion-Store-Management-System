@use('App\Enums\ExchangeStatus')
@php
    $status = $exchange->status;
    $methods = \App\Services\SaleService::PAYMENT_METHODS;
    $user = auth()->user();
    $inWindow = $deadline && now()->lte($deadline);
    $refundDue = $exchange->refund_due !== '0.00';
    $paymentDue = $exchange->amount_due !== '0.00';
    $canComplete = ! $refundDue || ($user->role?->slug === 'administrator' && $user->hasPermission('refunds.approve') && $user->hasPermission('refunds.complete'));
    $conditions = ['SELLABLE' => 'Sellable', 'DAMAGED' => 'Damaged', 'DEFECTIVE' => 'Defective', 'OTHER' => 'Other'];
    $settlement = $paymentDue ? 'Customer pays '.\App\Support\Money::format($exchange->amount_due) : ($refundDue ? 'Refund '.\App\Support\Money::format($exchange->refund_due).' to the customer' : 'Same value, nothing to pay');
    $settled = $paymentDue ? 'Customer paid '.\App\Support\Money::format($exchange->amount_due) : ($refundDue ? \App\Support\Money::format($exchange->refund_due).' refunded to the customer' : 'Same value, nothing paid');
    $done = $status === ExchangeStatus::Completed;
    $chip = 'cursor-pointer rounded-lg border px-3 py-2 text-sm font-semibold has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-info';
@endphp
<x-layouts.app :title="$exchange->exchange_number">
    <x-page-header :title="$exchange->exchange_number" :back="route('exchanges.index')" back-label="Exchanges"
        :description="'Created '.$exchange->created_at->format('j M Y, H:i').' from sale '.$exchange->sale->sale_number">
        <x-slot:badge><x-status :value="$status" :label="$status === ExchangeStatus::Pending ? 'Not completed' : null" /></x-slot:badge>
    </x-page-header>

    @if($errors->any())
        <div role="alert" class="mb-6 rounded-xl border border-danger/30 bg-danger/5 p-4 text-sm text-danger">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>
    @endif

    @if($status === ExchangeStatus::Pending && ! $inWindow)
        <x-next-step tone="blocked" title="The exchange window has closed" :description="'Exchanges must be completed within '.(int) config('exchanges.window_days').' days of the sale. This one can only be cancelled.'">
            <x-button variant="outline" @click="$dispatch('open-modal', 'cancel-exchange')">Cancel exchange</x-button>
        </x-next-step>
    @elseif($status === ExchangeStatus::Pending)
        <x-next-step :title="$canComplete ? 'Complete the exchange' : 'Waiting for an administrator'"
            :description="$canComplete ? $settlement.'. Check the returned items and hand over the replacements, then complete. The window closes '.$deadline->format('j M, H:i').'.' : 'A refund is due, so an administrator must approve and complete this exchange.'">
            @if($canComplete)<x-button @click="$dispatch('open-modal', 'complete-exchange')">Complete exchange</x-button>@endif
            <x-button variant="outline" @click="$dispatch('open-modal', 'cancel-exchange')">Cancel</x-button>
        </x-next-step>
    @elseif($status === ExchangeStatus::Completed)
        <x-next-step tone="done" title="Exchange completed" :description="'Stock was updated'.($exchange->processed_at ? ' on '.$exchange->processed_at->format('j M Y, H:i') : '').'. '.$settled.($exchange->payment_method ? ' by '.$methods[$exchange->payment_method] : '').'.'" />
    @else
        <x-next-step tone="closed" title="Exchange cancelled" :description="$exchange->cancellation_reason ? 'Reason: '.$exchange->cancellation_reason.'. Stock did not change.' : 'Stock did not change.'" />
    @endif

    {{-- The money side at a glance --}}
    <dl class="mb-6 grid gap-3 sm:grid-cols-3">
        <div class="rounded-2xl border border-border bg-surface p-5"><dt class="text-sm text-text-secondary">Coming back</dt><dd class="mt-1 text-xl font-bold tabular-nums">@money($exchange->total_return_value)</dd></div>
        <div class="rounded-2xl border border-border bg-surface p-5"><dt class="text-sm text-text-secondary">Going out</dt><dd class="mt-1 text-xl font-bold tabular-nums">@money($exchange->total_replacement_value)</dd></div>
        <div @class(['rounded-2xl border p-5', 'border-primary/40 bg-selected/60' => $paymentDue || $refundDue, 'border-border bg-surface' => ! $paymentDue && ! $refundDue])>
            <dt class="text-sm text-text-secondary">{{ $paymentDue ? ($done ? 'Customer paid' : 'Customer pays') : ($refundDue ? ($done ? 'Refunded to customer' : 'Refund to customer') : 'Difference') }}</dt>
            <dd class="mt-1 text-xl font-bold tabular-nums">@money($paymentDue ? $exchange->amount_due : $exchange->refund_due)</dd>
        </div>
    </dl>

    <div class="grid gap-6 lg:grid-cols-2 lg:items-start">
        @foreach(['RETURNED' => 'Coming back', 'REPLACEMENT' => 'Going out'] as $type => $heading)
            <section class="overflow-hidden rounded-2xl border border-border bg-surface" aria-labelledby="{{ strtolower($type) }}-heading">
                <h2 id="{{ strtolower($type) }}-heading" class="border-b border-border px-5 py-4 text-base font-semibold">{{ $heading }}</h2>
                <ul class="divide-y divide-border">
                    @foreach($exchange->items->where('item_type', $type) as $item)
                        <li class="flex items-start justify-between gap-4 px-5 py-4 text-sm">
                            <div class="min-w-0">
                                <p class="font-semibold break-words">{{ $item->variant->product->name }}</p>
                                <p class="text-xs text-text-secondary">{{ collect([$item->variant->size?->name, $item->variant->colour?->name, $item->variant->sku])->filter()->join(' · ') }} · × {{ $item->quantity }}</p>
                                @if($item->condition)<x-badge :tone="$item->condition === 'SELLABLE' ? 'info' : 'warning'" class="mt-2">{{ $conditions[$item->condition] ?? $item->condition }}</x-badge>@endif
                                @can('products.view_cost')<p class="mt-1 text-xs text-text-secondary">Unit cost {{ $item->unit_cost !== null ? \App\Support\Money::format($item->unit_cost) : 'recorded on completion' }}</p>@endcan
                            </div>
                            <p class="shrink-0 font-semibold tabular-nums">@money($item->line_total)</p>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach
    </div>

    <section class="mt-6 rounded-2xl border border-border bg-surface p-5 text-sm" aria-labelledby="details-heading">
        <h2 id="details-heading" class="text-base font-semibold">Details</h2>
        <dl class="mt-3 grid gap-3 sm:grid-cols-2">
            <div><dt class="text-text-secondary">Original sale</dt><dd class="font-semibold">@can('sales.create')<a class="underline underline-offset-4" href="{{ route('sales.show', $exchange->sale) }}">{{ $exchange->sale->sale_number }}</a>@else{{ $exchange->sale->sale_number }}@endcan · {{ $exchange->sale->customer?->full_name ?? 'Walk-in customer' }}</dd></div>
            <div><dt class="text-text-secondary">Reason</dt><dd class="break-words">{{ $exchange->reason }}</dd></div>
            @if($exchange->notes)<div><dt class="text-text-secondary">Notes</dt><dd class="break-words">{{ $exchange->notes }}</dd></div>@endif
            @if($exchange->payment_method)<div><dt class="text-text-secondary">Settled by</dt><dd>{{ $methods[$exchange->payment_method] }}@if($exchange->payment_reference) · <span class="break-all">{{ $exchange->payment_reference }}</span>@endif</dd></div>@endif
            @if($exchange->refund)<div><dt class="text-text-secondary">Refund record</dt><dd>@can('refunds.create')<a class="font-semibold underline underline-offset-4" href="{{ route('refunds.show', $exchange->refund) }}">{{ $exchange->refund->refund_number }}</a>@else{{ $exchange->refund->refund_number }}@endcan</dd></div>@endif
        </dl>
    </section>

    @if($status === ExchangeStatus::Pending && $inWindow && $canComplete)
        <x-modal name="complete-exchange" title="Complete the exchange">
            <form method="POST" action="{{ route('exchanges.complete', $exchange) }}" class="space-y-5" x-data="{ method: '' }" data-busy>@csrf
                <p class="text-sm">Check the returned items and hand over the replacements. Only sellable returned items go back into stock. Everything is recorded together.</p>
                @if($paymentDue || $refundDue)
                    <p class="rounded-lg bg-selected/60 p-3 text-sm font-semibold">{{ $settlement }}</p>
                    <fieldset>
                        <legend class="mb-2 text-sm font-medium">{{ $refundDue ? 'How is the money returned?' : 'How did the customer pay?' }}</legend>
                        <div class="flex flex-wrap gap-2">
                            @foreach($methods as $value => $label)
                                <label :class="method === @js($value) ? 'border-text-primary bg-text-primary text-white' : 'border-border bg-surface hover:bg-background'" class="{{ $chip }}"><input type="radio" name="payment_method" value="{{ $value }}" x-model="method" class="sr-only" required>{{ $label }}</label>
                            @endforeach
                        </div>
                    </fieldset>
                    <div x-show="method && method !== 'CASH'"><x-input name="payment_reference" label="Transaction reference (optional)" maxlength="191" /></div>
                    <label class="flex items-start gap-3 text-sm"><input type="checkbox" name="settlement_confirmed" value="1" required class="mt-0.5 size-4"> {{ $refundDue ? 'I approve this refund and confirm the money was returned.' : 'I confirm the customer paid the difference.' }}</label>
                    <x-button type="submit" x-bind:disabled="! method" data-busy-label="Completing…">Complete exchange</x-button>
                @else
                    <p class="text-sm font-semibold">Same value: no payment or refund.</p>
                    <x-button type="submit" data-busy-label="Completing…">Complete exchange</x-button>
                @endif
            </form>
        </x-modal>
    @endif
    @if($status === ExchangeStatus::Pending)
        <x-modal name="cancel-exchange" title="Cancel this exchange?"><form method="POST" action="{{ route('exchanges.cancel', $exchange) }}" class="space-y-5" data-busy>@csrf<p class="text-sm">Stock does not change. The customer keeps what they bought.</p><x-input name="reason" label="Reason for cancelling" required maxlength="255" /><x-button type="submit" variant="danger" data-busy-label="Cancelling…">Cancel exchange</x-button></form></x-modal>
    @endif
</x-layouts.app>
