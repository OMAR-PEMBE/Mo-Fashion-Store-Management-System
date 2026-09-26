@php
    $today = $periods['today'];
    $month = $periods['month'];
    $user = auth()->user();
    $hour = (int) $asOf->format('G');
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
    $firstName = \Illuminate\Support\Str::of($user->name)->before(' ')->before('(')->trim();
    $isLoss = fn ($value) => \Brick\Math\BigDecimal::of($value)->isNegative();
    $statusTone = fn ($status) => match ($status) {
        \App\Enums\OrderStatus::New => 'warning',
        \App\Enums\OrderStatus::Delivered => 'success',
        \App\Enums\OrderStatus::Cancelled => 'neutral',
        default => 'info',
    };
    $statusLabel = fn ($status) => ucfirst(strtolower(str_replace('_', ' ', $status->value)));
    $panel = 'rounded-2xl border border-border bg-surface p-5 sm:p-6';
    $panelTitle = 'text-base font-semibold';
@endphp
<x-layouts.app title="Overview">
    {{-- Greeting and the day's most common actions --}}
    <div class="mb-8 flex flex-wrap items-end justify-between gap-5">
        <div>
            <h1 class="text-3xl font-bold tracking-tight">{{ $greeting }}{{ $firstName->isNotEmpty() ? ', '.$firstName : '' }}</h1>
            <p class="mt-2 text-sm text-text-secondary">
                {{ $asOf->format('l j F Y') }} · Updated {{ $asOf->format('H:i') }}
                · <a href="{{ route('dashboard') }}" class="underline underline-offset-4 hover:text-text-primary">Refresh</a>
                @unless($allSales) · Showing your own sales @endunless
            </p>
        </div>
        <div class="flex w-full gap-2 sm:w-auto sm:gap-3">
            @can('sales.create')
                <a href="{{ route('sales.create') }}" class="inline-flex min-h-11 flex-1 items-center justify-center gap-2 rounded-lg bg-primary px-3 py-2 text-sm font-semibold text-text-primary hover:bg-primary-hover sm:flex-none sm:px-5">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>New sale</a>
            @endcan
            @can('orders.create')
                <a href="{{ route('orders.create') }}" class="inline-flex min-h-11 flex-1 items-center justify-center gap-2 rounded-lg border border-border bg-surface px-3 py-2 text-sm font-semibold hover:bg-selected sm:flex-none sm:px-5">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>New order</a>
            @endcan
            @can('expenses.create')
                <a href="{{ route('expenses.create') }}" class="inline-flex min-h-11 flex-1 items-center justify-center gap-2 rounded-lg border border-border bg-surface px-3 py-2 text-sm font-semibold hover:bg-selected sm:flex-none sm:px-5">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg><span class="sm:hidden">Expense</span><span class="hidden sm:inline">Record expense</span></a>
            @endcan
        </div>
    </div>

    {{-- Today: the three figures that answer "how are we doing?" --}}
    <section aria-labelledby="today-heading" class="mb-8">
        <h2 id="today-heading" class="mb-4 text-xs font-semibold tracking-[0.18em] text-text-secondary uppercase">Today</h2>
        <div class="grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-3 [&>*:first-child]:col-span-2 md:[&>*:first-child]:col-span-1">
            @if($today['finance'])
                @php $finance = $today['finance']; @endphp
                <div class="{{ $panel }}">
                    <p class="text-sm text-text-secondary">Sales</p>
                    <p class="mt-2 text-2xl font-bold tracking-tight sm:text-3xl">@money($finance['net_sales'])</p>
                    <p class="mt-2 text-sm text-text-secondary">{{ $today['sales_count'] }} {{ \Illuminate\Support\Str::plural('sale', $today['sales_count']) }}@if($finance['net_sales'] !== $today['sales_revenue']) · @money($today['sales_revenue']) before refunds @endif</p>
                </div>
                <div @class([$panel, 'border-danger/40' => $isLoss($finance['estimated_net_profit'])])>
                    <p class="text-sm text-text-secondary">Profit (estimated)</p>
                    <p @class(['mt-2 text-2xl font-bold tracking-tight sm:text-3xl', 'text-danger' => $isLoss($finance['estimated_net_profit'])])>@money($finance['estimated_net_profit'])</p>
                    <p @class(['mt-2 text-sm', 'font-semibold text-danger' => $isLoss($finance['estimated_net_profit']), 'text-text-secondary' => ! $isLoss($finance['estimated_net_profit'])])>{{ $isLoss($finance['estimated_net_profit']) ? 'Loss so far today' : 'After cost of goods and expenses' }}</p>
                </div>
                <div class="{{ $panel }}">
                    <p class="text-sm text-text-secondary">Expenses</p>
                    <p class="mt-2 text-2xl font-bold tracking-tight sm:text-3xl">@money($finance['expenses'])</p>
                    <p class="mt-2 text-sm text-text-secondary">Gross profit @money($finance['gross_profit'])</p>
                </div>
            @else
                @if($today['sales_count'] !== null)
                    <div class="{{ $panel }}">
                        <p class="text-sm text-text-secondary">{{ $allSales ? 'Sales' : 'Your sales' }}</p>
                        <p class="mt-2 text-2xl font-bold tracking-tight sm:text-3xl">@money($today['sales_revenue'])</p>
                        <p class="mt-2 text-sm text-text-secondary">{{ $today['sales_count'] }} completed {{ \Illuminate\Support\Str::plural('sale', $today['sales_count']) }}</p>
                    </div>
                @endif
                @if($today['orders'] !== null)
                    <div class="{{ $panel }}">
                        <p class="text-sm text-text-secondary">Orders created</p>
                        <p class="mt-2 text-2xl font-bold tracking-tight sm:text-3xl">{{ number_format($today['orders']) }}</p>
                        <p class="mt-2 text-sm text-text-secondary">{{ $allSales ? 'Across the store' : 'By you' }}</p>
                    </div>
                @endif
                @if($month['sales_count'] !== null)
                    <div class="{{ $panel }}">
                        <p class="text-sm text-text-secondary">{{ $allSales ? 'Sales this month' : 'Your sales this month' }}</p>
                        <p class="mt-2 text-2xl font-bold tracking-tight sm:text-3xl">@money($month['sales_revenue'])</p>
                        <p class="mt-2 text-sm text-text-secondary">{{ $month['sales_count'] }} completed {{ \Illuminate\Support\Str::plural('sale', $month['sales_count']) }}</p>
                    </div>
                @endif
            @endif
        </div>
        @if($today['finance'])
            <p class="mt-3 text-sm text-text-secondary">
                @if($today['orders'] !== null){{ number_format($today['orders']) }} {{ \Illuminate\Support\Str::plural('order', $today['orders']) }} created @endif
                @if($today['customers'] !== null)· {{ number_format($today['customers']) }} new {{ \Illuminate\Support\Str::plural('customer', $today['customers']) }} @endif
            </p>
        @endif
    </section>

    {{-- Work waiting for this person; every row links to where it is resolved --}}
    @php
        $a = $attention;
        $hasAttention = $a['lowCount'] + $a['outCount'] + $a['newOrders'] + $a['pendingReturns'] + $a['pendingRefunds'] > 0;
    @endphp
    <section aria-labelledby="attention-heading" class="mb-8">
        <h2 id="attention-heading" class="mb-4 text-xs font-semibold tracking-[0.18em] text-text-secondary uppercase">Needs attention</h2>
        @if(! $hasAttention)
            <div class="flex items-center gap-3 rounded-2xl border border-success/30 bg-success/5 p-5 text-sm">
                <svg class="size-5 shrink-0 text-success" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/></svg>
                <p><span class="font-semibold">All clear.</span> Nothing is waiting for you.</p>
            </div>
        @else
            <div class="divide-y divide-border overflow-hidden rounded-2xl border border-border bg-surface">
                @if($a['outCount'] + $a['lowCount'] > 0)
                    <div class="p-5">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <p class="flex items-center gap-3 text-sm font-semibold">
                                <svg @class(['size-5 shrink-0', 'text-danger' => $a['outCount'] > 0, 'text-warning' => $a['outCount'] === 0]) viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3 2 20h20L12 3Z"/><path d="M12 10v4M12 17h.01"/></svg>
                                {{ collect([$a['outCount'] ? $a['outCount'].' out of stock' : null, $a['lowCount'] ? $a['lowCount'].' running low' : null])->filter()->join(' · ') }}
                            </p>
                            <a href="{{ route('inventory.index', ['stock' => 'low']) }}" class="text-sm font-semibold underline underline-offset-4">View stock</a>
                        </div>
                        <ul class="mt-3 grid gap-2 pl-8 text-sm sm:grid-cols-2">
                            @foreach($a['stock'] as $item)
                                <li class="flex items-center justify-between gap-3 rounded-lg bg-background px-3 py-2">
                                    <span class="min-w-0 break-words">{{ $item['label'] }}</span>
                                    <x-badge :tone="$item['available'] === 0 ? 'danger' : 'warning'" class="shrink-0">{{ $item['available'] === 0 ? 'Out of stock' : $item['available'].' left' }}</x-badge>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                @foreach([
                    ['count' => $a['newOrders'], 'text' => fn ($n) => $n.' new '.\Illuminate\Support\Str::plural('order', $n).' waiting for confirmation', 'url' => $a['newOrders'] === 1 ? route('orders.show', $a['firstNewOrder']) : route('orders.index', ['status' => 'NEW']), 'action' => 'Review'],
                    ['count' => $a['pendingReturns'], 'text' => fn ($n) => $n.' '.\Illuminate\Support\Str::plural('return', $n).' waiting for approval', 'url' => route('returns.index', ['status' => 'PENDING']), 'action' => 'Review'],
                    ['count' => $a['pendingRefunds'], 'text' => fn ($n) => $n.' '.\Illuminate\Support\Str::plural('refund', $n).' waiting for approval', 'url' => route('refunds.index', ['status' => 'PENDING']), 'action' => 'Review'],
                ] as $row)
                    @if($row['count'] > 0)
                        <div class="flex flex-wrap items-center justify-between gap-3 p-5">
                            <p class="flex items-center gap-3 text-sm font-semibold">
                                <svg class="size-5 shrink-0 text-info" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                                {{ $row['text']($row['count']) }}
                            </p>
                            <a href="{{ $row['url'] }}" class="text-sm font-semibold underline underline-offset-4">{{ $row['action'] }}</a>
                        </div>
                    @endif
                @endforeach
            </div>
        @endif
    </section>

    <div class="grid gap-5 lg:grid-cols-2">
        {{-- This month: a short statement for the owner, a summary for staff --}}
        @if($month['finance'])
            @php $finance = $month['finance']; @endphp
            <section class="{{ $panel }}" aria-labelledby="month-heading">
                <h2 id="month-heading" class="{{ $panelTitle }}">This month</h2>
                <p class="mt-1 text-sm text-text-secondary">{{ $month['sales_count'] }} {{ \Illuminate\Support\Str::plural('sale', $month['sales_count']) }}@if($month['orders'] !== null) · {{ $month['orders'] }} {{ \Illuminate\Support\Str::plural('order', $month['orders']) }}@endif @if($month['customers'] !== null)· {{ $month['customers'] }} new {{ \Illuminate\Support\Str::plural('customer', $month['customers']) }}@endif</p>
                <dl class="mt-5 space-y-3 text-sm">
                    <div class="flex justify-between gap-4"><dt>Sales</dt><dd class="font-semibold tabular-nums">@money($finance['net_sales'])</dd></div>
                    <div class="flex justify-between gap-4 text-text-secondary"><dt>Cost of goods sold</dt><dd class="tabular-nums">− @money($finance['cogs'])</dd></div>
                    <div class="flex justify-between gap-4 border-t border-border pt-3"><dt class="font-semibold">Gross profit</dt><dd class="font-semibold tabular-nums">@money($finance['gross_profit'])</dd></div>
                    <div class="flex justify-between gap-4 text-text-secondary"><dt>Expenses</dt><dd class="tabular-nums">− @money($finance['expenses'])</dd></div>
                    <div @class(['flex items-baseline justify-between gap-4 rounded-lg px-3 py-3', 'bg-selected' => ! $isLoss($finance['estimated_net_profit']), 'bg-danger/5 text-danger' => $isLoss($finance['estimated_net_profit'])])>
                        <dt class="font-semibold">{{ $isLoss($finance['estimated_net_profit']) ? 'Loss (estimated)' : 'Profit (estimated)' }}</dt>
                        <dd class="text-xl font-bold tabular-nums">@money($finance['estimated_net_profit'])</dd>
                    </div>
                </dl>
                <details class="mt-5 text-sm">
                    <summary class="font-semibold text-info">How these figures are calculated</summary>
                    <dl class="mt-4 grid gap-3 sm:grid-cols-2">
                        @foreach(['gross_sales' => 'Completed sale revenue', 'exchange_payments' => 'Additional exchange payments', 'refunds' => 'Completed refunds (including exchanges)', 'sales_cogs' => 'Original sale costs', 'return_costs' => 'Sellable return cost reversals', 'replacement_costs' => 'Exchange replacement costs', 'exchange_return_costs' => 'Sellable exchange cost reversals'] as $field => $label)
                            <div><dt class="text-text-secondary">{{ $label }}</dt><dd class="mt-1 font-semibold tabular-nums">@money($finance[$field])</dd></div>
                        @endforeach
                    </dl>
                    <div class="mt-4 space-y-2 leading-6 text-text-secondary">
                        <p>Sales = completed sales + additional exchange payments − completed refunds. Cost of goods sold = original sale costs + replacement costs − sellable return and exchange cost reversals. Gross profit = sales − cost of goods sold. Profit (estimated) = gross profit − expenses.</p>
                        <p>Refunds, returns and exchanges count on the day they are completed; expenses use their expense date. Damaged, defective and other non-sellable returns keep their original cost. Pending transactions are excluded. Delivery fees are outside Version 1 accounting.</p>
                    </div>
                </details>
            </section>
        @endif

        {{-- Best sellers: products by default, variants one tap away --}}
        @if($month['sales_count'] !== null)
            <section class="{{ $panel }}" aria-labelledby="best-heading" x-data="{ tab: 'products' }">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 id="best-heading" class="{{ $panelTitle }}">Best sellers this month</h2>
                    <div class="flex rounded-lg bg-background p-1 text-xs font-semibold" role="tablist" aria-label="Best sellers view">
                        @foreach(['products' => 'Products', 'variants' => 'Variants'] as $key => $label)
                            <button type="button" role="tab" id="best-tab-{{ $key }}" aria-controls="best-panel-{{ $key }}" @click="tab = '{{ $key }}'" :aria-selected="tab === '{{ $key }}'"
                                :class="tab === '{{ $key }}' ? 'bg-surface shadow-sm' : 'text-text-secondary'" class="rounded-md px-3 py-1.5">{{ $label }}</button>
                        @endforeach
                    </div>
                </div>
                @foreach(['products' => $topProducts, 'variants' => $topVariants] as $key => $rows)
                    @php $max = max(1, (int) $rows->max('units')); @endphp
                    <ol id="best-panel-{{ $key }}" role="tabpanel" aria-labelledby="best-tab-{{ $key }}" class="mt-5 space-y-4" @if($key !== 'products') x-cloak @endif x-show="tab === '{{ $key }}'">
                        @forelse($rows as $row)
                            <li class="text-sm">
                                <div class="flex justify-between gap-4"><span class="min-w-0 break-words">{{ $key === 'products' ? $row->name : $row->label }}</span><span class="shrink-0 font-semibold tabular-nums">{{ $row->units }} {{ \Illuminate\Support\Str::plural('unit', (int) $row->units) }}</span></div>
                                <div class="mt-2 h-1.5 rounded-full bg-background" aria-hidden="true"><div class="h-1.5 rounded-full bg-primary" style="width: {{ round((int) $row->units / $max * 100) }}%"></div></div>
                            </li>
                        @empty
                            <li class="text-sm text-text-secondary">No completed sales this month.</li>
                        @endforelse
                    </ol>
                @endforeach
                <p class="mt-5 text-xs text-text-secondary">Units on completed sales, before returns.</p>
            </section>
        @endif

        {{-- Recent activity --}}
        @if($today['sales_count'] !== null)
            <section class="{{ $panel }}" aria-labelledby="recent-sales-heading">
                <div class="flex items-center justify-between gap-3">
                    <h2 id="recent-sales-heading" class="{{ $panelTitle }}">Recent sales</h2>
                    <a href="{{ route('sales.index') }}" class="text-sm font-semibold underline underline-offset-4">View all</a>
                </div>
                <ul class="mt-4 divide-y divide-border">
                    @forelse($recentSales as $sale)
                        <li class="flex items-center justify-between gap-4 py-3 text-sm">
                            <div class="min-w-0">
                                <a href="{{ route('sales.show', $sale) }}" class="font-semibold underline-offset-4 hover:underline">{{ $sale->customer?->full_name ?? 'Walk-in customer' }}</a>
                                <p class="mt-0.5 text-xs text-text-secondary">
                                    {{ $sale->completed_at->isSameDay($asOf) ? $sale->completed_at->format('H:i') : $sale->completed_at->format('j M, H:i') }}
                                    · {{ $sale->sale_number }}@if($allSales) · {{ $sale->salesperson_id === $user->id ? 'You' : $sale->salesperson?->name }}@endif
                                </p>
                            </div>
                            <span class="shrink-0 font-semibold tabular-nums">@money($sale->total_amount)</span>
                        </li>
                    @empty
                        <li class="py-3 text-sm text-text-secondary">No completed sales yet.</li>
                    @endforelse
                </ul>
            </section>
        @endif
        @if($today['orders'] !== null)
            <section class="{{ $panel }}" aria-labelledby="recent-orders-heading">
                <div class="flex items-center justify-between gap-3">
                    <h2 id="recent-orders-heading" class="{{ $panelTitle }}">Recent orders</h2>
                    <a href="{{ route('orders.index') }}" class="text-sm font-semibold underline underline-offset-4">View all</a>
                </div>
                <ul class="mt-4 divide-y divide-border">
                    @forelse($recentOrders as $order)
                        <li class="flex items-center justify-between gap-4 py-3 text-sm">
                            <div class="min-w-0">
                                <a href="{{ route('orders.show', $order) }}" class="font-semibold underline-offset-4 hover:underline">{{ $order->customer?->full_name ?? 'Customer' }}</a>
                                <p class="mt-0.5 text-xs text-text-secondary">{{ $order->order_number }} · @money($order->total_amount)</p>
                            </div>
                            <x-badge :tone="$statusTone($order->status)" class="shrink-0">{{ $statusLabel($order->status) }}</x-badge>
                        </li>
                    @empty
                        <li class="py-3 text-sm text-text-secondary">No orders yet.</li>
                    @endforelse
                </ul>
            </section>
        @endif

        @if($topCustomers->isNotEmpty())
            <section class="{{ $panel }}" aria-labelledby="customers-heading">
                <h2 id="customers-heading" class="{{ $panelTitle }}">Top customers this month</h2>
                <p class="mt-1 text-xs text-text-secondary">Completed sales before refunds and exchanges. Walk-ins are not included.</p>
                <ol class="mt-4 divide-y divide-border">
                    @foreach($topCustomers as $customer)
                        <li class="flex items-center justify-between gap-4 py-3 text-sm">
                            <span class="flex min-w-0 items-center gap-3">
                                <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-selected text-xs font-bold" aria-hidden="true">{{ $loop->iteration }}</span>
                                <a href="{{ route('customers.show', $customer['id']) }}" class="break-words font-semibold underline-offset-4 hover:underline">{{ $customer['name'] }}</a>
                            </span>
                            <span class="shrink-0 font-semibold tabular-nums">@money($customer['revenue'])</span>
                        </li>
                    @endforeach
                </ol>
            </section>
        @endif

        @if($stock)
            <section class="{{ $panel }}" aria-labelledby="stock-heading">
                <div class="flex items-center justify-between gap-3">
                    <h2 id="stock-heading" class="{{ $panelTitle }}">Stock at a glance</h2>
                    @can('inventory.view')<a href="{{ route('inventory.index') }}" class="text-sm font-semibold underline underline-offset-4">Inventory</a>@endcan
                </div>
                <dl class="mt-4 grid grid-cols-2 gap-4 text-sm">
                    <div class="rounded-xl bg-background p-4"><dt class="text-text-secondary">Ready to sell</dt><dd class="mt-1 text-2xl font-bold tabular-nums">{{ number_format($stock['available']) }}</dd><dd class="text-xs text-text-secondary">units</dd></div>
                    <div class="rounded-xl bg-background p-4"><dt class="text-text-secondary">Held for orders</dt><dd class="mt-1 text-2xl font-bold tabular-nums">{{ number_format($stock['reserved']) }}</dd><dd class="text-xs text-text-secondary">of {{ number_format($stock['physical']) }} in the shop</dd></div>
                    <div class="rounded-xl bg-background p-4"><dt class="text-text-secondary">Running low</dt><dd class="mt-1 text-2xl font-bold tabular-nums">{{ number_format($stock['low']) }}</dd><dd class="text-xs text-text-secondary">items</dd></div>
                    <div class="rounded-xl bg-background p-4"><dt class="text-text-secondary">Out of stock</dt><dd @class(['mt-1 text-2xl font-bold tabular-nums', 'text-danger' => $stock['out'] > 0])>{{ number_format($stock['out']) }}</dd><dd class="text-xs text-text-secondary">items · {{ number_format($stock['products']) }} products listed</dd></div>
                </dl>
            </section>
        @endif
    </div>
</x-layouts.app>
