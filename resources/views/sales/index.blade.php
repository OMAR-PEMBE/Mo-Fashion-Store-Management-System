@php
    $today = now();
    $ranges = [
        'Today' => [$today->toDateString(), $today->toDateString()],
        'This week' => [$today->copy()->startOfWeek()->toDateString(), $today->toDateString()],
        'This month' => [$today->copy()->startOfMonth()->toDateString(), $today->toDateString()],
        'All dates' => [null, null],
    ];
    $from = $filters['date_from'] ?? null;
    $to = $filters['date_to'] ?? null;
    $filtered = ($filters['q'] ?? null) || ($filters['payment_method'] ?? null) || $from || $to;
    $methods = \App\Services\SaleService::PAYMENT_METHODS;
@endphp
<x-layouts.app title="Sales history">
    <x-page-header title="Sales history" :description="auth()->user()->hasPermission('sales.view_all') ? 'Every completed sale in the store.' : 'The sales you have completed.'">
        <x-slot:actions><x-action-link :href="route('sales.create')">New sale</x-action-link></x-slot:actions>
    </x-page-header>

    {{-- Quick date ranges keep the other filters --}}
    <nav aria-label="Date range" class="mb-4 flex flex-wrap gap-1.5">
        @foreach($ranges as $label => [$rangeFrom, $rangeTo])
            @php $active = $from === $rangeFrom && $to === $rangeTo; @endphp
            <a href="{{ request()->fullUrlWithQuery(['date_from' => $rangeFrom, 'date_to' => $rangeTo, 'page' => null]) }}" @if($active) aria-current="page" @endif
                @class(['rounded-full border px-3.5 py-1.5 text-sm font-semibold', 'border-text-primary bg-text-primary text-white' => $active, 'border-border bg-surface hover:bg-selected' => ! $active])>{{ $label }}</a>
        @endforeach
    </nav>

    <details class="mb-5 rounded-2xl border border-border bg-surface" @if(($filters['q'] ?? null) || ($filters['payment_method'] ?? null) || ($from && ! collect($ranges)->contains(fn ($r) => $r === [$from, $to]))) open @endif>
        <summary class="cursor-pointer px-5 py-3 text-sm font-semibold">Search and more filters</summary>
        <form method="GET" class="grid gap-4 border-t border-border p-5 sm:grid-cols-2 lg:grid-cols-5">
            <div class="lg:col-span-2"><x-input name="q" label="Sale number or customer" :value="$filters['q'] ?? ''" maxlength="191" /></div>
            <x-select name="payment_method" label="Payment method"><option value="">All methods</option>@foreach($methods as $value => $label)<option value="{{ $value }}" @selected(($filters['payment_method'] ?? '') === $value)>{{ $label }}</option>@endforeach</x-select>
            <x-input type="date" name="date_from" label="From" :value="$from ?? ''" />
            <x-input type="date" name="date_to" label="To" :value="$to ?? ''" />
            <div class="flex items-center gap-4 sm:col-span-2 lg:col-span-5"><x-button type="submit" variant="secondary">Apply filters</x-button>@if($filtered)<a href="{{ route('sales.index') }}" class="text-sm font-semibold underline underline-offset-4">Clear all</a>@endif</div>
        </form>
    </details>

    {{-- What the current view adds up to --}}
    <div class="mb-4 flex flex-wrap items-baseline justify-between gap-2 text-sm">
        <p><span class="font-semibold">{{ number_format($summary['count']) }} {{ \Illuminate\Support\Str::plural('sale', $summary['count']) }}</span> <span class="text-text-secondary">{{ $filtered ? 'match these filters' : 'in total' }}</span></p>
        <p class="text-text-secondary">Completed value <span class="ml-1 text-base font-bold text-text-primary tabular-nums">@money($summary['total'])</span></p>
    </div>

    <div class="overflow-hidden rounded-2xl border border-border bg-surface">
        @if($sales->isEmpty())
            <div class="p-10 text-center">
                <p class="font-semibold">{{ $filtered ? 'No sales match these filters.' : 'No sales yet.' }}</p>
                <p class="mt-1 text-sm text-text-secondary">{{ $filtered ? 'Try a wider date range or clear the filters.' : 'Completed sales from the point of sale appear here.' }}</p>
            </div>
        @else
            <ul class="divide-y divide-border sm:hidden">
                @foreach($sales as $sale)
                    <li class="relative px-4 py-4 hover:bg-selected/50">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <a href="{{ route('sales.show', $sale) }}" class="row-link break-words">{{ $sale->customer?->full_name ?? 'Walk-in customer' }}</a>
                                <p class="mt-0.5 text-xs text-text-secondary">{{ $sale->sale_number }} · {{ $sale->sale_date->format('j M, H:i') }}</p>
                                <p class="text-xs text-text-secondary">{{ $methods[$sale->payment_method] }} · {{ $sale->salesperson->name }}</p>
                            </div>
                            <div class="shrink-0 text-right"><p class="font-semibold tabular-nums">@money($sale->total_amount)</p>@if($sale->status->value !== 'COMPLETED')<x-status :value="$sale->status" class="mt-1" />@endif</div>
                        </div>
                    </li>
                @endforeach
            </ul>
            <div class="hidden overflow-x-auto sm:block">
                <table class="data-table">
                    <caption class="sr-only">Sales</caption>
                    <thead><tr><th scope="col">Sale</th><th scope="col">Customer</th><th scope="col">Staff</th><th scope="col">Payment</th><th scope="col" class="num">Total (TZS)</th></tr></thead>
                    <tbody>
                        @foreach($sales as $sale)
                            <tr>
                                <th scope="row" class="font-normal"><a href="{{ route('sales.show', $sale) }}" class="row-link">{{ $sale->sale_number }}</a><span class="block text-xs text-text-secondary">{{ $sale->sale_date->format('j M Y, H:i') }}</span></th>
                                <td class="break-words">{{ $sale->customer?->full_name ?? 'Walk-in customer' }}</td>
                                <td>{{ $sale->salesperson->name }}</td>
                                <td>{{ $methods[$sale->payment_method] }}</td>
                                <td class="num"><span class="font-semibold">{{ \App\Support\Money::format($sale->total_amount, false) }}</span>@if($sale->status->value !== 'COMPLETED')<x-status :value="$sale->status" class="ml-2" />@endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        @if($sales->hasPages())<div class="border-t border-border p-4">{{ $sales->links() }}</div>@endif
    </div>
</x-layouts.app>
