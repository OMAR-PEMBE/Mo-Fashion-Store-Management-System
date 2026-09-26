@php
    $phone = \App\Support\Phone::international($supplier->phone);
    $button = 'inline-flex min-h-11 items-center gap-2 rounded-lg border border-border bg-surface px-4 text-sm font-semibold hover:bg-background';
    $statusBadge = fn ($status) => match ($status->value) { 'DRAFT' => ['Draft, not received', 'warning'], 'CONFIRMED' => ['Received', 'success'], default => ['Cancelled', 'neutral'] };
@endphp
<x-layouts.app :title="$supplier->name">
    <x-page-header :title="$supplier->name" :back="route('suppliers.index')" back-label="Suppliers"
        :description="collect([$supplier->supplier_code, $supplier->location])->filter()->join(' · ')">
        <x-slot:badge>@unless($supplier->is_active)<x-badge tone="neutral">Inactive</x-badge>@endunless</x-slot:badge>
        <x-slot:actions>
            @if($phone)<a href="tel:+{{ $phone }}" class="{{ $button }}"><svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/></svg>Call</a>
                <a href="https://wa.me/{{ $phone }}" target="_blank" rel="noopener" class="{{ $button }}"><svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3.5 20.5 5 16a8.5 8.5 0 1 1 3.3 3.1L3.5 20.5Z"/></svg>WhatsApp</a>@endif
            @can('update', $supplier)<a href="{{ route('suppliers.edit', $supplier) }}" class="{{ $button }}">Edit</a>@endcan
            @if($supplier->is_active && auth()->user()->can('purchases.manage'))<x-action-link :href="route('purchases.create', ['supplier' => $supplier->id])">New purchase</x-action-link>@endif
        </x-slot:actions>
    </x-page-header>

    @if($summary)
        <dl class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="rounded-2xl border border-border bg-surface p-4"><dt class="text-xs font-medium text-text-secondary">Total bought</dt><dd class="mt-1 text-xl font-bold tabular-nums">@money($summary['total'])</dd></div>
            <div class="rounded-2xl border border-border bg-surface p-4"><dt class="text-xs font-medium text-text-secondary">Purchases received</dt><dd class="mt-1 text-xl font-bold tabular-nums">{{ number_format($summary['count']) }}</dd></div>
            <div class="rounded-2xl border border-border bg-surface p-4"><dt class="text-xs font-medium text-text-secondary">Last bought</dt><dd class="mt-1 text-xl font-bold">{{ $summary['last'] ? \Illuminate\Support\Carbon::parse($summary['last'])->format('j M Y') : 'Not yet' }}</dd></div>
            <div @class(['rounded-2xl border p-4', 'border-warning/40 bg-warning/10' => $summary['drafts'], 'border-border bg-surface' => ! $summary['drafts']])><dt class="text-xs font-medium text-text-secondary">Drafts not received</dt><dd class="mt-1 text-xl font-bold tabular-nums">{{ number_format($summary['drafts']) }}</dd></div>
        </dl>
    @endif

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px] lg:items-start">
        <section class="overflow-hidden rounded-2xl border border-border bg-surface" aria-labelledby="history-heading">
            <h2 id="history-heading" class="border-b border-border px-5 py-4 text-base font-semibold">Purchase history</h2>
            @if($purchases === null)
                <p class="p-5 text-sm text-text-secondary">Only staff who record purchases can see what was bought from this supplier.</p>
            @elseif($purchases->isEmpty())
                <div class="p-8 text-center"><p class="font-semibold">Nothing bought yet</p><p class="mt-1 text-sm text-text-secondary">Purchases from {{ $supplier->name }} will be listed here.</p></div>
            @else
                <ul class="divide-y divide-border">
                    @foreach($purchases as $purchase)
                        @php [$label, $tone] = $statusBadge($purchase->status); @endphp
                        <li class="relative flex items-start justify-between gap-4 px-5 py-4 text-sm hover:bg-selected/50">
                            <div class="min-w-0">
                                <a href="{{ route('purchases.show', $purchase) }}" class="row-link">{{ $purchase->purchase_number }}</a>
                                <p class="text-xs text-text-secondary">{{ $purchase->purchase_date->format('j M Y') }} · {{ number_format((int) $purchase->items_sum_quantity) }} units @if($purchase->supplier_invoice_number)· invoice {{ $purchase->supplier_invoice_number }}@endif</p>
                            </div>
                            <div class="shrink-0 text-right"><p class="font-semibold tabular-nums">@money($purchase->total_amount)</p><x-badge :tone="$tone" class="mt-1">{{ $label }}</x-badge></div>
                        </li>
                    @endforeach
                </ul>
                @if($purchases->hasPages())<div class="border-t border-border p-4">{{ $purchases->links() }}</div>@endif
            @endif
        </section>

        <section class="rounded-2xl border border-border bg-surface p-5 text-sm" aria-labelledby="details-heading">
            <h2 id="details-heading" class="text-base font-semibold">Contact details</h2>
            <dl class="mt-3 space-y-3">
                <div><dt class="text-text-secondary">Contact person</dt><dd class="break-words">{{ $supplier->contact_person ?? 'Not provided' }}</dd></div>
                <div><dt class="text-text-secondary">Phone</dt><dd>{{ \App\Support\Phone::display($supplier->phone) ?? 'Not provided' }}</dd></div>
                <div><dt class="text-text-secondary">Email</dt><dd class="break-all">@if($supplier->email)<a href="mailto:{{ $supplier->email }}" class="underline underline-offset-4">{{ $supplier->email }}</a>@else Not provided @endif</dd></div>
                <div><dt class="text-text-secondary">Location</dt><dd class="break-words">{{ $supplier->location ?? 'Not provided' }}</dd></div>
                @if($supplier->notes)<div><dt class="text-text-secondary">Notes</dt><dd class="whitespace-pre-line break-words">{{ $supplier->notes }}</dd></div>@endif
            </dl>
        </section>
    </div>
</x-layouts.app>
