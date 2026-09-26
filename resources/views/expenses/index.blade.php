@php
    $today = now();
    $periods = [
        'This month' => [$today->copy()->startOfMonth()->format('Y-m-d'), $today->format('Y-m-d')],
        'Last month' => [$today->copy()->subMonthNoOverflow()->startOfMonth()->format('Y-m-d'), $today->copy()->subMonthNoOverflow()->endOfMonth()->format('Y-m-d')],
        'This year' => [$today->copy()->startOfYear()->format('Y-m-d'), $today->format('Y-m-d')],
        'All time' => [null, null],
    ];
    $from = $filters['date_from'] ?? null;
    $to = $filters['date_to'] ?? null;
    $activePeriod = collect($periods)->search(fn ($range) => $range === [$from, $to]);
    $periodLabel = $activePeriod === 'All time' ? 'All time' : ($activePeriod ?: collect([$from ? \Illuminate\Support\Carbon::parse($from)->format('j M Y') : 'Start', $to ? \Illuminate\Support\Carbon::parse($to)->format('j M Y') : 'today'])->join(' – '));
    $categoryNames = $categories->pluck('name', 'id');
    $advanced = ($filters['category_id'] ?? null) || ($filters['recorded_by'] ?? null) || ($from && ! $activePeriod) || ($to && ! $activePeriod);
    $filtered = ($filters['q'] ?? null) || ($filters['category_id'] ?? null) || ($filters['recorded_by'] ?? null) || $from || $to;
    $largest = $byCategory->first()['total'] ?? '0';
