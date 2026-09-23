<x-layouts.app :title="$category->exists ? 'Edit expense category' : 'Add expense category'">
    <a href="{{ route('expense-categories.index') }}" class="text-sm underline">Expense categories</a><h1 class="mb-6 mt-2 text-3xl font-bold">{{ $category->exists ? 'Edit expense category' : 'Add expense category' }}</h1>
    <x-card><form method="POST" action="{{ $category->exists ? route('expense-categories.update', $category) : route('expense-categories.store') }}" class="space-y-5">@csrf @if($category->exists) @method('PUT')<input type="hidden" name="revision" value="{{ old('revision', $category->revision) }}">@endif
        @if($errors->any())<div role="alert" class="text-sm text-danger">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
        <x-input name="name" label="Category name" :value="old('name', $category->name)" required maxlength="150" />
        <x-input name="description" label="Description (optional)" :value="old('description', $category->description)" maxlength="5000" />
        <x-select name="is_active" label="Status"><option value="1" @selected(old('is_active', $category->is_active ?? true))>Active</option><option value="0" @selected(!old('is_active', $category->is_active ?? true))>Inactive</option></x-select>
        <p class="text-sm text-text-secondary">Inactive categories remain on existing expenses and cannot be selected for new entries. Use categories for operating costs, not supplier stock purchases.</p>
        <x-button type="submit">{{ $category->exists ? 'Save category' : 'Create category' }}</x-button>
    </form></x-card>
</x-layouts.app>
