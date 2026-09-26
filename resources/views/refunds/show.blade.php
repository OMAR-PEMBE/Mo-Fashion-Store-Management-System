@use('App\Enums\RefundStatus')
@php
    $status = $refund->status;
    $methods = \App\Services\SaleService::PAYMENT_METHODS;
    $user = auth()->user();
    $isAdmin = $user->role?->slug === 'administrator';
    $canApprove = $isAdmin && $user->can('refunds.approve');
    $canComplete = $isAdmin && $user->can('refunds.complete');
    $open = in_array($status, [RefundStatus::Pending, RefundStatus::Approved], true);
    $chip = 'cursor-pointer rounded-lg border px-3 py-2 text-sm font-semibold has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-info';
@endphp
<x-layouts.app :title="$refund->refund_number">
    <x-page-header :title="$refund->refund_number" :back="route('refunds.index')" back-label="Refunds"
        :description="'Requested '.$refund->created_at->format('j M Y, H:i').' by '.$refund->requester->name">
        <x-slot:badge><x-status :value="$status" /></x-slot:badge>
    </x-page-header>

    @if($errors->any())
        <div role="alert" class="mb-6 rounded-xl border border-danger/30 bg-danger/5 p-4 text-sm text-danger">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>
    @endif

    @if($open || $status === RefundStatus::Completed)
        <x-workflow :steps="['Requested', 'Approved', 'Money returned']" :current="match ($status) { RefundStatus::Pending => 0, RefundStatus::Approved => 1, default => 3 }" label="Refund progress" />
    @endif

    @if($status === RefundStatus::Pending)
        <x-next-step :title="$canApprove ? 'Approve the refund' : 'Waiting for an administrator'"
            :description="$canApprove ? 'Check the amount and reason, then choose how the money goes back. Approving holds the amount against this sale.' : 'An administrator approves refunds and chooses how the money is returned.'">
            @if($canApprove)
                <x-button @click="$dispatch('open-modal', 'approve-refund')">Approve refund</x-button>
                <x-button variant="outline" @click="$dispatch('open-modal', 'close-refund')">Reject or cancel</x-button>
            @endif
        </x-next-step>
    @elseif($status === RefundStatus::Approved)
        <x-next-step :title="$canComplete ? 'Give the money back' : 'Approved, waiting for payment'"
            :description="'Return '.\App\Support\Money::format($refund->amount).' by '.$methods[$refund->refund_method].', then record it here.'">
            @if($canComplete)<x-button @click="$dispatch('open-modal', 'complete-refund')">Record money returned</x-button>@endif
            @if($canApprove)<x-button variant="outline" @click="$dispatch('open-modal', 'close-refund')">Reject or cancel</x-button>@endif
        </x-next-step>
    @elseif($status === RefundStatus::Completed)
        <x-next-step tone="done" title="Money returned" :description="\App\Support\Money::format($refund->amount).' returned by '.$methods[$refund->refund_method].($refund->processed_at ? ' on '.$refund->processed_at->format('j M Y, H:i') : '').'.'" />
    @else
        <x-next-step tone="closed" :title="'Refund '.strtolower(\App\Support\Status::label($status))" :description="$refund->closure_reason ? 'Reason: '.$refund->closure_reason.'. No money was returned.' : 'No money was returned.'" />
    @endif

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_340px] lg:items-start">
        <section class="overflow-hidden rounded-2xl border border-border bg-surface" aria-labelledby="items-heading">
            <div class="flex items-baseline justify-between gap-3 border-b border-border px-5 py-4"><h2 id="items-heading" class="text-base font-semibold">Refund</h2><p class="text-2xl font-bold tabular-nums">@money($refund->amount)</p></div>
            <ul class="divide-y divide-border">
                @foreach($refund->items as $item)
                    @php $variant = $item->saleItem->variant; @endphp
                    <li class="flex items-start justify-between gap-4 px-5 py-4 text-sm">
                        <div class="min-w-0">
                            <p class="font-semibold break-words">{{ $variant->product->name }}</p>
                            <p class="text-xs text-text-secondary">{{ collect([$variant->size?->name, $variant->colour?->name, $variant->sku])->filter()->join(' · ') }}</p>
                            @if($open)<p class="mt-1 text-xs text-text-secondary">Up to @money($limits[$item->sale_item_id]) could be refunded besides this request</p>@endif
                        </div>
                        <p class="shrink-0 font-semibold tabular-nums">@money($item->amount)</p>
                    </li>
                @endforeach
            </ul>
        </section>

        <div class="space-y-6">
            <section class="rounded-2xl border border-border bg-surface p-5 text-sm" aria-labelledby="details-heading">
                <h2 id="details-heading" class="text-base font-semibold">Details</h2>
                <dl class="mt-3 space-y-3">
                    <div><dt class="text-text-secondary">Original sale</dt><dd class="font-semibold">@can('sales.create')<a class="underline underline-offset-4" href="{{ route('sales.show', $refund->sale) }}">{{ $refund->sale->sale_number }}</a>@else{{ $refund->sale->sale_number }}@endcan · paid by {{ $methods[$refund->sale->payment_method] }}</dd></div>
                    <div><dt class="text-text-secondary">Customer</dt><dd>{{ $refund->sale->customer?->full_name ?? 'Walk-in customer' }}</dd></div>
                    <div><dt class="text-text-secondary">Reason</dt><dd class="break-words">{{ $refund->reason }}</dd></div>
                    @if($refund->saleReturn)<div><dt class="text-text-secondary">For return</dt><dd><a class="font-semibold underline underline-offset-4" href="{{ route('returns.show', $refund->saleReturn) }}">{{ $refund->saleReturn->return_number }}</a></dd></div>@endif
                    <div><dt class="text-text-secondary">Refund method</dt><dd>{{ $refund->refund_method ? $methods[$refund->refund_method] : 'Chosen by the administrator when approving' }}@if($refund->payment_reference) · <span class="break-all">{{ $refund->payment_reference }}</span>@endif</dd></div>
                </dl>
            </section>
            <section class="rounded-2xl border border-border bg-surface p-5" aria-labelledby="history-heading">
                <h2 id="history-heading" class="text-base font-semibold">History</h2>
                <ol class="mt-4 space-y-4 text-sm">
                    @foreach(array_filter(['Requested' => $refund->created_at, 'Approved' => $refund->approved_at, 'Money returned' => $refund->processed_at, \App\Support\Status::label($status) => $open ? null : ($status === RefundStatus::Completed ? null : $refund->closed_at)]) as $label => $time)
                        <li class="relative border-l-2 border-border pl-4"><span class="absolute top-1 -left-[5px] size-2 rounded-full bg-primary" aria-hidden="true"></span><p class="font-semibold">{{ $label }}</p><p class="text-xs text-text-secondary">{{ $time->format('j M Y, H:i') }}</p></li>
                    @endforeach
                </ol>
            </section>
        </div>
    </div>

    @if($canApprove && $status === RefundStatus::Pending)
        <x-modal name="approve-refund" title="Approve refund">
            <form method="POST" action="{{ route('refunds.approve', $refund) }}" class="space-y-5" x-data="{ method: '' }" data-busy>@csrf
                <p class="text-sm">Approve <strong>@money($refund->amount)</strong>. The customer originally paid by {{ $methods[$refund->sale->payment_method] }}; you may choose a different way to give the money back.</p>
                <fieldset>
                    <legend class="mb-2 text-sm font-medium">Refund method</legend>
                    <div class="flex flex-wrap gap-2">
                        @foreach($methods as $value => $label)
                            <label :class="method === @js($value) ? 'border-text-primary bg-text-primary text-white' : 'border-border bg-surface hover:bg-background'" class="{{ $chip }}"><input type="radio" name="refund_method" value="{{ $value }}" x-model="method" class="sr-only" required>{{ $label }}</label>
                        @endforeach
                    </div>
                </fieldset>
                <x-button type="submit" x-bind:disabled="! method" data-busy-label="Approving…">Approve refund</x-button>
            </form>
        </x-modal>
    @endif
    @if($canApprove && $open)
        <x-modal name="close-refund" title="Close without paying">
            <form method="POST" action="{{ route('refunds.close', $refund) }}" class="space-y-5" x-data="{ action: 'REJECTED' }" data-busy>@csrf
                <p class="text-sm">Use this only if no money has been returned. Any approved amount is released.</p>
                <fieldset>
                    <legend class="mb-2 text-sm font-medium">What happened?</legend>
                    <div class="flex flex-wrap gap-2">
                        @foreach(['REJECTED' => 'Reject: not allowed', 'CANCELLED' => 'Cancel: no longer needed'] as $value => $label)
                            <label :class="action === @js($value) ? 'border-text-primary bg-text-primary text-white' : 'border-border bg-surface hover:bg-background'" class="{{ $chip }}"><input type="radio" name="status" value="{{ $value }}" x-model="action" class="sr-only">{{ $label }}</label>
                        @endforeach
                    </div>
                </fieldset>
                <x-input name="reason" label="Reason" required maxlength="255" />
                <x-button type="submit" variant="danger" data-busy-label="Closing…">Close refund</x-button>
            </form>
        </x-modal>
    @endif
    @if($canComplete && $status === RefundStatus::Approved)
        <x-modal name="complete-refund" title="Record money returned">
            <form method="POST" action="{{ route('refunds.complete', $refund) }}" class="space-y-5" data-busy>@csrf
                <p class="text-sm">Give <strong>@money($refund->amount)</strong> back by {{ $methods[$refund->refund_method] }} first, then record it here.</p>
                @if($refund->refund_method !== 'CASH')<x-input name="payment_reference" label="Transaction reference (optional)" maxlength="191" />@endif
                <label class="flex items-start gap-3 text-sm"><input type="checkbox" name="payment_returned" value="1" required class="mt-0.5 size-4"> I confirm the money was returned by {{ $methods[$refund->refund_method] }}.</label>
                <x-button type="submit" data-busy-label="Saving…">Record money returned</x-button>
            </form>
        </x-modal>
    @endif
</x-layouts.app>
