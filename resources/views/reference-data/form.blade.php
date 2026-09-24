<x-layouts.app :title="($record->exists ? 'Edit ' : 'Add ').$type->singular()">
    <a href="{{ route('reference.index', $type->value) }}" class="mb-5 inline-block text-sm underline underline-offset-4">Back to {{ strtolower($type->label()) }}</a>
    <h1 class="mb-7 text-3xl font-bold">{{ $record->exists ? 'Edit' : 'Add' }} {{ $type->singular() }}</h1>
    <div class="max-w-2xl">
        <x-card>
            <form method="POST" action="{{ $record->exists ? route('reference.update', [$type->value, $record->id]) : route('reference.store', $type->value) }}" class="space-y-6">
                @csrf
                @if($record->exists) @method('PUT') @endif
                @if($errors->any())<p role="alert" class="text-sm text-danger">Please correct the highlighted fields.</p>@endif
                <x-input name="name" label="Name" :value="old('name', $record->name)" required :maxlength="match($type->value) { 'categories' => 150, 'sizes' => 50, default => 100 }" autofocus />
                @if($type->value === 'categories')
                    <x-input name="slug" label="Slug" :value="old('slug', $record->slug)" help="A unique identifier, such as official-wear. Use letters, numbers and hyphens." required maxlength="160" />
                    <div>
                        <label for="description" class="mb-2 block text-sm font-medium">Description (optional)</label>
                        <textarea id="description" name="description" rows="4" maxlength="5000" class="w-full rounded-lg border border-border px-3 py-2 text-sm" @if($errors->has('description')) aria-invalid="true" aria-describedby="description-error" @endif>{{ old('description', $record->description) }}</textarea>
                        @error('description')<p id="description-error" class="mt-2 text-sm text-danger">{{ $message }}</p>@enderror
                    </div>
                @elseif($type->value === 'sizes')
                    <x-input name="code" label="Code" :value="old('code', $record->code)" help="A unique code. Letters and numbers may be separated by hyphens or underscores. Saved in uppercase." required :maxlength="$type->value === 'sizes' ? 50 : 100" />
                @endif
                @if($type->value === 'sizes')
                    <x-input name="sort_order" label="Display order" type="number" :value="old('sort_order', $record->sort_order ?? 0)" help="Lower numbers appear first." required min="0" max="2147483647" step="1" />
                @endif
                <x-select name="is_active" label="Status" required>
                    <option value="1" @selected((string) old('is_active', (int) $record->is_active) === '1')>Active</option>
                    <option value="0" @selected((string) old('is_active', (int) $record->is_active) === '0')>Inactive</option>
                </x-select>
                <p class="text-sm text-text-secondary">Deactivate entries you no longer use. Existing records are preserved, and you can reactivate them later.</p>
                <div class="flex items-center gap-5 border-t border-border pt-5">
                    <x-button type="submit">{{ $record->exists ? 'Save changes' : 'Create '.$type->singular() }}</x-button>
                    <a href="{{ route('reference.index', $type->value) }}" class="text-sm underline">Cancel</a>
                </div>
            </form>
        </x-card>
    </div>
</x-layouts.app>