@endphp
<x-layouts.app title="Expenses">
    <x-page-header title="Expenses" description="Running costs such as rent, electricity and packaging. Stock bought from suppliers goes in Purchases.">
        <x-slot:actions>
            @can('expense-categories.manage')<a href="{{ route('expense-categories.index') }}" class="inline-flex min-h-11 items-center rounded-lg border border-border bg-surface px-4 text-sm font-semibold hover:bg-background">Manage categories</a>@endcan
            @can('expenses.create')<x-action-link :href="route('expenses.create')">Record expense</x-action-link>@endcan
        </x-slot:actions>
    </x-page-header>

    <nav aria-label="Period" class="-mx-1 mb-4 flex gap-1.5 overflow-x-auto px-1 pb-1 [scrollbar-width:none]">
        @foreach($periods as $label => [$start, $end])
            <a href="{{ request()->fullUrlWithQuery(['date_from' => $start, 'date_to' => $end, 'page' => null]) }}" @if($activePeriod === $label) aria-current="page" @endif
                @class(['inline-flex shrink-0 items-center rounded-full border px-3.5 py-1.5 text-sm font-semibold', 'border-text-primary bg-text-primary text-white' => $activePeriod === $label, 'border-border bg-surface hover:bg-selected' => $activePeriod !== $label])>{{ $label }}</a>
        @endforeach
    </nav>

    <section class="mb-6 grid gap-4 rounded-2xl border border-border bg-surface p-5 lg:grid-cols-[260px_minmax(0,1fr)]" aria-labelledby="spent-heading">
        <div>
            <h2 id="spent-heading" class="text-sm font-medium text-text-secondary">Spent · {{ $periodLabel }}</h2>
            <p class="mt-1 text-3xl font-bold tabular-nums">@money($total)</p>
            <p class="mt-1 text-xs text-text-secondary">{{ number_format($expenses->total()) }} {{ \Illuminate\Support\Str::plural('expense', $expenses->total()) }}@if($filters['q'] ?? null) matching “{{ $filters['q'] }}”@endif</p>
        </div>
        @if($byCategory->isNotEmpty())
            <ul class="space-y-2 text-sm" aria-label="Spending by category">
                @foreach($byCategory->take(5) as $row)
                    @php $share = \Brick\Math\BigDecimal::of($largest)->isZero() ? 0 : (float) (string) \Brick\Math\BigDecimal::of($row['total'])->multipliedBy(100)->dividedBy($largest, 1, \Brick\Math\RoundingMode::HalfUp); @endphp
                    <li class="grid grid-cols-[minmax(0,9rem)_minmax(0,1fr)_auto] items-center gap-3">
                        <a href="{{ request()->fullUrlWithQuery(['category_id' => $row['id'], 'page' => null]) }}" class="truncate font-medium hover:underline">{{ $categoryNames[$row['id']] ?? 'Unknown' }}</a>
                        <span class="h-2 overflow-hidden rounded-full bg-background" aria-hidden="true"><span class="block h-full rounded-full bg-primary" style="width: {{ max($share, 2) }}%"></span></span>
                        <span class="font-semibold tabular-nums">{{ \App\Support\Money::format($row['total'], false) }}</span>
                    </li>
                @endforeach
                @if($byCategory->count() > 5)<li class="text-xs text-text-secondary">and {{ $byCategory->count() - 5 }} more {{ \Illuminate\Support\Str::plural('category', $byCategory->count() - 5) }}</li>@endif
            </ul>
        @endif
    </section>

    <form method="GET" class="mb-5 space-y-3" role="search">
        @foreach(['date_from', 'date_to'] as $keep)@if(($filters[$keep] ?? null) && $activePeriod)<input type="hidden" name="{{ $keep }}" value="{{ $filters[$keep] }}">@endif @endforeach
        <div class="relative max-w-md">
            <label for="expense-search" class="sr-only">Search expenses</label>
            <svg class="pointer-events-none absolute top-1/2 left-3.5 size-5 -translate-y-1/2 text-text-secondary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <input id="expense-search" name="q" type="search" value="{{ $filters['q'] ?? '' }}" maxlength="191" placeholder="Expense number or description" class="min-h-11 w-full rounded-lg border border-border bg-surface py-2 pr-3 pl-11 text-sm">
        </div>
        <details class="rounded-2xl border border-border bg-surface" @if($advanced) open @endif>
            <summary class="cursor-pointer px-5 py-3 text-sm font-semibold">Category, person and custom dates</summary>
            <div class="grid gap-4 border-t border-border p-5 sm:grid-cols-2 lg:grid-cols-5">
                <x-select name="category_id" label="Category"><option value="">All categories</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(($filters['category_id'] ?? '') == $category->id)>{{ $category->name }}</option>@endforeach</x-select>
                <x-select name="recorded_by" label="Recorded by"><option value="">Anyone</option>@foreach($recorders as $recorder)<option value="{{ $recorder->id }}" @selected(($filters['recorded_by'] ?? '') == $recorder->id)>{{ $recorder->name }}</option>@endforeach</x-select>
                <x-input name="date_from" label="From" type="date" :value="$activePeriod ? '' : ($from ?? '')" />
                <x-input name="date_to" label="To" type="date" :value="$activePeriod ? '' : ($to ?? '')" />
                <div class="flex items-end gap-4"><x-button type="submit" variant="secondary">Apply</x-button>@if($filtered)<a href="{{ route('expenses.index') }}" class="py-3 text-sm font-semibold underline underline-offset-4">Clear all</a>@endif</div>
            </div>
        </details>
    </form>

    <div class="overflow-hidden rounded-2xl border border-border bg-surface">
        @if($expenses->isEmpty())
            <div class="p-10 text-center">
                <p class="font-semibold">No expenses found.</p>
                <p class="mt-1 text-sm text-text-secondary">{{ $filtered ? 'Try another period or clear the filters.' : 'Record rent, bills and other running costs to see where money goes.' }}</p>
            </div>
        @else
            <ul class="divide-y divide-border sm:hidden">
                @foreach($expenses as $expense)
                    <li class="relative px-4 py-4 hover:bg-selected/50">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <a href="{{ route('expenses.show', $expense) }}" class="row-link break-words">{{ $expense->category->name }}</a>
                                @if($expense->description)<p class="text-sm break-words">{{ \Illuminate\Support\Str::limit($expense->description, 80) }}</p>@endif
                                <p class="mt-0.5 text-xs text-text-secondary">{{ $expense->expense_date->format('j M Y') }} · {{ $expense->expense_number }} · {{ $expense->recorder->name }}</p>
                            </div>
                            <p class="shrink-0 font-semibold tabular-nums">@money($expense->amount)</p>
                        </div>
                    </li>
                @endforeach
            </ul>
            <div class="hidden overflow-x-auto sm:block">
                <table class="data-table">
                    <caption class="sr-only">Operating expenses</caption>
                    <thead><tr><th scope="col">Date</th><th scope="col">Expense</th><th scope="col">Category</th><th scope="col">Recorded by</th><th scope="col" class="num">Amount (TZS)</th></tr></thead>
                    <tbody>
                        @foreach($expenses as $expense)
                            <tr>
                                <td class="whitespace-nowrap">{{ $expense->expense_date->format('j M Y') }}</td>
                                <th scope="row" class="max-w-md font-normal"><a href="{{ route('expenses.show', $expense) }}" class="row-link break-words">{{ $expense->description ? \Illuminate\Support\Str::limit($expense->description, 90) : $expense->category->name }}</a><span class="block text-xs text-text-secondary">{{ $expense->expense_number }}</span></th>
                                <td>{{ $expense->category->name }}</td>
                                <td>{{ $expense->recorder->name }}</td>
                                <td class="num font-semibold">{{ \App\Support\Money::format($expense->amount, false) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        @if($expenses->hasPages())<div class="border-t border-border p-4">{{ $expenses->links() }}</div>@endif
    </div>
</x-layouts.app>
