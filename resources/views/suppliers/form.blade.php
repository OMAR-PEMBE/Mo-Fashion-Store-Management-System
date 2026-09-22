<x-layouts.app :title="$supplier->exists ? 'Edit supplier' : 'Add supplier'">
    <a href="{{ $supplier->exists ? route('suppliers.show', $supplier) : route('suppliers.index') }}" class="mb-5 inline-block text-sm underline">Back to {{ $supplier->exists ? 'supplier' : 'suppliers' }}</a>
    <h1 class="mb-7 text-3xl font-bold">{{ $supplier->exists ? 'Edit supplier' : 'Add supplier' }}</h1>
    <x-card class="max-w-2xl"><form method="POST" action="{{ $supplier->exists ? route('suppliers.update', $supplier) : route('suppliers.store') }}" class="space-y-6">
        @csrf @if($supplier->exists) @method('PUT') @endif
        @if($errors->any())<p role="alert" class="text-sm text-danger">Please correct the highlighted fields.</p>@endif
        <x-input name="name" label="Supplier / business name" :value="old('name', $supplier->name)" required maxlength="191" autofocus />
        <x-input name="supplier_code" label="Supplier code" :value="old('supplier_code', $supplier->supplier_code)" required maxlength="100" help="A unique code, for example SUP-001. Use letters, numbers, hyphens or underscores. Saved in uppercase." />
        <x-input name="contact_person" label="Contact person (optional)" :value="old('contact_person', $supplier->contact_person)" maxlength="150" />
        <div class="grid gap-6 sm:grid-cols-2"><x-input name="phone" label="Phone (optional)" type="tel" :value="old('phone', $supplier->phone)" maxlength="30" /><x-input name="email" label="Email (optional)" type="email" :value="old('email', $supplier->email)" maxlength="191" /></div>
        <x-input name="location" label="Location (optional)" :value="old('location', $supplier->location)" maxlength="255" />
        <div><label for="notes" class="mb-2 block text-sm font-medium">Notes (optional)</label><textarea id="notes" name="notes" rows="4" maxlength="5000" class="w-full rounded-lg border border-border px-3 py-2 text-sm" @if($errors->has('notes')) aria-invalid="true" aria-describedby="notes-error" @endif>{{ old('notes', $supplier->notes) }}</textarea>@error('notes')<p id="notes-error" class="mt-2 text-sm text-danger">{{ $message }}</p>@enderror</div>
        <x-select name="is_active" label="Status"><option value="1" @selected((string) old('is_active', (int) $supplier->is_active) === '1')>Active</option><option value="0" @selected((string) old('is_active', (int) $supplier->is_active) === '0')>Inactive</option></x-select>
        <p class="text-sm text-text-secondary">Deactivate suppliers you no longer use. Their details are preserved, and you can reactivate them later.</p>
        <div class="flex items-center gap-5 border-t border-border pt-5"><x-button type="submit">{{ $supplier->exists ? 'Save changes' : 'Create supplier' }}</x-button><a href="{{ route('suppliers.index') }}" class="text-sm underline">Cancel</a></div>
    </form></x-card>
</x-layouts.app>
