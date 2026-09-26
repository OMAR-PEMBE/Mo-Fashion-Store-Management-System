@php
    $sort = $filters['sort'] ?? 'name';
    $filtered = ($filters['q'] ?? null) || ($filters['status'] ?? null);
@endphp
<x-layouts.app title="Customers">
    <x-page-header title="Customers" description="People who chose to have a profile. Walk-in sales never need one.">
        <x-slot:actions>
            <div x-data><x-button variant="outline" @click="$dispatch('open-modal', 'quick-customer')">Quick add</x-button></div>
            <x-action-link :href="route('customers.create')">Full profile</x-action-link>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <form method="GET" class="w-full max-w-md" role="search">
            @foreach(['status', 'sort'] as $keep)@if($filters[$keep] ?? null)<input type="hidden" name="{{ $keep }}" value="{{ $filters[$keep] }}">@endif @endforeach
            <label for="customer-search" class="sr-only">Search customers</label>
            <div class="relative">
                <svg class="pointer-events-none absolute top-1/2 left-3.5 size-5 -translate-y-1/2 text-text-secondary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <input id="customer-search" name="q" type="search" value="{{ $filters['q'] ?? '' }}" maxlength="191" placeholder="Name, phone or customer code" class="min-h-11 w-full rounded-lg border border-border bg-surface py-2 pr-3 pl-11 text-sm">
            </div>
        </form>
        <nav aria-label="Sort customers" class="flex gap-1.5 text-sm">
            @foreach(['name' => 'A to Z', 'spent' => 'Top spenders', 'recent' => 'Recent buyers'] as $value => $label)
                <a href="{{ request()->fullUrlWithQuery(['sort' => $value === 'name' ? null : $value, 'page' => null]) }}" @if($sort === $value) aria-current="page" @endif
                    @class(['rounded-lg px-3 py-1.5 font-semibold', 'bg-selected text-text-primary' => $sort === $value, 'text-text-secondary hover:bg-background hover:text-text-primary' => $sort !== $value])>{{ $label }}</a>
            @endforeach
        </nav>
    </div>
    <x-filter-tabs :options="['' => 'All', 'active' => 'Active', 'inactive' => 'Inactive']" :counts="$counts" label="Filter by status" class="mb-5" />

    <div class="overflow-hidden rounded-2xl border border-border bg-surface">
        @if($customers->isEmpty())
            <div class="p-10 text-center">
                <p class="font-semibold">{{ $filtered ? 'No customers match.' : 'No customer profiles yet.' }}</p>
                <p class="mt-1 text-sm text-text-secondary">{{ $filtered ? 'Check the spelling, or search by phone number.' : 'Add customers who want their purchases remembered.' }}</p>
            </div>
        @else
            <ul class="divide-y divide-border sm:hidden">
                @foreach($customers as $customer)
                    <li class="relative px-4 py-4 hover:bg-selected/50">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <a href="{{ route('customers.show', $customer) }}" class="row-link break-words">{{ $customer->full_name }}</a>
                                <p class="mt-0.5 text-xs text-text-secondary">{{ \App\Support\Phone::display($customer->phone) ?? $customer->customer_code }}</p>
                                <p class="text-xs text-text-secondary">{{ $customer->total_purchases }} {{ \Illuminate\Support\Str::plural('purchase', $customer->total_purchases) }}@if($customer->last_purchase_at) · last {{ $customer->last_purchase_at->diffForHumans() }}@endif</p>
                            </div>
                            <div class="shrink-0 text-right"><p class="font-semibold tabular-nums">@money($customer->total_spent)</p>@unless($customer->is_active)<x-badge tone="neutral" class="mt-1">Inactive</x-badge>@endunless</div>
                        </div>
                    </li>
                @endforeach
            </ul>
            <div class="hidden overflow-x-auto sm:block">
                <table class="data-table">
                    <caption class="sr-only">Customers</caption>
                    <thead><tr><th scope="col">Customer</th><th scope="col">Contact</th><th scope="col" class="num">Purchases</th><th scope="col">Last purchase</th><th scope="col" class="num">Spent (TZS)</th></tr></thead>
                    <tbody>
                        @foreach($customers as $customer)
                            <tr>
                                <th scope="row" class="font-normal"><a href="{{ route('customers.show', $customer) }}" class="row-link break-words">{{ $customer->full_name }}</a>
                                    <span class="block text-xs text-text-secondary">{{ $customer->customer_code }}@unless($customer->is_active) · Inactive @endunless</span></th>
                                <td>{{ \App\Support\Phone::display($customer->phone) ?? (\App\Support\Phone::display($customer->whatsapp_number) ?? 'Not given') }}@if($customer->whatsapp_number && $customer->whatsapp_number !== $customer->phone)<span class="block text-xs text-text-secondary">WhatsApp {{ \App\Support\Phone::display($customer->whatsapp_number) }}</span>@endif</td>
                                <td class="num">{{ number_format($customer->total_purchases) }}</td>
                                <td class="whitespace-nowrap">{{ $customer->last_purchase_at?->format('j M Y') ?? 'Not yet' }}</td>
                                <td class="num font-semibold">{{ \App\Support\Money::format($customer->total_spent, false) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        @if($customers->hasPages())<div class="border-t border-border p-4">{{ $customers->links() }}</div>@endif
    </div>

    <x-modal name="quick-customer" title="Quick add a customer" x-init="{{ $errors->any() ? '$el.showModal()' : '' }}">
        <form method="POST" action="{{ route('customers.store') }}" class="space-y-5" data-busy>@csrf
            @if($errors->any())<div role="alert" class="rounded-lg border border-danger/30 bg-danger/5 p-3 text-sm text-danger">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
            <x-input name="full_name" label="Full name" :value="old('full_name')" required maxlength="191" />
            <x-input name="phone" label="Phone (optional)" type="tel" :value="old('phone')" maxlength="30" placeholder="For example 0755 123 456" />
            <x-input name="whatsapp_number" label="WhatsApp number (optional)" type="tel" :value="old('whatsapp_number')" maxlength="30" />
            @error('allow_duplicate')<label class="flex items-start gap-3 rounded-lg border border-warning bg-warning/10 p-3 text-sm"><input type="checkbox" name="allow_duplicate" value="1" required class="mt-0.5"> I checked the existing profile and want a separate customer.</label>@enderror
            <p class="text-sm text-text-secondary">Marketing messages stay off until the customer agrees. Use Full profile to record preferences and consent.</p>
            <x-button type="submit" data-busy-label="Saving…">Save customer</x-button>
        </form>
    </x-modal>
</x-layouts.app>
