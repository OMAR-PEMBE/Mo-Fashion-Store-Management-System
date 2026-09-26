@php
    $titles = ['profit' => 'Profit', 'sales' => 'Sales', 'expenses' => 'Expenses', 'inventory' => 'Stock on hand', 'purchases' => 'Purchases',
        'customers' => 'Customers', 'returns' => 'Returns', 'refunds' => 'Refunds', 'exchanges' => 'Exchanges'];
    $labels = ['product' => 'Product name or code', 'variant' => 'Product option code (SKU)', 'customer' => 'Customer name, code or phone', 'supplier' => 'Supplier name or code',
        'category_id' => 'Category', 'size_id' => 'Size', 'colour_id' => 'Colour', 'salesperson_id' => 'Salesperson', 'recorded_by' => 'Recorded by',
        'purchase_count_min' => 'At least this many sales', 'spent_min' => 'Spent at least (TZS)', 'status' => 'Status', 'payment_method' => 'Payment method', 'stock_status' => 'Stock'];
    $dated = $type !== 'inventory';
    $today = now();
    $periods = [
        'This month' => [$today->copy()->startOfMonth()->toDateString(), $today->toDateString()],
        'Last month' => [$today->copy()->subMonthNoOverflow()->startOfMonth()->toDateString(), $today->copy()->subMonthNoOverflow()->endOfMonth()->toDateString()],
        'This year' => [$today->copy()->startOfYear()->toDateString(), $today->toDateString()],
    ];
    $activePeriod = $dated ? collect($periods)->search(fn ($range) => $range === [$filters['date_from'], $filters['date_to']]) : false;
    $periodText = $dated ? \Illuminate\Support\Carbon::parse($filters['date_from'])->format('j M Y').' – '.\Illuminate\Support\Carbon::parse($filters['date_to'])->format('j M Y') : null;
    $extraFilters = collect($options)->keys()->reject(fn ($field) => $field === 'status');
    $advancedOpen = $extraFilters->contains(fn ($field) => filled($filters[$field] ?? null));
    $numeric = array_merge($money, ['physical', 'reserved', 'available', 'purchases']);
    $linkable = ['sales', 'purchases', 'expenses', 'returns', 'refunds', 'exchanges'];
    $payments = \App\Services\SaleService::PAYMENT_METHODS;
    $cell = function (string $field, string $value) use ($payments) {
        if ($value === '') {
            return '—';
        }

        return match ($field) {
            'status', 'payment_status' => \App\Support\Status::label($value),
            'payment_method' => $payments[$value] ?? $value,
            'date' => \Illuminate\Support\Carbon::parse($value)->format('j M Y'),
            default => $value,
        };
    };
