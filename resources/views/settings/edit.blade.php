@php
    $field = fn ($key) => (string) old($key, $values[$key]);
    $textarea = 'w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm';
@endphp
<x-layouts.app title="Business settings">
    <x-page-header title="Business settings" description="Your shop's details. They appear on screen and on every receipt." />

    @if($errors->any())<div role="alert" class="mb-6 rounded-xl border border-danger/30 bg-danger/5 p-4 text-sm text-danger">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif

    <form method="POST" action="{{ route('settings.update') }}" data-busy class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_340px] lg:items-start"
        x-data="{ name: @js($field('business_name')), phone: @js($field('business_phone')), address: @js($field('business_address')), footer: @js($field('receipt_footer')) }">
        @csrf @method('PATCH')<input type="hidden" name="revision" value="{{ old('revision', $revision) }}">
        {{-- Fixed in this version, but the server still expects them with every save. --}}
        <input type="hidden" name="currency" value="TZS"><input type="hidden" name="timezone" value="Africa/Dar_es_Salaam">

        <div class="space-y-6">
            <section class="space-y-5 rounded-2xl border border-border bg-surface p-5 sm:p-6" aria-labelledby="shop-heading">
                <h2 id="shop-heading" class="text-base font-semibold">Shop details</h2>
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-input name="business_name" label="Shop name" :value="$field('business_name')" required maxlength="150" x-on:input="name = $event.target.value" />
                    <x-input name="business_phone" label="Phone" type="tel" :value="$field('business_phone')" maxlength="30" placeholder="0755 123 456" x-on:input="phone = $event.target.value" />
                </div>
                <div><label for="business_address" class="mb-2 block text-sm font-medium">Address</label><textarea id="business_address" name="business_address" rows="2" maxlength="500" placeholder="For example: Shop 12, Kariakoo Market, Dar es Salaam" class="{{ $textarea }}" @input="address = $event.target.value">{{ $field('business_address') }}</textarea></div>
            </section>

            <section class="space-y-5 rounded-2xl border border-border bg-surface p-5 sm:p-6" aria-labelledby="receipt-heading">
                <h2 id="receipt-heading" class="text-base font-semibold">Receipts</h2>
                <div><label for="receipt_footer" class="mb-2 block text-sm font-medium">Note at the bottom of every receipt</label><textarea id="receipt_footer" name="receipt_footer" rows="3" maxlength="500" placeholder="For example: Asante kwa kununua! Exchanges within 3 days with this receipt." class="{{ $textarea }}" @input="footer = $event.target.value">{{ $field('receipt_footer') }}</textarea>
                    <p class="mt-1 text-xs text-text-secondary">A thank-you, your return policy or social media handle.</p></div>
            </section>

            <section class="space-y-5 rounded-2xl border border-border bg-surface p-5 sm:p-6" aria-labelledby="stock-heading">
                <h2 id="stock-heading" class="text-base font-semibold">Stock</h2>
                <div class="max-w-xs"><x-input name="low_stock_default" label="Restock at (for new items)" type="number" :value="$field('low_stock_default')" required min="0" max="2147483647" help="New sizes and colours start with this. Items you already have keep their own number." /></div>
            </section>

            <section class="rounded-2xl border border-border bg-background/60 p-5 text-sm" aria-labelledby="fixed-heading">
                <h2 id="fixed-heading" class="font-semibold">Fixed for this version</h2>
                <dl class="mt-2 grid gap-2 sm:grid-cols-2">
                    <div><dt class="text-text-secondary">Currency</dt><dd class="font-semibold">Tanzanian shilling (TZS)</dd></div>
                    <div><dt class="text-text-secondary">Time zone</dt><dd class="font-semibold">Dar es Salaam (EAT, UTC+3)</dd></div>
                </dl>
                <p class="mt-2 text-xs text-text-secondary">These stay the same so past sales and reports keep adding up.</p>
            </section>

            <section class="max-w-xl space-y-4 rounded-2xl border border-border bg-surface p-5 sm:p-6">
                <x-input name="current_password" label="Your password, to confirm" type="password" required autocomplete="current-password" maxlength="255" revealable />
                <x-button type="submit" data-busy-label="Saving…">Save settings</x-button>
            </section>
        </div>

        <aside class="lg:sticky lg:top-6" aria-labelledby="preview-heading">
            <h2 id="preview-heading" class="mb-2 text-xs font-semibold tracking-widest text-text-secondary uppercase">Receipt preview</h2>
            <div class="rounded-2xl border border-border bg-white p-6 font-mono text-xs leading-5 text-text-primary shadow-sm" aria-live="polite">
                <p class="text-center text-sm font-bold break-words" x-text="name || 'Your shop name'"></p>
                <p class="text-center break-words whitespace-pre-line text-text-secondary" x-show="address" x-text="address"></p>
                <p class="text-center text-text-secondary" x-show="phone" x-text="'Tel: ' + phone"></p>
                <div class="my-3 border-t border-dashed border-border"></div>
                <p class="flex justify-between"><span>MFS-SAL-000123</span><span>{{ now()->format('d/m/Y H:i') }}</span></p>
                <div class="my-3 border-t border-dashed border-border"></div>
                <p class="flex justify-between gap-2"><span>Boyfriend Jeans M · Blue</span><span>45,000</span></p>
                <p class="flex justify-between gap-2"><span>Linen Shirt L · White ×2</span><span>76,000</span></p>
                <div class="my-3 border-t border-dashed border-border"></div>
                <p class="flex justify-between font-bold"><span>TOTAL</span><span>TZS 121,000</span></p>
                <p class="flex justify-between text-text-secondary"><span>Paid by</span><span>M-Pesa</span></p>
                <template x-if="footer"><div><div class="my-3 border-t border-dashed border-border"></div><p class="text-center break-words whitespace-pre-line" x-text="footer"></p></div></template>
            </div>
            <p class="mt-2 text-xs text-text-secondary">Sample items. Your real receipts use the same header and note.</p>
        </aside>
    </form>
</x-layouts.app>
