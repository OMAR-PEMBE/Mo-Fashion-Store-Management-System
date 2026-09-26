@php
    $editing = $category->exists;
    $active = (string) old('is_active', (int) ($category->is_active ?? true)) === '1';
@endphp
<x-layouts.app :title="$editing ? 'Edit expense category' : 'Add expense category'">
    <x-page-header :title="$editing ? 'Edit '.$category->name : 'Add expense category'" :back="route('expense-categories.index')" back-label="Expense categories" />

    <form method="POST" action="{{ $editing ? route('expense-categories.update', $category) : route('expense-categories.store') }}" class="max-w-xl space-y-6 rounded-2xl border border-border bg-surface p-5 sm:p-6" data-busy>
        @csrf @if($editing) @method('PUT')<input type="hidden" name="revision" value="{{ old('revision', $category->revision) }}">@endif
        @if($errors->any())<div role="alert" class="rounded-xl border border-danger/30 bg-danger/5 p-4 text-sm text-danger">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
        <x-input name="name" label="Category name" :value="old('name', $category->name)" required maxlength="150" placeholder="For example Rent" :autofocus="! $editing" />
        <x-input name="description" label="What goes here (optional)" :value="old('description', $category->description)" maxlength="5000" help="Helps staff choose the right heading." />
        <input type="hidden" name="is_active" value="0">
        <label class="flex items-start gap-3 text-sm"><input type="checkbox" name="is_active" value="1" class="mt-0.5 size-4" @checked($active)><span><span class="block font-semibold">Available for new expenses</span><span class="block text-text-secondary">Switch off headings you no longer use. Expenses already recorded under it keep it.</span></span></label>
        <div class="flex flex-wrap items-center gap-4">
            <x-button type="submit" data-busy-label="Saving…">{{ $editing ? 'Save changes' : 'Add category' }}</x-button>
            <a href="{{ route('expense-categories.index') }}" class="text-sm font-semibold underline underline-offset-4">Cancel</a>
        </div>
    </form>
</x-layouts.app>
