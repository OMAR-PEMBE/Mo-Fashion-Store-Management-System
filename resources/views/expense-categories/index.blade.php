<x-layouts.app title="Expense categories">
    <x-page-header title="Expense categories" :back="route('expenses.index')" back-label="Expenses" description="The headings your running costs are grouped under, such as Rent or Electricity.">
        <x-slot:actions><x-action-link :href="route('expense-categories.create')">Add category</x-action-link></x-slot:actions>
    </x-page-header>

    <form method="GET" class="mb-4" role="search">
        @if($filters['status'] ?? null)<input type="hidden" name="status" value="{{ $filters['status'] }}">@endif
        <div class="relative max-w-md">
            <label for="category-search" class="sr-only">Search categories</label>
            <svg class="pointer-events-none absolute top-1/2 left-3.5 size-5 -translate-y-1/2 text-text-secondary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <input id="category-search" name="q" type="search" value="{{ $filters['q'] ?? '' }}" maxlength="150" placeholder="Category name" class="min-h-11 w-full rounded-lg border border-border bg-surface py-2 pr-3 pl-11 text-sm">
        </div>
    </form>
    <x-filter-tabs :options="['' => 'All', 'active' => 'In use', 'inactive' => 'Switched off']" :counts="$counts" class="mb-5" />

    <div class="overflow-hidden rounded-2xl border border-border bg-surface">
        @if($categories->isEmpty())
            <div class="p-10 text-center"><p class="font-semibold">No expense categories found.</p><p class="mt-1 text-sm text-text-secondary">Add headings like Rent, Electricity, Transport or Packaging.</p></div>
        @else
            <ul class="divide-y divide-border">
                @foreach($categories as $category)
                    <li class="relative flex items-start justify-between gap-4 px-5 py-4 hover:bg-selected/50">
                        <div class="min-w-0">
                            <a href="{{ route('expense-categories.edit', $category) }}" class="row-link break-words">{{ $category->name }}</a>
                            @unless($category->is_active)<x-badge tone="neutral" class="ml-2">Switched off</x-badge>@endunless
                            @if($category->description)<p class="mt-0.5 text-sm break-words text-text-secondary">{{ $category->description }}</p>@endif
                            <p class="mt-0.5 text-xs text-text-secondary">{{ $category->expenses_count ? number_format($category->expenses_count).' '.\Illuminate\Support\Str::plural('expense', $category->expenses_count).' · last on '.\Illuminate\Support\Carbon::parse($category->expenses_max_expense_date)->format('j M Y') : 'Not used yet' }}</p>
                        </div>
                        @if($category->expenses_count)<p class="shrink-0 text-right text-sm"><span class="block text-xs text-text-secondary">All time</span><span class="font-semibold tabular-nums">@money(\App\Support\Money::round($category->expenses_sum_amount))</span></p>@endif
                    </li>
                @endforeach
            </ul>
        @endif
        @if($categories->hasPages())<div class="border-t border-border p-4">{{ $categories->links() }}</div>@endif
    </div>
</x-layouts.app>
