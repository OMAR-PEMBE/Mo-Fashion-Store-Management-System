@php
    $groups = [
        'Money' => [
            'profit' => ['Profit', 'Did we make money this month? Sales, cost of goods and running costs in one statement.'],
            'sales' => ['Sales', 'Every sale in a period, by salesperson, customer or payment method.'],
            'expenses' => ['Expenses', 'Running costs in a period, by category or person.'],
        ],
        'Stock' => [
            'inventory' => ['Stock on hand', 'What is in the shop right now, what is held for orders and what has run out.'],
            'purchases' => ['Purchases', 'What was bought from suppliers and what it cost.'],
        ],
        'Customers' => [
            'customers' => ['Customers', 'Who bought, how often and how much, for follow-up.'],
        ],
        'After-sales' => [
            'returns' => ['Returns', 'Goods brought back and why.'],
            'refunds' => ['Refunds', 'Money paid back to customers.'],
            'exchanges' => ['Exchanges', 'Swaps, with any extra paid or refunded.'],
        ],
    ];
@endphp
<x-layouts.app title="Reports">
    <x-page-header title="Reports" description="Pick the question you want answered. Every report can be downloaded as a spreadsheet (CSV)." />

    @if(empty($types))
        <div class="rounded-2xl border border-border bg-surface p-10 text-center"><p class="font-semibold">No reports are available to your account.</p><p class="mt-1 text-sm text-text-secondary">Ask the owner if you need access.</p></div>
    @else
        <div class="space-y-8">
            @foreach($groups as $group => $reports)
                @php $visible = array_intersect_key($reports, array_flip($types)); @endphp
                @if($visible)
                    <section aria-labelledby="group-{{ \Illuminate\Support\Str::slug($group) }}">
                        <h2 id="group-{{ \Illuminate\Support\Str::slug($group) }}" class="mb-3 text-xs font-semibold tracking-widest text-text-secondary uppercase">{{ $group }}</h2>
                        <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach($visible as $type => [$title, $question])
                                <li @class(['relative rounded-2xl border bg-surface p-5 transition-colors hover:border-primary hover:bg-selected/40', 'border-primary/50' => $type === 'profit', 'border-border' => $type !== 'profit'])>
                                    <a href="{{ route('reports.show', $type) }}" class="row-link text-base">{{ $title }}</a>
                                    <p class="mt-1 text-sm text-text-secondary">{{ $question }}</p>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            @endforeach
        </div>
    @endif
</x-layouts.app>
