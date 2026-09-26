<x-layouts.app title="Business settings">
    <h1 class="mb-6 text-3xl font-bold">Business settings</h1>
    
    @if($errors->any())<div role="alert" class="mb-5 text-sm text-danger">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <x-card><form method="POST" action="{{ route('settings.update') }}" class="space-y-6">@csrf @method('PATCH')<input type="hidden" name="revision" value="{{ old('revision', $revision) }}">
        <div class="grid gap-5 sm:grid-cols-2"><x-input name="business_name" label="Business name" :value="old('business_name', $values['business_name'])" required maxlength="150" /><x-input name="business_phone" label="Business phone" :value="old('business_phone', $values['business_phone'])" maxlength="30" /></div>
        @foreach(['business_address' => 'Business address', 'receipt_footer' => 'Receipt footer'] as $field => $label)<div><label for="{{ $field }}" class="mb-2 block text-sm font-medium">{{ $label }}</label><textarea id="{{ $field }}" name="{{ $field }}" rows="3" maxlength="500" class="w-full rounded-lg border border-border p-3 text-sm">{{ old($field, $values[$field]) }}</textarea></div>@endforeach
        <x-input name="low_stock_default" label="Default low-stock threshold" type="number" :value="old('low_stock_default', $values['low_stock_default'])" required min="0" max="2147483647" help="Used for new variants. Existing variant thresholds remain unchanged." />
        <div class="grid gap-5 sm:grid-cols-2"><x-input name="currency" label="Currency" value="TZS" readonly /><x-input name="timezone" label="Business timezone" value="Africa/Dar_es_Salaam" readonly /></div>
        <p class="text-sm text-text-secondary">Currency and timezone are fixed for Version 1 to preserve financial history. Contact details and footer appear on the sale summary.</p>
        <x-input name="current_password" label="Your administrator password" type="password" required autocomplete="current-password" maxlength="255" />
        <x-button type="submit">Save business settings</x-button>
    </form></x-card>
</x-layouts.app>