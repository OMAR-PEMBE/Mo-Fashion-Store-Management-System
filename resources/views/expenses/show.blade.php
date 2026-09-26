@php
    $fields = ['amount' => 'Amount', 'expense_date' => 'Date paid', 'category_name' => 'Category', 'description' => 'Details'];
    $show = function (string $field, $value) {
        if ($value === null || $value === '') {
            return 'empty';
        }

        return match ($field) {
            'amount' => \App\Support\Money::format($value),
            'expense_date' => \Illuminate\Support\Carbon::parse($value)->format('j M Y'),
            default => $value,
        };
    };
@endphp
<x-layouts.app :title="$expense->expense_number">
    <x-page-header :title="$expense->category->name.' · '.\App\Support\Money::format($expense->amount)" :back="route('expenses.index')" back-label="Expenses"
        :description="$expense->expense_number.' · paid '.$expense->expense_date->format('j M Y').' · recorded by '.$expense->recorder->name">
        <x-slot:actions>@can('expenses.update')<a href="{{ route('expenses.edit', $expense) }}" class="inline-flex min-h-11 items-center rounded-lg border border-border bg-surface px-4 text-sm font-semibold hover:bg-background">Edit</a>@endcan</x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px] lg:items-start">
        <section class="rounded-2xl border border-border bg-surface p-5 sm:p-6" aria-labelledby="details-heading">
            <h2 id="details-heading" class="text-base font-semibold">Details</h2>
            <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                <div><dt class="text-text-secondary">Amount</dt><dd class="mt-1 text-2xl font-bold tabular-nums">@money($expense->amount)</dd></div>
                <div><dt class="text-text-secondary">Category</dt><dd class="mt-1 font-semibold">{{ $expense->category->name }}</dd></div>
                <div><dt class="text-text-secondary">Date paid</dt><dd class="mt-1">{{ $expense->expense_date->format('l, j F Y') }}</dd></div>
                <div><dt class="text-text-secondary">Recorded by</dt><dd class="mt-1">{{ $expense->recorder->name }}</dd></div>
                <div class="sm:col-span-2"><dt class="text-text-secondary">Details</dt><dd class="mt-1 whitespace-pre-wrap break-words">{{ $expense->description ?? 'No details given.' }}</dd></div>
            </dl>
        </section>

        <section class="rounded-2xl border border-border bg-surface p-5" aria-labelledby="history-heading">
            <h2 id="history-heading" class="text-base font-semibold">History</h2>
            <ol class="mt-4 space-y-5 border-l-2 border-border pl-4">
                @foreach($history as $entry)
                    @php
                        $before = $entry->old_values ? json_decode($entry->old_values, true) : null;
                        $after = json_decode($entry->new_values, true) ?? [];
                        $changes = $before ? collect($fields)->filter(fn ($label, $field) => ($before[$field] ?? null) != ($after[$field] ?? null)) : collect();
                    @endphp
                    <li class="text-sm">
                        <p class="font-semibold">{{ $entry->action === 'CREATE_EXPENSE' ? 'Recorded' : 'Changed' }} by {{ $entry->actor_name ?? 'someone' }}</p>
                        <p class="text-xs text-text-secondary">{{ \Illuminate\Support\Carbon::parse($entry->created_at)->format('j M Y, H:i') }}</p>
                        @if($before)
                            <ul class="mt-2 space-y-1">
                                @forelse($changes as $field => $label)
                                    <li class="break-words">{{ $label }}: <span class="text-text-secondary line-through">{{ $show($field, $before[$field] ?? null) }}</span> → <span class="font-semibold">{{ $show($field, $after[$field] ?? null) }}</span></li>
                                @empty
                                    <li class="text-text-secondary">Saved with no changes.</li>
                                @endforelse
                            </ul>
                        @else
                            <p class="mt-1 text-text-secondary">{{ $show('amount', $after['amount'] ?? null) }} for {{ $after['category_name'] ?? 'unknown category' }}, paid {{ $show('expense_date', $after['expense_date'] ?? null) }}.</p>
                        @endif
                    </li>
                @endforeach
            </ol>
            @if($history->hasPages())<div class="mt-4">{{ $history->links() }}</div>@endif
        </section>
    </div>
</x-layouts.app>
