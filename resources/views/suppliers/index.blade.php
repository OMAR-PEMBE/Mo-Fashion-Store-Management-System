@php
    $filtered = ($filters['q'] ?? null) || ($filters['status'] ?? null);
@endphp
<x-layouts.app title="Suppliers">
    <x-page-header title="Suppliers" description="The businesses you buy stock from, and what you have bought from each.">
        <x-slot:actions>@can('create', App\Models\Supplier::class)<x-action-link :href="route('suppliers.create')">Add supplier</x-action-link>@endcan</x-slot:actions>
    </x-page-header>

    <form method="GET" class="mb-4" role="search">
        @if($filters['status'] ?? null)<input type="hidden" name="status" value="{{ $filters['status'] }}">@endif
        <div class="relative max-w-md">
            <label for="supplier-search" class="sr-only">Search suppliers</label>
            <svg class="pointer-events-none absolute top-1/2 left-3.5 size-5 -translate-y-1/2 text-text-secondary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <input id="supplier-search" name="q" type="search" value="{{ $filters['q'] ?? '' }}" maxlength="191" placeholder="Name, code, contact, phone or email" class="min-h-11 w-full rounded-lg border border-border bg-surface py-2 pr-3 pl-11 text-sm">
        </div>
    </form>
    <x-filter-tabs :options="['' => 'All', 'active' => 'Active', 'inactive' => 'Inactive']" :counts="$counts" class="mb-5" />

    <div class="overflow-hidden rounded-2xl border border-border bg-surface">
        @if($suppliers->isEmpty())
            <div class="p-10 text-center">
                <p class="font-semibold">{{ $filtered ? 'No suppliers match.' : 'No suppliers found' }}</p>
                <p class="mt-1 text-sm text-text-secondary">{{ $filtered ? 'Check the spelling or clear the search.' : 'Add the businesses you buy from so purchases can be recorded against them.' }}</p>
                @if($filtered)<a href="{{ route('suppliers.index') }}" class="mt-3 inline-block text-sm font-semibold underline underline-offset-4">Clear search</a>@endif
            </div>
        @else
            <ul class="divide-y divide-border sm:hidden">
                @foreach($suppliers as $supplier)
                    <li class="relative px-4 py-4 hover:bg-selected/50">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <a href="{{ route('suppliers.show', $supplier) }}" class="row-link break-words">{{ $supplier->name }}</a>
                                <p class="mt-0.5 text-xs text-text-secondary">{{ collect([$supplier->supplier_code, $supplier->contact_person, \App\Support\Phone::display($supplier->phone)])->filter()->join(' · ') }}</p>
                                <p class="text-xs text-text-secondary">{{ $supplier->last_purchase_date ? 'Last bought '.\Illuminate\Support\Carbon::parse($supplier->last_purchase_date)->format('j M Y') : 'Nothing received yet' }}</p>
                            </div>
                            <div class="shrink-0 text-right">
                                @if($supplier->received_count)<p class="font-semibold tabular-nums">@money($supplier->received_total)</p>@endif
                                @unless($supplier->is_active)<x-badge tone="neutral" class="mt-1">Inactive</x-badge>@endunless
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
            <div class="hidden overflow-x-auto sm:block">
                <table class="data-table">
                    <caption class="sr-only">Suppliers and contact details</caption>
                    <thead><tr><th scope="col">Supplier</th><th scope="col">Contact</th><th scope="col">Last bought</th><th scope="col" class="num">Received</th><th scope="col" class="num">Total bought (TZS)</th></tr></thead>
                    <tbody>
                        @foreach($suppliers as $supplier)
                            <tr>
                                <th scope="row" class="font-normal">
                                    <a href="{{ route('suppliers.show', $supplier) }}" class="row-link break-words">{{ $supplier->name }}</a>
                                    <span class="block text-xs text-text-secondary">{{ $supplier->supplier_code }}@if($supplier->location) · {{ $supplier->location }}@endif</span>
                                    @unless($supplier->is_active)<x-badge tone="neutral" class="mt-1">Inactive</x-badge>@endunless
                                </th>
                                <td class="break-words">{{ $supplier->contact_person ?? '' }}<span class="block text-xs text-text-secondary">{{ \App\Support\Phone::display($supplier->phone) ?? ($supplier->contact_person ? '' : 'No contact saved') }}</span></td>
                                <td>{{ $supplier->last_purchase_date ? \Illuminate\Support\Carbon::parse($supplier->last_purchase_date)->format('j M Y') : '—' }}</td>
                                <td class="num">{{ $supplier->received_count ? number_format($supplier->received_count).' '.\Illuminate\Support\Str::plural('purchase', $supplier->received_count) : '—' }}</td>
                                <td class="num font-semibold">{{ $supplier->received_count ? \App\Support\Money::format($supplier->received_total, false) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        @if($suppliers->hasPages())<div class="border-t border-border p-4">{{ $suppliers->links() }}</div>@endif
    </div>
</x-layouts.app>
