@use('App\Enums\ReturnStatus')
@php
    $status = $return->status;
    $open = in_array($status, [ReturnStatus::Pending, ReturnStatus::Approved], true);
    $canApprove = auth()->user()->can('returns.approve');
    $conditions = ['SELLABLE' => 'Sellable', 'DAMAGED' => 'Damaged', 'DEFECTIVE' => 'Defective', 'OTHER' => 'Other'];
    $customer = $return->sale->customer;
@endphp
<x-layouts.app :title="$return->return_number">
    <x-page-header :title="$return->return_number" :back="route('returns.index')" back-label="Returns"
        :description="'Requested '.$return->return_date->format('j M Y, H:i').' by '.$return->processor->name">
        <x-slot:badge><x-status :value="$status" /></x-slot:badge>
    </x-page-header>

    @if($errors->any())
        <div role="alert" class="mb-6 rounded-xl border border-danger/30 bg-danger/5 p-4 text-sm text-danger">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>
    @endif

    @if($open || $status === ReturnStatus::Completed)
        <x-workflow :steps="['Requested', 'Approved', 'Completed']" :current="match ($status) { ReturnStatus::Pending => 0, ReturnStatus::Approved => 1, default => 3 }" label="Return progress" />
    @endif

    @if($open && ! $eligible)
        <x-next-step tone="blocked" title="The return window has closed" description="Returns must be finished within {{ (int) config('returns.window_days') }} days of the sale (it closed {{ $deadline?->format('j M, H:i') }}). This return can only be rejected now.">
            @if($canApprove)<x-button variant="outline" @click="$dispatch('open-modal', 'reject-return')">Reject return</x-button>@endif
        </x-next-step>
    @elseif($status === ReturnStatus::Pending)
        <x-next-step :title="$canApprove ? 'Approve the return' : 'Waiting for approval'"
            :description="$canApprove ? 'Check the sale, reason, quantities and conditions below. Approving does not change stock yet.' : 'Someone who can approve returns needs to check it.'">
            @if($canApprove)
                <x-button @click="$dispatch('open-modal', 'approve-return')">Approve return</x-button>
                <x-button variant="outline" @click="$dispatch('open-modal', 'reject-return')">Reject</x-button>
            @endif
        </x-next-step>
    @elseif($status === ReturnStatus::Approved)
        <x-next-step :title="$canApprove ? 'Complete the return' : 'Approved, waiting to be completed'"
            :description="'Confirm the items were received and inspected. Sellable items go back into stock. The window closes '.$deadline?->format('j M, H:i').'.'">
            @if($canApprove)
                <x-button @click="$dispatch('open-modal', 'complete-return')">Complete return</x-button>
                <x-button variant="outline" @click="$dispatch('open-modal', 'reject-return')">Reject</x-button>
            @endif
        </x-next-step>
    @elseif($status === ReturnStatus::Completed)
        <x-next-step tone="done" title="Return completed" description="Sellable items are back in stock. Refunding money is a separate step.">
            @can('refunds.create')<x-action-link :href="route('refunds.create', ['sale_number' => $return->sale->sale_number, 'return_id' => $return->id])">Refund the customer</x-action-link>@endcan
        </x-next-step>
    @else
        <x-next-step tone="closed" :title="'Return '.strtolower(\App\Support\Status::label($status))" :description="$return->rejection_reason ? 'Reason: '.$return->rejection_reason : 'Stock did not change.'" />
    @endif

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_340px] lg:items-start">
        <section class="overflow-hidden rounded-2xl border border-border bg-surface" aria-labelledby="items-heading">
            <h2 id="items-heading" class="border-b border-border px-5 py-4 text-base font-semibold">Items coming back</h2>
            <ul class="divide-y divide-border">
                @foreach($return->items as $item)
                    <li class="flex flex-wrap items-start justify-between gap-3 px-5 py-4 text-sm">
                        <div class="min-w-0">
                            <p class="font-semibold break-words">{{ $item->variant->product->name }}</p>
                            <p class="text-xs text-text-secondary">{{ collect([$item->variant->size?->name, $item->variant->colour?->name, $item->variant->sku])->filter()->join(' · ') }}</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <x-badge :tone="$item->condition === 'SELLABLE' ? 'info' : 'warning'">{{ $conditions[$item->condition] ?? $item->condition }}</x-badge>
                                @if($status === ReturnStatus::Completed)<x-badge :tone="$item->returned_to_stock ? 'success' : 'neutral'">{{ $item->returned_to_stock ? 'Back in stock' : 'Kept out of stock' }}</x-badge>@endif
                            </div>
                        </div>
                        <p class="shrink-0 font-semibold tabular-nums">× {{ $item->quantity }}</p>
                    </li>
                @endforeach
            </ul>
            @can('products.view_cost')
                <p class="border-t border-border bg-background/60 px-5 py-3 text-sm text-text-secondary">{{ $status === ReturnStatus::Completed ? 'Cost reversed from sales' : 'Cost that will be reversed' }}: <span class="font-semibold text-text-primary">@money($return->total_cost_adjustment)</span> <span class="text-xs">(sellable items only)</span></p>
            @endcan
        </section>

        <div class="space-y-6">
            <section class="rounded-2xl border border-border bg-surface p-5 text-sm" aria-labelledby="details-heading">
                <h2 id="details-heading" class="text-base font-semibold">Details</h2>
                <dl class="mt-3 space-y-3">
                    <div><dt class="text-text-secondary">Original sale</dt><dd class="font-semibold">@can('sales.create')<a class="underline underline-offset-4" href="{{ route('sales.show', $return->sale) }}">{{ $return->sale->sale_number }}</a>@else{{ $return->sale->sale_number }}@endcan · {{ $customer?->full_name ?? 'Walk-in customer' }}</dd></div>
                    <div><dt class="text-text-secondary">Reason</dt><dd class="break-words">{{ $return->reason }}</dd></div>
                    <div><dt class="text-text-secondary">Proof</dt><dd class="break-words">{{ $return->proof_type === 'RECEIPT' ? 'Customer receipt '.$return->proof_reference : 'Sale in the system' }}</dd></div>
                    @if($return->notes)<div><dt class="text-text-secondary">Notes</dt><dd class="break-words">{{ $return->notes }}</dd></div>@endif
                    <div><dt class="text-text-secondary">Return window</dt><dd>Closes {{ $deadline?->format('j M Y, H:i') }}</dd></div>
                </dl>
            </section>
            <section class="rounded-2xl border border-border bg-surface p-5" aria-labelledby="history-heading">
                <h2 id="history-heading" class="text-base font-semibold">History</h2>
                <ol class="mt-4 space-y-4 text-sm">
                    @foreach(array_filter(['Requested' => $return->return_date, 'Approved' => $return->approved_at, 'Completed' => $return->completed_at, 'Rejected' => $return->rejected_at]) as $label => $time)
                        <li class="relative border-l-2 border-border pl-4"><span class="absolute top-1 -left-[5px] size-2 rounded-full bg-primary" aria-hidden="true"></span><p class="font-semibold">{{ $label }}</p><p class="text-xs text-text-secondary">{{ $time->format('j M Y, H:i') }}</p></li>
                    @endforeach
                </ol>
            </section>
            @can('refunds.create')
                <section class="rounded-2xl border border-border bg-surface p-5" aria-labelledby="refunds-heading">
                    <h2 id="refunds-heading" class="text-base font-semibold">Refunds for this return</h2>
                    @if($return->refunds->isEmpty())
                        <p class="mt-2 text-sm text-text-secondary">No money refunded for this return yet.</p>
                    @else
                        <ul class="mt-2 divide-y divide-border">
                            @foreach($return->refunds as $refund)
                                <li class="relative flex items-center justify-between gap-3 py-3 text-sm"><div><a href="{{ route('refunds.show', $refund) }}" class="row-link">{{ $refund->refund_number }}</a><p class="text-xs text-text-secondary">@money($refund->amount)</p></div><x-status :value="$refund->status" /></li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endcan
        </div>
    </div>

    @if($canApprove && $open)
        @if($eligible && $status === ReturnStatus::Pending)
            <x-modal name="approve-return" title="Approve this return?"><p class="mb-5 text-sm">You have checked the sale, proof, reason, quantities and conditions. Stock does not change until the return is completed.</p><form method="POST" action="{{ route('returns.approve', $return) }}" data-busy>@csrf<x-button type="submit" data-busy-label="Approving…">Approve return</x-button></form></x-modal>
        @endif
        @if($eligible && $status === ReturnStatus::Approved)
            <x-modal name="complete-return" title="Complete this return?"><p class="mb-5 text-sm">The items have been received and inspected. Sellable items go back into stock once. No money is refunded by this step.</p><form method="POST" action="{{ route('returns.complete', $return) }}" data-busy>@csrf<x-button type="submit" data-busy-label="Completing…">Complete return</x-button></form></x-modal>
        @endif
        <x-modal name="reject-return" title="Reject this return?"><form method="POST" action="{{ route('returns.reject', $return) }}" class="space-y-5" data-busy>@csrf<p class="text-sm">Stock does not change. The customer keeps the items.</p><x-input name="reason" label="Reason for rejecting" required maxlength="255" /><x-button type="submit" variant="danger" data-busy-label="Rejecting…">Reject return</x-button></form></x-modal>
    @endif
</x-layouts.app>