@endphp
<x-layouts.app :title="ucfirst($type).' report'">
    <x-page-header :title="$titles[$type] ?? ucfirst($type)" :back="route('reports.index')" back-label="Reports" :description="$dated ? $periodText : 'As it stands right now'">
        <x-slot:actions><a href="{{ route('reports.export', ['type' => $type] + \Illuminate\Support\Arr::except($filters, 'page')) }}" class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-border bg-surface px-4 text-sm font-semibold hover:bg-background">
            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4v11m0 0-4-4m4 4 4-4M5 19h14"/></svg>Download CSV</a></x-slot:actions>
    </x-page-header>

    @if($errors->any())<div role="alert" class="mb-5 rounded-xl border border-danger/30 bg-danger/5 p-4 text-sm text-danger">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif

    <form method="GET" class="mb-6 space-y-3">
        @if($dated)
            <div class="flex flex-wrap items-center gap-2">
                <nav aria-label="Period" class="-mx-1 flex gap-1.5 overflow-x-auto px-1 pb-1 [scrollbar-width:none]">
                    @foreach($periods as $label => [$start, $end])
                        <a href="{{ request()->fullUrlWithQuery(['date_from' => $start, 'date_to' => $end, 'page' => null]) }}" @if($activePeriod === $label) aria-current="page" @endif
                            @class(['inline-flex shrink-0 items-center rounded-full border px-3.5 py-1.5 text-sm font-semibold', 'border-text-primary bg-text-primary text-white' => $activePeriod === $label, 'border-border bg-surface hover:bg-selected' => $activePeriod !== $label])>{{ $label }}</a>
                    @endforeach
                </nav>
                <div class="flex flex-wrap items-center gap-2 text-sm">
                    <label for="date_from" class="sr-only">From</label><input id="date_from" name="date_from" type="date" value="{{ $filters['date_from'] }}" max="{{ $today->toDateString() }}" required class="min-h-10 rounded-lg border border-border bg-surface px-2 py-1.5 text-sm">
                    <span class="text-text-secondary" aria-hidden="true">to</span>
                    <label for="date_to" class="sr-only">To (inclusive)</label><input id="date_to" name="date_to" type="date" value="{{ $filters['date_to'] }}" max="{{ $today->toDateString() }}" required class="min-h-10 rounded-lg border border-border bg-surface px-2 py-1.5 text-sm">
                    <x-button type="submit" variant="secondary">Show</x-button>
                </div>
            </div>
        @endif
        @if(isset($options['status']))
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <label for="status" class="font-medium">Status</label>
                <select id="status" name="status" x-data @change="$el.form.requestSubmit()" class="min-h-10 rounded-lg border border-border bg-surface px-2 py-1.5 text-sm">
                    <option value="" @selected(($filters['status'] ?? '') === '')>Any status</option>
                    @foreach($options['status'] as $value => $choice)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ \App\Support\Status::label($value) }}</option>@endforeach
                </select>
            </div>
        @endif
        @if($extraFilters->isNotEmpty())
            <details class="rounded-2xl border border-border bg-surface" @if($advancedOpen) open @endif>
                <summary class="cursor-pointer px-5 py-3 text-sm font-semibold">Narrow down{{ $advancedOpen ? ' (filters on)' : '' }}</summary>
                <div class="grid gap-4 border-t border-border p-5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($extraFilters as $field)
                        @if($options[$field] !== null)
                            <x-select :name="$field" :label="$labels[$field]"><option value="">All</option>@foreach($options[$field] as $value => $choice)<option value="{{ $value }}" @selected(($filters[$field] ?? '') == $value)>{{ $choice }}</option>@endforeach</x-select>
                        @else
                            <x-input :name="$field" :label="$labels[$field]" :value="$filters[$field] ?? ''" maxlength="150" />
                        @endif
                    @endforeach
                    <div class="flex items-end gap-4"><x-button type="submit" variant="secondary">Apply</x-button><a href="{{ route('reports.show', $type) }}" class="py-3 text-sm font-semibold underline underline-offset-4">Clear all</a></div>
                </div>
            </details>
        @endif
    </form>

    @if($summary)
        @php
            $d = fn ($key) => \Brick\Math\BigDecimal::of((string) $summary[$key]);
            $net = $d('estimated_net_profit');
            $margin = $d('net_sales')->isZero() ? null : $d('gross_profit')->multipliedBy(100)->dividedBy($d('net_sales'), 1, \Brick\Math\RoundingMode::HalfUp);
            $line = fn ($label, $key, $sign = '') => ['label' => $label, 'key' => $key, 'sign' => $sign];
        @endphp
        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px] lg:items-start">
            <section class="overflow-hidden rounded-2xl border border-border bg-surface" aria-labelledby="statement-heading">
                <h2 id="statement-heading" class="border-b border-border px-5 py-4 text-base font-semibold">Profit statement</h2>
                @foreach([
                    ['Money in', [$line('Sales', 'gross_sales'), $line('Extra paid on exchanges', 'exchange_payments', '+'), $line('Refunds paid back', 'refunds', '−')], $line('Net sales', 'net_sales')],
                    ['Cost of the goods sold', [$line('Cost of items sold', 'sales_cogs'), $line('Cost of replacement items given', 'replacement_costs', '+'), $line('Returned items back in stock', 'return_costs', '−'), $line('Exchanged items back in stock', 'exchange_return_costs', '−')], $line('Cost of goods sold', 'cogs')],
                ] as [$heading, $parts, $totalLine])
                    <div class="border-b border-border px-5 py-4">
                        <h3 class="mb-2 text-xs font-semibold tracking-widest text-text-secondary uppercase">{{ $heading }}</h3>
                        <dl class="space-y-1.5 text-sm">
                            @foreach($parts as $part)
                                <div class="flex justify-between gap-4"><dt class="text-text-secondary">{{ $part['sign'] ? $part['sign'].' ' : '' }}{{ $part['label'] }}</dt><dd class="tabular-nums">@money($summary[$part['key']])</dd></div>
                            @endforeach
                            <div class="flex justify-between gap-4 border-t border-border pt-1.5 font-semibold"><dt>{{ $totalLine['label'] }}</dt><dd class="tabular-nums">@money($summary[$totalLine['key']])</dd></div>
                        </dl>
                    </div>
                @endforeach
                <dl class="space-y-1.5 px-5 py-4 text-sm">
                    <div class="flex justify-between gap-4 font-semibold"><dt>Gross profit @if($margin !== null)<span class="font-normal text-text-secondary">({{ $margin }}% of net sales)</span>@endif</dt><dd class="tabular-nums">@money($summary['gross_profit'])</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-text-secondary">− Running costs (expenses)</dt><dd class="tabular-nums">@money($summary['expenses'])</dd></div>
                </dl>
                <div @class(['flex flex-wrap items-baseline justify-between gap-2 px-5 py-4', 'bg-success/10' => $net->isPositive(), 'bg-danger/10' => $net->isNegative(), 'bg-background' => $net->isZero()])>
                    <span class="font-semibold">Estimated net profit</span>
                    <span @class(['text-2xl font-bold tabular-nums', 'text-danger' => $net->isNegative()])>@money($summary['estimated_net_profit'])</span>
                </div>
            </section>
            <aside class="space-y-4">
                <div @class(['rounded-2xl border p-5', 'border-success/30 bg-success/5' => $net->isPositive(), 'border-danger/30 bg-danger/5' => $net->isNegative(), 'border-border bg-surface' => $net->isZero()])>
                    <p class="text-sm text-text-secondary">{{ $periodText }}</p>
                    <p class="mt-1 text-lg font-semibold">{{ $net->isPositive() ? 'The shop made a profit.' : ($net->isNegative() ? 'The shop spent more than it earned.' : 'The shop broke even.') }}</p>
                    <p class="mt-1 text-sm text-text-secondary">This is an estimate for running the shop, not full accounts.</p>
                </div>
                <div class="rounded-2xl border border-border bg-surface p-5 text-sm">
                    <p class="font-semibold">Check the numbers</p>
                    <ul class="mt-2 space-y-1">
                        @foreach(['sales' => 'Sales in this period', 'refunds' => 'Refunds', 'returns' => 'Returns', 'exchanges' => 'Exchanges', 'expenses' => 'Expenses'] as $source => $text)
                            @if(app(\App\Services\ReportService::class)->allowed(auth()->user(), $source))<li><a class="underline underline-offset-4" href="{{ route('reports.show', ['type' => $source, 'date_from' => $filters['date_from'], 'date_to' => $filters['date_to']] + ($source === 'expenses' ? [] : ['status' => 'COMPLETED'])) }}">{{ $text }}</a></li>@endif
                        @endforeach
                    </ul>
                </div>
                <details class="rounded-2xl border border-border bg-surface p-5 text-sm">
                    <summary class="cursor-pointer font-semibold">How this is worked out</summary>
                    <p class="mt-2 text-text-secondary">Returns, refunds and exchanges count in the period they were completed; expenses use the date paid. Only items returned in sellable condition go back into stock and reverse their cost; damaged or faulty items keep their original cost. Refunds from exchanges are counted once.</p>
                </details>
            </aside>
        </div>
    @else
        <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2 text-sm">
            <p><span class="font-semibold">{{ number_format($rows->total()) }}</span> {{ \Illuminate\Support\Str::plural('record', $rows->total()) }}</p>
            <p class="text-xs text-text-secondary">Downloads include every match, up to 5,000 rows.</p>
        </div>
        <div class="overflow-hidden rounded-2xl border border-border bg-surface">
            @if($rows->isEmpty())
                <div class="p-10 text-center"><p class="font-semibold">No matching records.</p><p class="mt-1 text-sm text-text-secondary">Try a wider period or clear the filters.</p></div>
            @else
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <caption class="sr-only">{{ $titles[$type] ?? ucfirst($type) }} report results</caption>
                        <thead><tr>@foreach($columns as $field => $label)<th scope="col" @class(['num' => in_array($field, $numeric, true)])>{{ $label }}</th>@endforeach</tr></thead>
                        <tbody>
                            @foreach($rows as $row)
                                <tr>
                                    @foreach($row['values'] as $field => $value)
                                        <td @class(['num' => in_array($field, $numeric, true), 'max-w-xs break-words' => ! in_array($field, $numeric, true), 'whitespace-nowrap' => $field === 'date'])>
                                            @if($field === 'number' && in_array($type, $linkable, true))<a class="font-semibold underline underline-offset-4" href="{{ route($type.'.show', $row['id']) }}">{{ $value }}</a>
                                            @elseif(in_array($field, ['status', 'payment_status'], true) && $value !== '')<x-status :value="$value" />
                                            @elseif(in_array($field, $money, true) && $value !== ''){{ \App\Support\Money::format($value, false) }}
                                            @else{{ $cell($field, $value) }}@endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                        @if($totals && $rows->total() > 0)
                            <tfoot class="border-t-2 border-border bg-background/60 font-semibold">
                                <tr>@foreach(array_keys($columns) as $i => $field)<td @class(['num' => in_array($field, $numeric, true)])>@if($i === 0)Total, all {{ number_format($rows->total()) }}@elseif(isset($totals[$field])){{ \App\Support\Money::format($totals[$field], false) }}@endif</td>@endforeach</tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            @endif
            @if($rows->hasPages())<div class="border-t border-border p-4">{{ $rows->links() }}</div>@endif
        </div>
        <details class="mt-4 text-sm">
            <summary class="cursor-pointer font-semibold text-text-secondary">About this report</summary>
            <p class="mt-2 leading-6 text-text-secondary">
                @if($type === 'inventory')Current balances, including switched-off and archived items. Ready to sell = in the shop − held for orders. This is today's position, not a past snapshot.
                @elseif($type === 'customers')Completed sales in the period, before refunds and exchanges. Walk-in sales are not included. Customers with no sales in the period show zero.
                @else Product filters select matching documents; displayed amounts are full document totals, not just matching lines. Completed returns, refunds and exchanges use their completion date; unfinished ones use the date they were started. Sales use the sale date; purchases and expenses use their own dates.
                @endif
            </p>
        </details>
    @endif
</x-layouts.app>
