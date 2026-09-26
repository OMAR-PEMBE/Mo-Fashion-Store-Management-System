@php
    $tabs = ['' => 'All'] + collect(\App\Enums\ExchangeStatus::cases())->mapWithKeys(fn ($status) => [$status->value => $status->value === 'PENDING' ? 'Not completed' : \App\Support\Status::label($status)])->all();
    $filtered = ($filters['q'] ?? null) || ($filters['status'] ?? null);
    $difference = fn ($exchange) => $exchange->amount_due !== '0.00' ? 'Customer pays '.\App\Support\Money::format($exchange->amount_due)
        : ($exchange->refund_due !== '0.00' ? 'Refund '.\App\Support\Money::format($exchange->refund_due) : 'Same value');
@endphp
<x-layouts.app title="Exchanges">
    <x-page-header title="Exchanges" description="Swap items from a recent sale for other sizes, colours or products. Price differences are settled when the exchange is completed.">
        <x-slot:actions><x-action-link :href="route('exchanges.create')">New exchange</x-action-link></x-slot:actions>
    </x-page-header>

    <form method="GET" class="mb-4" role="search">
        @if($filters['status'] ?? null)<input type="hidden" name="status" value="{{ $filters['status'] }}">@endif
        <label for="exchange-search" class="sr-only">Search by exchange or sale number</label>
        <div class="relative max-w-md">
            <svg class="pointer-events-none absolute top-1/2 left-3.5 size-5 -translate-y-1/2 text-text-secondary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <input id="exchange-search" name="q" type="search" value="{{ $filters['q'] ?? '' }}" maxlength="191" placeholder="Exchange or sale number" class="min-h-11 w-full rounded-lg border border-border bg-surface py-2 pr-3 pl-11 text-sm">
        </div>
    </form>
    <x-filter-tabs :options="$tabs" :counts="$counts" class="mb-5" />

    <div class="overflow-hidden rounded-2xl border border-border bg-surface">
        @if($exchanges->isEmpty())
            <div class="p-10 text-center">
                <p class="font-semibold">{{ $filtered ? 'No exchanges match these filters.' : 'No exchanges yet.' }}</p>
                <p class="mt-1 text-sm text-text-secondary">{{ $filtered ? 'Try another number or status.' : 'Start an exchange from a sale made in the last three days.' }}</p>
            </div>
        @else
            <ul class="divide-y divide-border sm:hidden">
                @foreach($exchanges as $exchange)
                    <li class="relative px-4 py-4 hover:bg-selected/50">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <a href="{{ route('exchanges.show', $exchange) }}" class="row-link break-words">{{ $exchange->sale->customer?->full_name ?? 'Walk-in customer' }}</a>
                                <p class="mt-0.5 text-xs text-text-secondary">{{ $exchange->exchange_number }} · {{ $exchange->created_at->format('j M, H:i') }}</p>
                                <p class="text-xs text-text-secondary">{{ $difference($exchange) }}</p>
                            </div>
                            <x-status :value="$exchange->status" :label="$exchange->status->value === 'PENDING' ? 'Not completed' : null" class="shrink-0" />
                        </div>
                    </li>
                @endforeach
            </ul>
            <div class="hidden overflow-x-auto sm:block">
                <table class="data-table">
                    <caption class="sr-only">Exchanges</caption>
                    <thead><tr><th scope="col">Exchange</th><th scope="col">Customer</th><th scope="col">Original sale</th><th scope="col">Difference</th><th scope="col">Status</th></tr></thead>
                    <tbody>
                        @foreach($exchanges as $exchange)
                            <tr>
                                <th scope="row" class="font-normal"><a href="{{ route('exchanges.show', $exchange) }}" class="row-link">{{ $exchange->exchange_number }}</a><span class="block text-xs text-text-secondary">{{ $exchange->created_at->format('j M Y, H:i') }}</span></th>
                                <td class="break-words">{{ $exchange->sale->customer?->full_name ?? 'Walk-in customer' }}</td>
                                <td>{{ $exchange->sale->sale_number }}</td>
                                <td class="whitespace-nowrap">{{ $difference($exchange) }}</td>
                                <td><x-status :value="$exchange->status" :label="$exchange->status->value === 'PENDING' ? 'Not completed' : null" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        @if($exchanges->hasPages())<div class="border-t border-border p-4">{{ $exchanges->links() }}</div>@endif
    </div>
</x-layouts.app>
