@php
    $isOrder = $isOrder ?? false;
    $methods = \App\Services\SaleService::PAYMENT_METHODS;
    $config = [
        'customer' => $selectedCustomer, 'lines' => $lines, 'isOrder' => $isOrder, 'csrf' => csrf_token(),
        'lookupUrl' => route($isOrder ? 'orders.lookup' : 'sales.lookup'), 'customerUrl' => route('customers.store'),
        'canOverridePrice' => $canOverridePrice ?? false, 'payment' => old('payment_method'), 'notes' => old('notes'),
    ];
    $icon = 'size-5 shrink-0';
    $press = 'transition-transform duration-150 ease-out active:scale-[0.97] motion-reduce:transition-none motion-reduce:active:scale-100';
@endphp
<x-layouts.app :title="$isOrder ? 'New order' : 'Point of sale'">
    <form method="POST" action="{{ route($isOrder ? 'orders.store' : 'sales.review') }}" x-data="posForm(@js($config))" data-busy
        @keydown.window="if ($event.key === '/' && ! ['INPUT', 'TEXTAREA', 'SELECT'].includes($event.target.tagName)) { $event.preventDefault(); $refs.search.focus() }"
        class="pb-24 lg:grid lg:grid-cols-[minmax(0,1fr)_minmax(340px,400px)] lg:items-start lg:gap-8 lg:pb-0">
        @csrf
        <input type="hidden" name="request_key" value="{{ $requestKey }}">
        <noscript><p class="mb-4 rounded-lg border border-warning bg-warning/10 p-4 text-sm">Turn on JavaScript to search products and build the cart.</p></noscript>
        <p class="sr-only" aria-live="polite" x-text="announcement"></p>

        {{-- Products: search as you type, tap a tile to add --}}
        <section aria-labelledby="pos-heading" class="min-w-0">
            <div class="mb-5 flex items-center justify-between gap-4">
                <h1 id="pos-heading" class="text-2xl font-bold tracking-tight sm:text-3xl">{{ $isOrder ? 'New order' : 'Point of sale' }}</h1>
                <a href="{{ route($isOrder ? 'orders.index' : 'sales.index') }}" class="text-sm font-semibold underline underline-offset-4">{{ $isOrder ? 'All orders' : 'Sales history' }}</a>
            </div>
            <label for="product-search" class="sr-only">Search products by name or code</label>
            <div class="relative">
                <svg class="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-text-secondary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <input id="product-search" x-ref="search" x-model="query" @input="queueSearch()" @keydown.enter.prevent="submitSearch()" @keydown.escape="clearSearch()"
                    type="search" autocomplete="off" autocapitalize="none" spellcheck="false" maxlength="191" x-init="if (innerWidth >= 1024) $el.focus()" placeholder="Search name or scan a code"
                    aria-describedby="product-search-help" class="min-h-13 w-full rounded-xl border border-border bg-surface py-3 pr-12 pl-12 text-base shadow-sm">
                <button type="button" x-show="query" x-cloak @click="clearSearch()" class="absolute inset-y-0 right-0 flex w-12 items-center justify-center text-text-secondary hover:text-text-primary" aria-label="Clear search">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
            </div>
            <p id="product-search-help" class="mt-2 text-xs text-text-secondary">Press Enter on an exact product code to add it straight to the {{ $isOrder ? 'order' : 'sale' }}. Press / to jump back here.</p>
            <p role="alert" x-show="searchError" x-cloak x-text="searchError" class="mt-3 text-sm font-semibold text-danger"></p>

            <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-2 xl:grid-cols-3">
                <template x-for="result in results" :key="result.id">
                    <button type="button" @click="add(result)" :disabled="result.available < 1"
                        :aria-label="result.name + (result.variant ? ', ' + result.variant : '') + ', ' + formatMoney(result.unit_price) + (result.available < 1 ? ', out of stock' : ', ' + result.available + ' in stock')"
                        class="{{ $press }} relative flex min-h-32 flex-col rounded-xl border border-border bg-surface p-4 text-left hover:border-primary hover:bg-selected/40 disabled:cursor-not-allowed disabled:bg-background disabled:hover:border-border disabled:active:scale-100">
                        <span class="text-sm leading-snug font-semibold break-words" x-text="result.name"></span>
                        <span class="mt-0.5 text-xs text-text-secondary" x-text="result.variant || 'Standard'"></span>
                        <span class="mt-auto pt-3 text-base font-bold tabular-nums" x-text="formatMoney(result.unit_price)"></span>
                        <span class="mt-1 text-xs">
                            <template x-if="result.available < 1"><span class="rounded-full bg-danger/10 px-2 py-0.5 font-semibold text-danger">Out of stock</span></template>
                            <template x-if="result.available > 0 && result.low"><span class="rounded-full bg-warning/20 px-2 py-0.5 font-semibold" x-text="'Only ' + result.available + ' left'"></span></template>
                            <template x-if="result.available > 0 && ! result.low"><span class="text-text-secondary" x-text="result.available + ' in stock'"></span></template>
                        </span>
                        <span x-show="inCart(result.id)" x-cloak class="absolute top-3 right-3 rounded-full bg-text-primary px-2 py-0.5 text-xs font-bold text-white tabular-nums" x-text="inCart(result.id)?.quantity"></span>
                    </button>
                </template>
                <template x-for="i in (searching && ! results.length ? 6 : 0)" :key="'skeleton-' + i"><div class="min-h-32 animate-pulse rounded-xl bg-border/60 motion-reduce:animate-none" aria-hidden="true"></div></template>
            </div>
            <p x-show="! searching && ! searchError && ! results.length" x-cloak class="mt-6 rounded-xl border border-dashed border-border p-8 text-center text-sm text-text-secondary">
                <span x-text="query ? 'No products match “' + query + '”. Check the spelling or the code.' : 'No active products with stock information yet.'"></span>
            </p>
        </section>

        {{-- Current sale: fixed beside the products on larger screens, a full sheet on phones --}}
        <aside aria-labelledby="sale-heading" x-init="if (@js($errors->any()) && innerWidth < 1024) cartOpen = true"
            :class="cartOpen ? 'fixed inset-0 z-40 overflow-y-auto bg-background p-4 sm:p-6' : 'hidden'"
            class="lg:sticky lg:top-6 lg:block lg:max-h-[calc(100dvh-8.5rem)] lg:overflow-y-auto lg:rounded-2xl lg:border lg:border-border lg:bg-surface lg:p-0 lg:shadow-sm">
            <div class="rounded-2xl border border-border bg-surface lg:rounded-none lg:border-0">
                <div class="flex items-center justify-between gap-3 border-b border-border px-5 py-4">
                    <h2 id="sale-heading" class="text-lg font-bold">{{ $isOrder ? 'Order' : 'Current sale' }}
                        <span class="ml-1 text-sm font-medium text-text-secondary" x-show="itemCount" x-text="'(' + itemCount + (itemCount === 1 ? ' item)' : ' items)')"></span></h2>
                    <button type="button" @click="cartOpen = false" class="rounded-lg border border-border px-3 py-2 text-sm font-semibold lg:hidden">Keep shopping</button>
                </div>

                @if($errors->any())
                    <div role="alert" class="m-5 mb-0 flex gap-3 rounded-lg border border-danger/30 bg-danger/5 p-4 text-sm text-danger">
                        <svg class="{{ $icon }} mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7.5v5.5M12 16.5h.01"/></svg>
                        <div class="space-y-1">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>
                    </div>
                @endif

                {{-- Customer --}}
                <div class="border-b border-border px-5 py-4">
                    <input type="hidden" name="customer_id" :value="customer.id">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-selected" aria-hidden="true">
                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
                            </span>
                            <div class="min-w-0">
                                <p class="text-xs text-text-secondary">Customer</p>
                                <p class="text-sm font-semibold break-words" x-text="customer.label"></p>
                                <p class="text-xs text-text-secondary" x-show="customer.detail" x-text="customer.detail"></p>
                            </div>
                        </div>
                        <button type="button" @click="customerOpen = ! customerOpen; $nextTick(() => customerOpen && $refs.customerSearch.focus())" :aria-expanded="customerOpen" aria-controls="customer-panel"
                            class="{{ $press }} shrink-0 rounded-lg border border-border px-3 py-2 text-sm font-semibold hover:bg-background" x-text="customerOpen ? 'Close' : 'Change'"></button>
                    </div>
                    <div id="customer-panel" x-show="customerOpen" x-cloak class="mt-4 space-y-3">
                        <label for="customer-search" class="block text-sm font-medium">Find a registered customer</label>
                        <input id="customer-search" x-ref="customerSearch" x-model="customerQuery" @input="queueCustomerSearch()" @keydown.enter.prevent="searchCustomers()"
                            type="search" autocomplete="off" maxlength="191" placeholder="Name, phone or customer code" class="min-h-11 w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm">
                        <p x-show="customerSearching" class="text-xs text-text-secondary">Searching…</p>
                        <p x-show="customerError" x-text="customerError" class="text-xs text-text-secondary"></p>
                        <ul class="max-h-56 divide-y divide-border overflow-y-auto rounded-lg border border-border" x-show="customerResults.length">
                            <template x-for="result in customerResults" :key="result.id">
                                <li><button type="button" @click="chooseCustomer(result)" class="w-full px-3 py-2.5 text-left text-sm hover:bg-selected">
                                    <span class="block font-semibold" x-text="result.name"></span><span class="block text-xs text-text-secondary" x-text="result.detail"></span></button></li>
                            </template>
                        </ul>
                        <div class="flex flex-wrap gap-2">
                            @unless($isOrder)<button type="button" @click="walkIn()" class="{{ $press }} rounded-lg border border-border px-3 py-2 text-sm font-semibold hover:bg-background">Walk-in customer</button>@endunless
                            @can('customers.create')<button type="button" @click="startQuickCustomer()" x-show="! quick.open" class="{{ $press }} rounded-lg border border-border px-3 py-2 text-sm font-semibold hover:bg-background">+ Register new customer</button>@endcan
                        </div>
                        @can('customers.create')
                            <div x-show="quick.open" x-cloak class="space-y-3 rounded-xl bg-background p-4" role="group" aria-labelledby="quick-customer-title">
                                <p id="quick-customer-title" class="text-sm font-semibold">Register a customer</p>
                                <div>
                                    <label for="quick-name" class="mb-1 block text-xs font-medium">Full name</label>
                                    <input id="quick-name" x-ref="quickName" x-model="quick.full_name" @keydown.enter.prevent="saveQuickCustomer()" maxlength="191" class="min-h-11 w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm" :aria-invalid="Boolean(quick.errors.full_name)">
                                    <p class="mt-1 text-xs text-danger" x-show="quick.errors.full_name" x-text="(quick.errors.full_name || [])[0]"></p>
                                </div>
                                <div>
                                    <label for="quick-phone" class="mb-1 block text-xs font-medium">Phone (optional)</label>
                                    <input id="quick-phone" x-model="quick.phone" @keydown.enter.prevent="saveQuickCustomer()" type="tel" maxlength="30" class="min-h-11 w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm" :aria-invalid="Boolean(quick.errors.phone)">
                                    <p class="mt-1 text-xs text-danger" x-show="quick.errors.phone" x-text="(quick.errors.phone || [])[0]"></p>
                                </div>
                                <div x-show="quick.needsDuplicate" class="rounded-lg border border-warning bg-warning/10 p-3 text-xs">
                                    <p x-text="(quick.errors.allow_duplicate || [])[0]"></p>
                                    <label class="mt-2 flex items-start gap-2"><input type="checkbox" x-model="quick.allow_duplicate" class="mt-0.5"> I checked the existing profile and want a separate customer.</label>
                                </div>
                                <div class="flex gap-2">
                                    <button type="button" @click="saveQuickCustomer()" :disabled="quick.saving || ! quick.full_name.trim()" class="{{ $press }} rounded-lg bg-text-primary px-4 py-2 text-sm font-semibold text-white disabled:opacity-50" x-text="quick.saving ? 'Saving…' : 'Save and select'"></button>
                                    <button type="button" @click="quick.open = false" class="rounded-lg px-3 py-2 text-sm font-semibold underline underline-offset-4">Cancel</button>
                                </div>
                            </div>
                        @endcan
                    </div>
                </div>

                {{-- Items --}}
                <div class="px-5">
                    <div x-show="! lines.length" class="flex flex-col items-center py-6 text-center">
                        <svg class="size-10 text-border" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 4h2l2.4 11h11.2L21 8H6.3"/><circle cx="9" cy="19.5" r="1.3"/><circle cx="17" cy="19.5" r="1.3"/></svg>
                        <p class="mt-3 text-sm font-semibold">No items yet</p>
                        <p class="mt-1 text-xs text-text-secondary">Tap a product or scan its code to add it.</p>
                    </div>
                    <ul class="divide-y divide-border">
                        <template x-for="(line, index) in lines" :key="line.product_variant_id">
                            <li class="-mx-5 px-5 py-4 transition-colors duration-700 ease-out motion-reduce:transition-none" :class="flashId == line.product_variant_id ? 'bg-selected' : ''">
                                <input type="hidden" :name="`items[${index}][product_variant_id]`" :value="line.product_variant_id">
                                <input type="hidden" :name="`items[${index}][quantity]`" :value="line.quantity">
                                <input type="hidden" :name="`items[${index}][unit_price]`" :value="line.unit_price">
                                <input type="hidden" :name="`items[${index}][discount_amount]`" :value="line.discount_amount || '0'">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold break-words" x-text="line.name"></p>
                                        <p class="text-xs text-text-secondary" x-text="[line.variant, line.sku].filter(Boolean).join(' · ')"></p>
                                    </div>
                                    <button type="button" @click="remove(index)" :aria-label="'Remove ' + line.name" class="-mt-1 -mr-2 flex size-9 shrink-0 items-center justify-center rounded-lg text-text-secondary hover:bg-background hover:text-danger">
                                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                                    </button>
                                </div>
                                <div class="mt-3 flex items-center justify-between gap-3">
                                    <div class="flex items-center rounded-lg border border-border" role="group" :aria-label="'Quantity of ' + line.name">
                                        <button type="button" @click="decrease(line)" :disabled="Number(line.quantity) <= 1" class="{{ $press }} flex size-10 items-center justify-center rounded-l-lg hover:bg-background disabled:opacity-40" :aria-label="'One fewer ' + line.name">
                                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M5 12h14"/></svg></button>
                                        <input x-model="line.quantity" @keydown.enter.prevent inputmode="numeric" :aria-label="'Quantity of ' + line.name" class="h-10 w-12 border-x border-border text-center text-sm font-semibold tabular-nums focus-visible:outline-offset-[-2px]">
                                        <button type="button" @click="increase(line)" :disabled="! {{ $isOrder ? 'true' : 'false' }} && Number(line.quantity) >= line.available" class="{{ $press }} flex size-10 items-center justify-center rounded-r-lg hover:bg-background disabled:opacity-40" :aria-label="'One more ' + line.name">
                                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg></button>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm font-bold tabular-nums" x-text="lineTotal(line) === null ? 'Check amounts' : formatMoney(lineTotal(line))"></p>
                                        <p class="text-xs text-text-secondary tabular-nums" x-show="Number(line.quantity) > 1" x-text="formatMoney(line.unit_price) + ' each'"></p>
                                    </div>
                                </div>
                                <p x-show="overStock(line)" class="mt-2 text-xs font-semibold text-danger" x-text="'Only ' + line.available + ' in stock.'"></p>
                                <p x-show="priceChanged(line)" class="mt-2 text-xs text-info" x-text="'Price changed from ' + formatMoney(line.catalogue_price)"></p>
                                <button type="button" @click="line.adjust = ! line.adjust" :aria-expanded="line.adjust" class="mt-2 text-xs font-semibold text-text-secondary underline underline-offset-4 hover:text-text-primary" x-text="line.adjust ? 'Hide price and discount' : 'Price or discount'"></button>
                                <div x-show="line.adjust" x-cloak class="mt-3 grid grid-cols-2 gap-3">
                                    <div>
                                        <label :for="'price-' + index" class="mb-1 block text-xs font-medium">Unit price (TZS)</label>
                                        @if($canOverridePrice ?? false)
                                            <input :id="'price-' + index" x-model="line.unit_price" @keydown.enter.prevent inputmode="decimal" maxlength="16" class="min-h-10 w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm tabular-nums">
                                        @else
                                            <p :id="'price-' + index" class="flex min-h-10 items-center rounded-lg bg-background px-3 text-sm tabular-nums" x-text="formatMoney(line.catalogue_price)"></p>
                                        @endif
                                    </div>
                                    <div>
                                        <label :for="'discount-' + index" class="mb-1 block text-xs font-medium">Discount (TZS)</label>
                                        <input :id="'discount-' + index" x-model="line.discount_amount" @keydown.enter.prevent inputmode="decimal" maxlength="16" class="min-h-10 w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm tabular-nums">
                                    </div>
                                    @unless($canOverridePrice ?? false)<p class="col-span-2 text-xs text-text-secondary">Prices come from the catalogue. Ask an administrator to change a price.</p>@endunless
                                </div>
                            </li>
                        </template>
                    </ul>
                </div>

                <div class="space-y-4 border-t border-border px-5 py-4">
                    @if($isOrder)
                        <x-input name="delivery_address" label="Delivery address (optional)" :value="old('delivery_address')" maxlength="255" />
                        <p class="text-xs text-text-secondary">Saving an order does not reserve stock or record a sale. Confirm the order to reserve stock.</p>
                    @else
                        {{-- One tap to choose how the customer paid; nothing is preselected. --}}
                        <fieldset>
                            <legend class="mb-2 text-sm font-medium">Payment method</legend>
                            <div class="flex flex-wrap gap-2">
                                @foreach($methods as $value => $label)
                                    <label :class="payment === @js($value) ? 'border-text-primary bg-text-primary text-white' : 'border-border bg-surface hover:bg-background'"
                                        class="{{ $press }} cursor-pointer rounded-lg border px-3 py-2 text-sm font-semibold has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-info">
                                        <input type="radio" name="payment_method" value="{{ $value }}" x-model="payment" class="sr-only">{{ $label }}
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                        <div x-show="payment && payment !== 'CASH'" x-cloak>
                            <x-input name="payment_reference" label="Transaction reference (optional)" :value="old('payment_reference')" maxlength="191" placeholder="For example the M-Pesa code" />
                        </div>
                        <p class="text-xs text-text-secondary">Collect the payment yourself; the system records it but does not check it.</p>
                    @endif
                    <div x-data="{ showNotes: @js((bool) old('notes')) }">
                        <button type="button" x-show="! showNotes && ! hasDiscount" @click="showNotes = true; $nextTick(() => $refs.notes.focus())" class="text-sm font-semibold underline underline-offset-4">Add a note</button>
                        <div x-show="showNotes || hasDiscount" x-cloak>
                            <label for="notes" class="mb-2 block text-sm font-medium" x-text="hasDiscount ? 'Reason for the discount (required)' : 'Note (optional)'"></label>
                            <textarea id="notes" name="notes" x-ref="notes" x-model="notes" rows="2" maxlength="5000" class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm"></textarea>
                        </div>
                    </div>
                </div>

                <div class="sticky bottom-0 z-10 border-t border-border bg-surface px-5 pt-3 pb-4 shadow-[0_-8px_16px_-12px_rgb(24_24_27/0.25)]">
                <dl x-show="lines.length" x-cloak class="mb-3 space-y-1 text-sm">
                    <div class="flex justify-between gap-3 text-text-secondary" x-show="hasDiscount"><dt>Subtotal</dt><dd class="tabular-nums" x-text="formatMoney(subtotal)"></dd></div>
                    <div class="flex justify-between gap-3 text-text-secondary" x-show="hasDiscount"><dt>Discount</dt><dd class="tabular-nums" x-text="'-' + formatMoney(discountTotal)"></dd></div>
                    <div class="flex items-baseline justify-between gap-3"><dt class="font-semibold">Total</dt><dd class="text-2xl font-bold tracking-tight tabular-nums" x-text="formatMoney(total)"></dd></div>
                </dl>

                    <button type="submit" :disabled="blocker !== ''" data-busy-label="{{ $isOrder ? 'Saving…' : 'Opening review…' }}"
                        class="{{ $press }} flex min-h-13 w-full items-center justify-between gap-3 rounded-xl bg-primary px-5 py-3 text-base font-bold text-text-primary hover:bg-primary-hover disabled:cursor-not-allowed disabled:opacity-50 disabled:active:scale-100">
                        <span>{{ $isOrder ? 'Save order' : 'Review sale' }}</span><span class="tabular-nums" x-text="lines.length ? formatMoney(total) : ''"></span>
                    </button>
                    <p x-show="blocker" x-text="blocker" class="mt-2 text-center text-xs text-text-secondary" aria-live="polite"></p>
                    @unless($isOrder)<p x-show="! blocker" x-cloak class="mt-2 text-center text-xs text-text-secondary">You'll confirm the payment on the next screen.</p>@endunless
                </div>
            </div>
        </aside>

        {{-- Phones: the running total is always one glance away --}}
        <div x-show="! cartOpen" class="fixed inset-x-0 bottom-0 z-30 border-t border-white/10 bg-text-primary px-4 pt-3 pb-[calc(env(safe-area-inset-bottom)+0.75rem)] text-white lg:hidden">
            <div class="mx-auto flex max-w-xl items-center justify-between gap-4">
                <div>
                    <p class="text-xs text-white/70" x-text="itemCount ? itemCount + (itemCount === 1 ? ' item' : ' items') : 'No items yet'"></p>
                    <p class="text-lg font-bold tabular-nums" x-text="formatMoney(total)"></p>
                </div>
                <button type="button" @click="cartOpen = true" class="{{ $press }} min-h-11 rounded-lg bg-primary px-5 py-2 text-sm font-bold text-text-primary">{{ $isOrder ? 'View order' : 'View sale' }}</button>
            </div>
        </div>
    </form>
</x-layouts.app>
