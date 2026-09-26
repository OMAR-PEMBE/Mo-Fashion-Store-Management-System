@php
    $editing = $customer->exists;
    $chosen = array_map('intval', (array) old('category_ids', $customer->categories->modelKeys()));
@endphp
<x-layouts.app :title="$editing ? 'Edit customer' : 'Add customer'">
    <x-page-header :title="$editing ? 'Edit '.$customer->full_name : 'Add customer'" :back="$editing ? route('customers.show', $customer) : route('customers.index')" :back-label="$editing ? $customer->full_name : 'Customers'"
        :description="$editing ? null : 'Only name is required. Everything else helps you serve and follow up with them.'" />

    <form method="POST" action="{{ $editing ? route('customers.update', $customer) : route('customers.store') }}" class="max-w-3xl space-y-6" data-busy>
        @csrf @if($editing) @method('PUT') @endif
        @if($errors->any())<div role="alert" class="rounded-xl border border-danger/30 bg-danger/5 p-4 text-sm text-danger">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif

        <section class="space-y-5 rounded-2xl border border-border bg-surface p-5 sm:p-6" aria-labelledby="contact-heading">
            <h2 id="contact-heading" class="text-base font-semibold">Contact</h2>
            <x-input name="full_name" label="Full name" :value="old('full_name', $customer->full_name)" maxlength="191" required />
            <div class="grid gap-5 sm:grid-cols-2">
                <x-input name="phone" label="Phone (optional)" type="tel" :value="old('phone', $customer->phone)" maxlength="30" placeholder="For example 0755 123 456" />
                <x-input name="whatsapp_number" label="WhatsApp (optional, if different)" type="tel" :value="old('whatsapp_number', $customer->whatsapp_number)" maxlength="30" />
            </div>
            <p class="-mt-2 text-xs text-text-secondary">Spaces, brackets and dashes are removed when saved, so numbers are easy to match.</p>
            @error('allow_duplicate')<label class="flex items-start gap-3 rounded-lg border border-warning bg-warning/10 p-3 text-sm"><input type="checkbox" name="allow_duplicate" value="1" required class="mt-0.5"> I checked the existing profile and want to save this as a separate customer.</label>@enderror
            <x-input name="location" label="Area or address (optional)" :value="old('location', $customer->location)" maxlength="255" />
        </section>

        <section class="space-y-5 rounded-2xl border border-border bg-surface p-5 sm:p-6" aria-labelledby="likes-heading">
            <h2 id="likes-heading" class="text-base font-semibold">What they like</h2>
            <fieldset>
                <legend class="mb-2 text-sm font-medium">Categories</legend>
                <div class="flex flex-wrap gap-2">
                    @forelse($categories as $category)
                        <label class="cursor-pointer rounded-full border border-border bg-surface px-3.5 py-1.5 text-sm font-semibold hover:bg-background has-[:checked]:border-text-primary has-[:checked]:bg-text-primary has-[:checked]:text-white has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-info">
                            <input type="checkbox" name="category_ids[]" value="{{ $category->id }}" class="sr-only" @checked(in_array($category->id, $chosen, true))>{{ $category->name }}
                        </label>
                    @empty
                        <p class="text-sm text-text-secondary">No active categories yet.</p>
                    @endforelse
                </div>
            </fieldset>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-select name="preferred_size_id" label="Usual size"><option value="">No preference</option>@foreach($sizes as $size)<option value="{{ $size->id }}" @selected((string) old('preferred_size_id', $customer->preferred_size_id) === (string) $size->id)>{{ $size->name }}</option>@endforeach</x-select>
                <x-select name="preferred_colour_id" label="Favourite colour"><option value="">No preference</option>@foreach($colours as $colour)<option value="{{ $colour->id }}" @selected((string) old('preferred_colour_id', $customer->preferred_colour_id) === (string) $colour->id)>{{ $colour->name }}</option>@endforeach</x-select>
            </div>
            <div><label for="notes" class="mb-2 block text-sm font-medium">Notes (optional)</label><textarea id="notes" name="notes" rows="3" maxlength="5000" class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm">{{ old('notes', $customer->notes) }}</textarea></div>
        </section>

        <section class="space-y-4 rounded-2xl border border-border bg-surface p-5 sm:p-6" aria-labelledby="consent-heading">
            <h2 id="consent-heading" class="text-base font-semibold">Consent{{ $editing ? ' and status' : '' }}</h2>
            <input type="hidden" name="marketing_opt_in" value="0">
            <label class="flex items-start gap-3 text-sm"><input type="checkbox" name="marketing_opt_in" value="1" class="mt-0.5 size-4" @checked(old('marketing_opt_in', $customer->marketing_opt_in))><span><span class="block font-semibold">The customer agreed to receive offers and news</span><span class="block text-text-secondary">Only tick this if they said yes. It can be switched off at any time.</span></span></label>
            @if($editing)
                <input type="hidden" name="is_active" value="0">
                <label class="flex items-start gap-3 text-sm"><input type="checkbox" name="is_active" value="1" class="mt-0.5 size-4" @checked(old('is_active', $customer->is_active))><span><span class="block font-semibold">Active customer</span><span class="block text-text-secondary">Inactive customers keep their history but cannot be chosen for new sales or orders.</span></span></label>
            @endif
        </section>

        <div class="flex flex-wrap items-center gap-4">
            <x-button type="submit" data-busy-label="Saving…">{{ $editing ? 'Save changes' : 'Save customer' }}</x-button>
            <a href="{{ $editing ? route('customers.show', $customer) : route('customers.index') }}" class="text-sm font-semibold underline underline-offset-4">Cancel</a>
        </div>
    </form>
</x-layouts.app>
