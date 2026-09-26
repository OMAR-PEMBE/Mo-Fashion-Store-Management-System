@php
    $editing = $supplier->exists;
@endphp
<x-layouts.app :title="$editing ? 'Edit supplier' : 'Add supplier'">
    <x-page-header :title="$editing ? 'Edit '.$supplier->name : 'Add supplier'" :back="$editing ? route('suppliers.show', $supplier) : route('suppliers.index')" :back-label="$editing ? $supplier->name : 'Suppliers'" />

    <form method="POST" action="{{ $editing ? route('suppliers.update', $supplier) : route('suppliers.store') }}" class="max-w-2xl space-y-6" data-busy>
        @csrf @if($editing) @method('PUT') @endif
        @if($errors->any())<p role="alert" class="rounded-xl border border-danger/30 bg-danger/5 p-4 text-sm text-danger">Please correct the highlighted fields.</p>@endif

        <section class="space-y-5 rounded-2xl border border-border bg-surface p-5 sm:p-6" aria-labelledby="business-heading">
            <h2 id="business-heading" class="text-base font-semibold">Business</h2>
            <x-input name="name" label="Business name" :value="old('name', $supplier->name)" required maxlength="191" autofocus />
            <x-input name="supplier_code" label="Supplier code" :value="old('supplier_code', $supplier->supplier_code)" required maxlength="100"
                :help="$editing ? 'Letters, numbers, hyphens or underscores. Saved in capitals.' : 'Filled in for you. Change it if you use your own codes.'" />
            <x-input name="location" label="Location (optional)" :value="old('location', $supplier->location)" maxlength="255" placeholder="For example Kariakoo, Dar es Salaam" />
        </section>

        <section class="space-y-5 rounded-2xl border border-border bg-surface p-5 sm:p-6" aria-labelledby="contact-heading">
            <h2 id="contact-heading" class="text-base font-semibold">Who to contact</h2>
            <x-input name="contact_person" label="Contact person (optional)" :value="old('contact_person', $supplier->contact_person)" maxlength="150" />
            <div class="grid gap-5 sm:grid-cols-2">
                <x-input name="phone" label="Phone (optional)" type="tel" :value="old('phone', $supplier->phone)" maxlength="30" placeholder="0755 123 456" help="Used for the Call and WhatsApp buttons." />
                <x-input name="email" label="Email (optional)" type="email" :value="old('email', $supplier->email)" maxlength="191" />
            </div>
            <div><label for="notes" class="mb-2 block text-sm font-medium">Notes (optional)</label><textarea id="notes" name="notes" rows="3" maxlength="5000" placeholder="Payment terms, delivery days, what they sell" class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm" @if($errors->has('notes')) aria-invalid="true" aria-describedby="notes-error" @endif>{{ old('notes', $supplier->notes) }}</textarea>@error('notes')<p id="notes-error" class="mt-2 text-sm text-danger">{{ $message }}</p>@enderror</div>
        </section>

        <div class="rounded-2xl border border-border bg-surface p-5 sm:p-6">
            <input type="hidden" name="is_active" value="0">
            <label class="flex items-start gap-3 text-sm"><input type="checkbox" name="is_active" value="1" class="mt-0.5 size-4" @checked((string) old('is_active', (int) $supplier->is_active) === '1')><span><span class="block font-semibold">Still buying from them</span><span class="block text-text-secondary">Switch off for suppliers you no longer use. They disappear from the purchase form, but their history stays.</span></span></label>
            @error('is_active')<p class="mt-2 text-sm text-danger">{{ $message }}</p>@enderror
        </div>

        <div class="flex flex-wrap items-center gap-4">
            <x-button type="submit" data-busy-label="Saving…">{{ $editing ? 'Save changes' : 'Add supplier' }}</x-button>
            <a href="{{ $editing ? route('suppliers.show', $supplier) : route('suppliers.index') }}" class="text-sm font-semibold underline underline-offset-4">Cancel</a>
        </div>
    </form>
</x-layouts.app>
