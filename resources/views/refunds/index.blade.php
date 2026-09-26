@php
    $tabs = ['' => 'All'] + collect(\App\Enums\RefundStatus::cases())->mapWithKeys(fn ($status) => [$status->value => \App\Support\Status::label($status)])->all();
    $filtered = ($filters['q'] ?? null) || ($filters['status'] ?? null);
@endphp
<x-layouts.app title="Refunds">
    <x-page-header title="Refunds" description="Money given back to customers. An administrator approves each refund and records when the money is returned.">
        <x-slot:actions><x-action-link :href="route('refunds.create')">Request refund</x-action-link></x-slot:actions>
    </x-page-header>

    <form method="GET" class="mb-4" role="search">
        @if($filters['status'] ?? null)<input type="hidden" name="status" value="{{ $filters['status'] }}">@endif
        <label for="refund-search" class="sr-only">Search by refund or sale number</label>
        <div class="relative max-w-md">
            <svg class="pointer-events-none absolute top-1/2 left-3.5 size-5 -translate-y-1/2 text-text-secondary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <input id="refund-search" name="q" type="search" value="{{ $filters['q'] ?? '' }}" maxlength="191" placeholder="Refund or sale number" class="min-h-11 w-full rounded-lg border border-border bg-surface py-2 pr-3 pl-11 text-sm">
        </div>
    </form>
    <x-filter-tabs :options="$tabs" :counts="$counts" class="mb-5" />

    <div class="overflow-hidden rounded-2xl border border-border bg-surface">
        @if($refunds->isEmpty())
            <div class="p-10 text-center">
                <p class="font-semibold">{{ $filtered ? 'No refunds match these filters.' : 'No refunds yet.' }}</p>
                <p class="mt-1 text-sm text-text-secondary">{{ $filtered ? 'Try another number or status.' : 'Request a refund from the sale the customer paid for.' }}</p>
            </div>
        @else
            <ul class="divide-y divide-border sm:hidden">
                @foreach($refunds as $refund)
                    <li class="relative px-4 py-4 hover:bg-selected/50">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <a href="{{ route('refunds.show', $refund) }}" class="row-link break-words">{{ $refund->sale->customer?->full_name ?? 'Walk-in customer' }}</a>
                                <p class="mt-0.5 text-xs text-text-secondary">{{ $refund->refund_number }} · {{ $refund->created_at->format('j M, H:i') }}</p>
                            </div>
                            <div class="shrink-0 text-right"><p class="font-semibold tabular-nums">@money($refund->amount)</p><x-status :value="$refund->status" class="mt-1" /></div>
                        </div>
                    </li>
                @endforeach
            </ul>
            <div class="hidden overflow-x-auto sm:block">
                <table class="data-table">
                    <caption class="sr-only">Refunds</caption>
                    <thead><tr><th scope="col">Refund</th><th scope="col">Customer</th><th scope="col">Original sale</th><th scope="col">Status</th><th scope="col" class="num">Amount (TZS)</th></tr></thead>
                    <tbody>
                        @foreach($refunds as $refund)
                            <tr>
                                <th scope="row" class="font-normal"><a href="{{ route('refunds.show', $refund) }}" class="row-link">{{ $refund->refund_number }}</a><span class="block text-xs text-text-secondary">{{ $refund->created_at->format('j M Y, H:i') }}</span></th>
                                <td class="break-words">{{ $refund->sale->customer?->full_name ?? 'Walk-in customer' }}</td>
                                <td>{{ $refund->sale->sale_number }}</td>
                                <td><x-status :value="$refund->status" /></td>
                                <td class="num font-semibold">{{ \App\Support\Money::format($refund->amount, false) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        @if($refunds->hasPages())<div class="border-t border-border p-4">{{ $refunds->links() }}</div>@endif
    </div>
</x-layouts.app>
