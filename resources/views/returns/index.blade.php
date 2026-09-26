@php
    $tabs = ['' => 'All'] + collect(\App\Enums\ReturnStatus::cases())->mapWithKeys(fn ($status) => [$status->value => \App\Support\Status::label($status)])->all();
    $filtered = ($filters['q'] ?? null) || ($filters['status'] ?? null);
@endphp
<x-layouts.app title="Returns">
    <x-page-header title="Returns" description="Items customers bring back. Sellable items go back into stock when a return is completed. Money is refunded separately.">
        <x-slot:actions><x-action-link :href="route('returns.create')">New return</x-action-link></x-slot:actions>
    </x-page-header>

    <form method="GET" class="mb-4" role="search">
        @if($filters['status'] ?? null)<input type="hidden" name="status" value="{{ $filters['status'] }}">@endif
        <label for="return-search" class="sr-only">Search by return or sale number</label>
        <div class="relative max-w-md">
            <svg class="pointer-events-none absolute top-1/2 left-3.5 size-5 -translate-y-1/2 text-text-secondary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <input id="return-search" name="q" type="search" value="{{ $filters['q'] ?? '' }}" maxlength="191" placeholder="Return or sale number" class="min-h-11 w-full rounded-lg border border-border bg-surface py-2 pr-3 pl-11 text-sm">
        </div>
    </form>
    <x-filter-tabs :options="$tabs" :counts="$counts" class="mb-5" />

    <div class="overflow-hidden rounded-2xl border border-border bg-surface">
        @if($returns->isEmpty())
            <div class="p-10 text-center">
                <p class="font-semibold">{{ $filtered ? 'No returns match these filters.' : 'No returns yet.' }}</p>
                <p class="mt-1 text-sm text-text-secondary">{{ $filtered ? 'Try another number or status.' : 'Start a return from a sale made in the last three days.' }}</p>
            </div>
        @else
            <ul class="divide-y divide-border sm:hidden">
                @foreach($returns as $return)
                    <li class="relative px-4 py-4 hover:bg-selected/50">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <a href="{{ route('returns.show', $return) }}" class="row-link break-words">{{ $return->sale->customer?->full_name ?? 'Walk-in customer' }}</a>
                                <p class="mt-0.5 text-xs text-text-secondary">{{ $return->return_number }} · {{ $return->return_date->format('j M, H:i') }}</p>
                                <p class="text-xs text-text-secondary">{{ (int) $return->items_sum_quantity }} {{ \Illuminate\Support\Str::plural('item', (int) $return->items_sum_quantity) }} from {{ $return->sale->sale_number }}</p>
                            </div>
                            <x-status :value="$return->status" class="shrink-0" />
                        </div>
                    </li>
                @endforeach
            </ul>
            <div class="hidden overflow-x-auto sm:block">
                <table class="data-table">
                    <caption class="sr-only">Returns</caption>
                    <thead><tr><th scope="col">Return</th><th scope="col">Customer</th><th scope="col">Original sale</th><th scope="col">Items</th><th scope="col">Status</th></tr></thead>
                    <tbody>
                        @foreach($returns as $return)
                            <tr>
                                <th scope="row" class="font-normal"><a href="{{ route('returns.show', $return) }}" class="row-link">{{ $return->return_number }}</a><span class="block text-xs text-text-secondary">{{ $return->return_date->format('j M Y, H:i') }}</span></th>
                                <td class="break-words">{{ $return->sale->customer?->full_name ?? 'Walk-in customer' }}</td>
                                <td>{{ $return->sale->sale_number }}</td>
                                <td class="tabular-nums">{{ (int) $return->items_sum_quantity }}</td>
                                <td><x-status :value="$return->status" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        @if($returns->hasPages())<div class="border-t border-border p-4">{{ $returns->links() }}</div>@endif
    </div>
</x-layouts.app>
