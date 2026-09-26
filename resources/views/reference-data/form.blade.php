@php
    $editing = $record->exists;
    $active = (string) old('is_active', (int) $record->is_active) === '1';
    $nameMax = match ($type->value) { 'categories' => 150, 'sizes' => 50, default => 100 };
    // New entries: the code follows the name until the owner types their own.
    $identifier = $type->value === 'categories' ? 'slug' : ($type->value === 'sizes' ? 'code' : null);
    $auto = ! $editing && $identifier && blank(old($identifier));
@endphp
<x-layouts.app :title="($editing ? 'Edit ' : 'Add ').$type->singular()">
    <x-page-header :title="$editing ? 'Edit '.$record->name : 'Add '.$type->singular()" :back="route('reference.index', $type->value)" :back-label="$type->label()" />

    <form method="POST" action="{{ $editing ? route('reference.update', [$type->value, $record->id]) : route('reference.store', $type->value) }}" class="max-w-xl space-y-6 rounded-2xl border border-border bg-surface p-5 sm:p-6" data-busy
        x-data="{ auto: @js($auto), slugify(v) { return v.normalize('NFKD').replace(/[̀-ͯ]/g, '').trim(){{ $type->value === 'sizes' ? ".toUpperCase().replace(/[^A-Z0-9]+/g, '-')" : ".toLowerCase().replace(/[^a-z0-9]+/g, '-')" }}.replace(/^-+|-+$/g, '') } }">
        @csrf @if($editing) @method('PUT') @endif
        @if($errors->any())<p role="alert" class="rounded-xl border border-danger/30 bg-danger/5 p-4 text-sm text-danger">Please correct the highlighted fields.</p>@endif

        <x-input name="name" label="Name" :value="old('name', $record->name)" required :maxlength="$nameMax" autofocus
            :placeholder="match ($type->value) { 'categories' => 'For example Official wear', 'sizes' => 'For example XL or 42', default => 'For example Navy' }"
            x-on:input="if (auto && $refs.identifier) $refs.identifier.value = slugify($event.target.value)" />

        @if($type->value === 'categories')
            <x-input name="slug" label="Short code" :value="old('slug', $record->slug)" required maxlength="160" x-ref="identifier" x-on:input="auto = false"
                :help="$editing ? 'Lower-case letters, numbers and hyphens.' : 'Filled in from the name. Lower-case letters, numbers and hyphens.'" />
            <div>
                <label for="description" class="mb-2 block text-sm font-medium">What goes in it (optional)</label>
                <textarea id="description" name="description" rows="3" maxlength="5000" class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm" @if($errors->has('description')) aria-invalid="true" aria-describedby="description-error" @endif>{{ old('description', $record->description) }}</textarea>
                @error('description')<p id="description-error" class="mt-2 text-sm text-danger">{{ $message }}</p>@enderror
            </div>
        @elseif($type->value === 'sizes')
            <div class="grid gap-5 sm:grid-cols-2">
                <x-input name="code" label="Code" :value="old('code', $record->code)" required maxlength="50" x-ref="identifier" x-on:input="auto = false"
                    :help="$editing ? 'Saved in capitals.' : 'Filled in from the name. Saved in capitals.'" />
                <x-input name="sort_order" label="Position in lists" type="number" :value="old('sort_order', $record->sort_order ?? 0)" required min="0" max="2147483647" step="1" help="Lower numbers come first." />
            </div>
        @else
            <p class="text-sm text-text-secondary">A code is made from the name automatically.</p>
        @endif

        <input type="hidden" name="is_active" value="0">
        <label class="flex items-start gap-3 text-sm"><input type="checkbox" name="is_active" value="1" class="mt-0.5 size-4" @checked($active)><span><span class="block font-semibold">Available for new products</span><span class="block text-text-secondary">Switch off entries you no longer use. Products already using it keep it.</span></span></label>

        <div class="flex flex-wrap items-center gap-4">
            <x-button type="submit" data-busy-label="Saving…">{{ $editing ? 'Save changes' : 'Add '.$type->singular() }}</x-button>
            <a href="{{ route('reference.index', $type->value) }}" class="text-sm font-semibold underline underline-offset-4">Cancel</a>
        </div>
    </form>
</x-layouts.app>
