@php
    $user = auth()->user();
    $phone = \App\Support\Phone::international($customer->phone);
    $whatsapp = \App\Support\Phone::international($customer->whatsapp_number) ?? $phone;
    $average = $customer->total_purchases > 0 ? \Brick\Math\BigDecimal::of($customer->total_spent)->dividedBy($customer->total_purchases, 0, \Brick\Math\RoundingMode::HalfUp) : null;
@endphp
<x-layouts.app :title="$customer->full_name">
    <x-page-header :title="$customer->full_name" :back="route('customers.index')" back-label="Customers"
        :description="$customer->customer_code.($customer->first_purchase_at ? ' · customer since '.$customer->first_purchase_at->format('M Y') : '')">
        <x-slot:badge>@unless($customer->is_active)<x-badge tone="neutral">Inactive</x-badge>@endunless</x-slot:badge>
        <x-slot:actions>
            @if($phone)<a href="tel:+{{ $phone }}" class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-border bg-surface px-4 text-sm font-semibold hover:bg-background">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/></svg>Call</a>@endif
            @if($whatsapp)<a href="https://wa.me/{{ $whatsapp }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-border bg-surface px-4 text-sm font-semibold hover:bg-background">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3.5 20.5 5 16a8.5 8.5 0 1 1 3.3 3.1L3.5 20.5Z"/></svg>WhatsApp</a>@endif
            @can('customers.manage')<a href="{{ route('customers.edit', $customer) }}" class="inline-flex min-h-11 items-center rounded-lg border border-border bg-surface px-4 text-sm font-semibold hover:bg-background">Edit</a>@endcan
            @if($customer->is_active && $user->can('sales.create'))<x-action-link :href="route('sales.create', ['customer' => $customer->id])">New sale for {{ \Illuminate\Support\Str::of($customer->full_name)->before(' ') }}</x-action-link>@endif
        </x-slot:actions>
    </x-page-header>

    <dl class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="rounded-2xl border border-border bg-surface p-5"><dt class="text-sm text-text-secondary">Total spent</dt><dd class="mt-1 text-xl font-bold tabular-nums">@money($customer->total_spent)</dd></div>
        <div class="rounded-2xl border border-border bg-surface p-5"><dt class="text-sm text-text-secondary">Purchases</dt><dd class="mt-1 text-xl font-bold tabular-nums">{{ number_format($customer->total_purchases) }}</dd></div>
        <div class="rounded-2xl border border-border bg-surface p-5"><dt class="text-sm text-text-secondary">Average purchase</dt><dd class="mt-1 text-xl font-bold tabular-nums">{{ $average ? \App\Support\Money::format($average) : 'None yet' }}</dd></div>
        <div class="rounded-2xl border border-border bg-surface p-5"><dt class="text-sm text-text-secondary">Last purchase</dt><dd class="mt-1 text-xl font-bold">{{ $customer->last_purchase_at?->format('j M Y') ?? 'None yet' }}</dd>@if($customer->last_purchase_at)<dd class="text-xs text-text-secondary">{{ $customer->last_purchase_at->diffForHumans() }}</dd>@endif</div>
    </dl>
    <p class="-mt-3 mb-6 text-xs text-text-secondary">Completed sales before any refunds.</p>

    <div class="grid gap-6 lg:grid-cols-[340px_minmax(0,1fr)] lg:items-start">
        <div class="space-y-6">
        <section class="rounded-2xl border border-border bg-surface p-5 text-sm" aria-labelledby="profile-heading">
            <h2 id="profile-heading" class="text-base font-semibold">Profile</h2>
            <dl class="mt-3 space-y-3">
                <div><dt class="text-text-secondary">Phone</dt><dd>{{ \App\Support\Phone::display($customer->phone) ?? 'Not given' }}</dd></div>
                <div><dt class="text-text-secondary">WhatsApp</dt><dd>{{ \App\Support\Phone::display($customer->whatsapp_number) ?? ($customer->phone ? 'Same as phone' : 'Not given') }}</dd></div>
                @if($customer->location)<div><dt class="text-text-secondary">Location</dt><dd class="break-words">{{ $customer->location }}</dd></div>@endif
                <div><dt class="text-text-secondary">Likes</dt><dd class="mt-1 flex flex-wrap gap-1.5">
                    @forelse($customer->categories as $category)<x-badge tone="neutral">{{ $category->name }}</x-badge>@empty<span>No preferences recorded</span>@endforelse
                    @if($customer->preferredSize)<x-badge tone="info">Size {{ $customer->preferredSize->name }}</x-badge>@endif
                    @if($customer->preferredColour)<x-badge tone="info">{{ $customer->preferredColour->name }}</x-badge>@endif
                </dd></div>
                <div><dt class="text-text-secondary">Marketing messages</dt><dd>@if($customer->marketing_opt_in)<x-badge tone="success">Agreed</x-badge>@else<x-badge tone="neutral">Not agreed, do not send offers</x-badge>@endif</dd></div>
                @if($customer->notes)<div><dt class="text-text-secondary">Notes</dt><dd class="rounded-lg bg-background p-3 whitespace-pre-line break-words">{{ $customer->notes }}</dd></div>@endif
            </dl>
        </section>
        @if($messages->isNotEmpty())
            <section class="rounded-2xl border border-border bg-surface p-5 text-sm" aria-labelledby="messages-heading">
                <h2 id="messages-heading" class="text-base font-semibold">Messages sent</h2>
                <ul class="mt-3 space-y-3">
                    @foreach($messages as $message)
                        <li class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-medium">{{ $message->purpose === 'receipt' ? 'Receipt' : ucfirst($message->purpose) }} · {{ $message->channel === 'whatsapp' ? 'WhatsApp' : 'SMS' }}@if($message->sale) · @can('sales.create')<a href="{{ route('sales.show', $message->sale) }}" class="underline underline-offset-4">{{ $message->sale->sale_number }}</a>@else{{ $message->sale->sale_number }}@endcan @endif</p>
                                <p class="text-xs text-text-secondary">{{ $message->recipientDisplay() }} · {{ $message->created_at->format('j M Y, H:i') }}</p>
                            </div>
                            <x-badge :tone="$message->statusTone()">{{ $message->statusLabel() }}</x-badge>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
        </div>

        <section class="overflow-hidden rounded-2xl border border-border bg-surface" aria-labelledby="history-heading">
            <h2 id="history-heading" class="border-b border-border px-5 py-4 text-base font-semibold">Purchase history</h2>
            @if($sales->isEmpty())
                <p class="p-5 text-sm text-text-secondary">{{ $user->hasPermission('sales.view_all') ? 'No purchases yet.' : 'No purchases you served yet.' }}</p>
            @else
                <ul class="divide-y divide-border">
                    @foreach($sales as $sale)
                        <li class="relative flex items-center justify-between gap-4 px-5 py-3.5 text-sm hover:bg-selected/40">
                            <div class="min-w-0">
                                @can('sales.create')<a href="{{ route('sales.show', $sale) }}" class="row-link">{{ $sale->sale_number }}</a>@else<span class="font-semibold">{{ $sale->sale_number }}</span>@endcan
                                <p class="text-xs text-text-secondary">{{ $sale->sale_date->format('j M Y, H:i') }}</p>
                            </div>
                            <div class="flex shrink-0 items-center gap-3">@if($sale->status->value !== 'COMPLETED')<x-status :value="$sale->status" />@endif<span class="font-semibold tabular-nums">@money($sale->total_amount)</span></div>
                        </li>
                    @endforeach
                </ul>
                @if($sales->hasPages())<div class="border-t border-border p-4">{{ $sales->links() }}</div>@endif
            @endif
        </section>
    </div>
</x-layouts.app>
