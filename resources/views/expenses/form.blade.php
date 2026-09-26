@php
    $editing = $expense->exists;
    $chip = 'cursor-pointer rounded-lg border border-border bg-surface px-3.5 py-2 text-sm font-semibold hover:bg-background has-[:checked]:border-text-primary has-[:checked]:bg-text-primary has-[:checked]:text-white has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-info';
    $categoryId = (string) old('expense_category_id', $expense->expense_category_id);
    $date = old('expense_date', $expense->expense_date?->format('Y-m-d') ?? now()->format('Y-m-d'));
@endphp
<x-layouts.app :title="$editing ? 'Edit expense' : 'Record expense'">
    <x-page-header :title="$editing ? 'Edit '.$expense->expense_number : 'Record expense'" :back="$editing ? route('expenses.show', $expense) : route('expenses.index')" :back-label="$editing ? $expense->expense_number : 'Expenses'"
        :description="$editing ? 'The person who recorded it stays the same, and the old values are kept in the history.' : 'Saving records the cost; it does not send any money.'" />

    <form method="POST" action="{{ $editing ? route('expenses.update', $expense) : route('expenses.store') }}" class="max-w-2xl space-y-6" data-busy
        x-data="{ amount: @js((string) old('amount', $expense->amount)) }">
        @csrf
        @if($editing) @method('PUT')<input type="hidden" name="revision" value="{{ old('revision', $expense->revision) }}">
        @else<input type="hidden" name="request_key" value="{{ old('request_key', $requestKey) }}">@endif
        @if($errors->any())<div role="alert" class="rounded-xl border border-danger/30 bg-danger/5 p-4 text-sm text-danger">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif

        <section class="space-y-5 rounded-2xl border border-border bg-surface p-5 sm:p-6">
            <div>
                <label for="amount" class="mb-2 block text-sm font-medium">Amount (TZS)</label>
                <input id="amount" name="amount" value="{{ old('amount', $expense->amount) }}" @input="amount = $event.target.value" inputmode="decimal" required maxlength="16" autocomplete="off" @unless($editing) autofocus @endunless
                    class="min-h-14 w-full rounded-lg border bg-surface px-4 py-2 text-2xl font-bold tabular-nums {{ $errors->has('amount') ? 'border-danger' : 'border-border' }}" @error('amount') aria-invalid="true" aria-describedby="amount-error" @enderror>
                <p class="mt-1 text-sm text-text-secondary" x-show="amount && Number.isFinite(Number(amount)) && Number(amount) > 0" x-text="formatMoney(Number(amount).toFixed(2))"></p>
                @error('amount')<p id="amount-error" class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
            </div>

            <fieldset>
                <legend class="mb-2 text-sm font-medium">What was it for?</legend>
                <div class="flex flex-wrap gap-2">
                    @foreach($categories as $category)
                        <label class="{{ $chip }}"><input type="radio" name="expense_category_id" value="{{ $category->id }}" class="sr-only" required @checked($categoryId === (string) $category->id)>{{ $category->name }}{{ $category->is_active ? '' : ' (inactive, kept for this entry)' }}</label>
                    @endforeach
                </div>
                @if($categories->isEmpty())<p class="text-sm text-text-secondary">No categories yet. @can('expense-categories.manage')<a href="{{ route('expense-categories.create') }}" class="font-semibold underline underline-offset-4">Add one first</a>.@endcan</p>@endif
                @error('expense_category_id')<p class="mt-2 text-sm text-danger">{{ $message }}</p>@enderror
            </fieldset>

            <div>
                <label for="expense_date" class="mb-2 block text-sm font-medium">Date paid</label>
                <div class="flex flex-wrap items-center gap-2">
                    <input id="expense_date" name="expense_date" type="date" value="{{ $date }}" x-ref="date" required min="1000-01-01" max="9999-12-31" class="min-h-11 rounded-lg border border-border bg-surface px-3 py-2 text-sm">
                    <button type="button" class="rounded-lg border border-border px-3 py-2 text-sm font-semibold hover:bg-background" @click="$refs.date.value = @js(now()->format('Y-m-d'))">Today</button>
                    <button type="button" class="rounded-lg border border-border px-3 py-2 text-sm font-semibold hover:bg-background" @click="$refs.date.value = @js(now()->subDay()->format('Y-m-d'))">Yesterday</button>
                </div>
                @error('expense_date')<p class="mt-2 text-sm text-danger">{{ $message }}</p>@enderror
            </div>

            <div><label for="description" class="mb-2 block text-sm font-medium">Details (optional)</label><textarea id="description" name="description" rows="3" maxlength="5000" placeholder="For example: LUKU tokens for September, or receipt number" class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm">{{ old('description', $expense->description) }}</textarea></div>
        </section>

        <div class="flex flex-wrap items-center gap-3">
            <x-button type="submit" data-busy-label="Saving…">{{ $editing ? 'Save changes' : 'Record expense' }}</x-button>
            @unless($editing)<x-button type="submit" variant="outline" name="add_another" value="1">Save and record another</x-button>@endunless
            <a href="{{ $editing ? route('expenses.show', $expense) : route('expenses.index') }}" class="ml-2 text-sm font-semibold underline underline-offset-4">Cancel</a>
        </div>
    </form>
</x-layouts.app>
