<x-layouts.app :title="$expense->exists ? 'Edit expense' : 'Record expense'">
    <a href="{{ route('expenses.index') }}" class="text-sm underline">Expenses</a>
    <h1 class="mb-6 mt-2 text-3xl font-bold">{{ $expense->exists ? 'Edit expense' : 'Record expense' }}</h1>
    <p class="mb-6 text-sm text-text-secondary">Record operating costs such as rent, electricity and packaging. Use Purchases for supplier stock. Delivery fees are outside Version 1 accounting.</p>
    <x-card><form method="POST" action="{{ $expense->exists ? route('expenses.update', $expense) : route('expenses.store') }}" class="space-y-5">
        @csrf
        @if($expense->exists) @method('PUT')<input type="hidden" name="revision" value="{{ old('revision', $expense->revision) }}">
        @else<input type="hidden" name="request_key" value="{{ old('request_key', $requestKey) }}">@endif
        @if($errors->any())<div role="alert" class="text-sm text-danger">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
        <div class="grid gap-5 sm:grid-cols-2">
            <x-select name="expense_category_id" label="Category" required><option value="">Select a category</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(old('expense_category_id', $expense->expense_category_id) == $category->id)>{{ $category->name }}{{ $category->is_active ? '' : ' (inactive — existing entry)' }}</option>@endforeach</x-select>
            <x-input name="expense_date" label="Expense date" type="date" :value="old('expense_date', $expense->expense_date?->format('Y-m-d') ?? now()->format('Y-m-d'))" required min="1000-01-01" max="9999-12-31" />
            <x-input name="amount" label="Amount (TZS)" type="number" :value="old('amount', $expense->amount)" required min="0" max="9999999999999.99" step="0.01" />
        </div>
        <div><label for="description" class="mb-2 block text-sm font-medium">Description (optional)</label><textarea id="description" name="description" rows="4" maxlength="5000" class="w-full rounded-lg border border-border px-3 py-2 text-sm">{{ old('description', $expense->description) }}</textarea></div>
        <p class="text-sm text-text-secondary">{{ $expense->exists ? 'Changes retain the original recorder and preserve previous values in audit history.' : 'Saving records the expense; it does not send a payment.' }}</p>
        <x-button type="submit">{{ $expense->exists ? 'Save changes' : 'Record expense' }}</x-button>
    </form></x-card>
</x-layouts.app>
