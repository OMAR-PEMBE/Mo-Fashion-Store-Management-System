@props(['title' => 'Welcome'])
@php
    $user = auth()->user();
    $isAdmin = $user?->role?->slug === 'administrator';
    $monogram = mb_strtoupper(mb_substr(trim($business['business_name']), 0, 1)) ?: 'M';
    $initials = collect(preg_split('/\s+/', trim(preg_replace('/\(.*?\)/', '', (string) $user?->name))))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->join('');
    // 24px outline icon paths, shared stroke style.
    $icons = [
        'home' => '<path d="M3 11.5 12 4l9 7.5"/><path d="M5.5 10v10h13V10"/>',
        'pos' => '<path d="M3 4h2l2.4 11h11.2L21 8H6.3"/><circle cx="9" cy="19.5" r="1.3"/><circle cx="17" cy="19.5" r="1.3"/>',
        'receipt' => '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2V3Z"/><path d="M9 8h6M9 12h6"/>',
        'orders' => '<path d="M4 7.5 12 3l8 4.5v9L12 21l-8-4.5v-9Z"/><path d="m4 7.5 8 4.5 8-4.5M12 12v9"/>',
        'customers' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18 14.2A6.5 6.5 0 0 1 21.5 20"/>',
        'returns' => '<path d="M9 14 4 9l5-5"/><path d="M4 9h10.5a5.5 5.5 0 0 1 0 11H11"/>',
        'refunds' => '<rect x="2.5" y="6" width="19" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 9.5v5M18 9.5v5"/>',
        'exchanges' => '<path d="M4 8h14l-3-3M20 16H6l3 3"/>',
        'inventory' => '<path d="M3 8.5 12 4l9 4.5-9 4.5-9-4.5Z"/><path d="m3 13 9 4.5 9-4.5M3 17.5 12 22l9-4.5"/>',
        'purchases' => '<path d="M2.5 6h11v10h-11zM13.5 9.5h4l3 3.5v3h-7"/><circle cx="6.5" cy="17.5" r="1.8"/><circle cx="17" cy="17.5" r="1.8"/>',
        'products' => '<path d="M20.5 13.5 13.5 20.5a2 2 0 0 1-2.8 0l-7.2-7.2V3.5h9.8l7.2 7.2a2 2 0 0 1 0 2.8Z"/><circle cx="8" cy="8" r="1.5"/>',
        'suppliers' => '<path d="M3 21V9l6 3V9l6 3V5h6v16H3Z"/><path d="M7 17h2M12 17h2M17 17h1"/>',
        'opening' => '<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4V2.5h6V4M9 13l2 2 4-4"/>',
        'expenses' => '<path d="M4 7a2 2 0 0 1 2-2h12v4"/><path d="M4 7v11a2 2 0 0 0 2 2h14V9H6a2 2 0 0 1-2-2Z"/><circle cx="16" cy="14.5" r="1.2"/>',
        'reports' => '<path d="M4 20V10M10 20V4M16 20v-7M21.5 20h-19"/>',
        'staff' => '<path d="M12 3 4.5 6v5.5c0 4.5 3.2 8.4 7.5 9.5 4.3-1.1 7.5-5 7.5-9.5V6L12 3Z"/><path d="m9 12 2 2 4-4"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1Z"/>',
        'audit' => '<path d="M12 7v5l3 2"/><path d="M3.5 12a8.5 8.5 0 1 0 2.5-6L3.5 8.5"/><path d="M3.5 4v4.5H8"/>',
        'categories' => '<rect x="3.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.5"/>',
        'sizes' => '<path d="m3 16 13-13 5 5-13 13-5-5Z"/><path d="m7 12 2 2M10 9l2 2M13 6l2 2"/>',
        'colours' => '<path d="M12 3a9 9 0 1 0 0 18c1.4 0 2-1 2-2 0-1.5-1.5-1.8-1.5-3.2 0-1 .8-1.8 1.8-1.8H17a4 4 0 0 0 4-4c0-3.9-4-7-9-7Z"/><circle cx="7.5" cy="11" r="1"/><circle cx="10" cy="7" r="1"/><circle cx="15" cy="7.5" r="1"/>',
    ];
    $referenceIcons = ['categories' => 'categories', 'sizes' => 'sizes', 'colours' => 'colours'];
    // Grouped by daily use. Visibility rules are unchanged from the original navigation.
    $groups = [
        null => [
            ['Overview', route('home'), true, request()->routeIs('dashboard'), 'home'],
        ],
        'Sell' => [
            ['Point of sale', route('sales.create'), $user?->can('sales.create'), request()->routeIs('sales.create', 'sales.review'), 'pos'],
            ['Sales history', route('sales.index'), $user?->can('sales.create'), request()->routeIs('sales.index', 'sales.show'), 'receipt'],
            ['Orders', route('orders.index'), $user?->can('orders.create'), request()->routeIs('orders.*'), 'orders'],
            ['Customers', route('customers.index'), $user?->can('customers.create'), request()->routeIs('customers.*'), 'customers'],
        ],
        'After-sales' => [
            ['Returns', route('returns.index'), $user?->can('returns.create'), request()->routeIs('returns.*'), 'returns'],
            ['Refunds', route('refunds.index'), $user?->can('refunds.create'), request()->routeIs('refunds.*'), 'refunds'],
            ['Exchanges', route('exchanges.index'), $user?->can('exchanges.create'), request()->routeIs('exchanges.*'), 'exchanges'],
        ],
        'Stock' => [
            ['Inventory', route('inventory.index'), $user?->can('inventory.view'), request()->routeIs('inventory.*'), 'inventory'],
            ['Products', route('products.index'), $user?->can('products.view'), request()->routeIs('products.*', 'variants.*'), 'products'],
            ['Purchases', route('purchases.index'), $user?->can('purchases.manage'), request()->routeIs('purchases.*'), 'purchases'],
            ['Suppliers', route('suppliers.index'), $user?->can('suppliers.manage'), request()->routeIs('suppliers.*'), 'suppliers'],
            ['Opening stock', route('opening-stock.index'), $isAdmin && $user->hasPermission('inventory.adjust'), request()->routeIs('opening-stock.*'), 'opening'],
        ],
        'Money' => [
            ['Expenses', route('expenses.index'), $user?->can('expenses.view'), request()->routeIs('expenses.*', 'expense-categories.*'), 'expenses'],
            ['Reports', route('reports.index'), $user?->can('reports.view'), request()->routeIs('reports.*'), 'reports'],
        ],
        'Catalogue setup' => collect(\App\Enums\ReferenceType::cases())->map(fn ($type) => [$type->label(), route('reference.index', $type->value),
            $user?->can('reference-data.manage'), request()->route('type') === $type, $referenceIcons[$type->value] ?? 'categories'])->all(),
        'Administration' => [
            ['Staff & access', route('users.index'), $isAdmin && $user->can('users.manage'), request()->routeIs('users.*', 'roles.*'), 'staff'],
            ['Business settings', route('settings.edit'), $isAdmin && $user->can('settings.manage'), request()->routeIs('settings.*'), 'settings'],
            ['Audit logs', route('audit.index'), $isAdmin && $user->can('audit.view'), request()->routeIs('audit.*'), 'audit'],
        ],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#18181B">
    <title>{{ $title }} · {{ $business['business_name'] }}</title>
    <link rel="icon" href="/favicon.ico" sizes="32x32">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body>
    <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-50 focus:rounded-lg focus:bg-surface focus:p-3">Skip to content</a>
    <div class="min-h-screen lg:grid lg:grid-cols-[248px_1fr] print:block" x-data="{ navigationOpen: false }">
        <aside class="bg-text-primary text-white lg:sticky lg:top-0 lg:flex lg:h-screen lg:flex-col print:hidden">
            <div class="flex items-center justify-between px-5 py-4 lg:px-6 lg:py-7">
                <a href="{{ route('home') }}" class="flex min-w-0 items-center gap-3" aria-label="{{ $business['business_name'] }} home">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary text-lg font-bold text-text-primary" aria-hidden="true">{{ $monogram }}</span>
                    <span class="min-w-0 text-base leading-tight font-bold tracking-tight break-words">{{ $business['business_name'] }}</span>
                </a>
                <div class="ml-3 flex shrink-0 items-center gap-2 lg:hidden">
                    @auth<x-account-menu :user="$user" :initials="$initials" dark />@endauth
                    <button type="button" class="inline-flex items-center gap-2 rounded-lg border border-white/30 px-3 py-2 text-sm" @click="navigationOpen = !navigationOpen" :aria-expanded="navigationOpen" aria-controls="main-navigation">
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>Menu
                    </button>
                </div>
            </div>
            <nav id="main-navigation" aria-label="Main navigation" class="px-3 pb-6 lg:block lg:flex-1 lg:overflow-y-auto" :class="navigationOpen ? 'block' : 'hidden lg:block'">
                @foreach($groups as $heading => $items)
                    @php $visible = array_filter($items, fn ($item) => $item[2]); @endphp
                    @if($visible)
                        @if($heading)<p class="px-3 pt-5 pb-2 text-[11px] font-semibold tracking-[0.18em] text-white/50 uppercase">{{ $heading }}</p>@endif
                        @foreach($visible as [$label, $url, , $active, $icon])
                            <a href="{{ $url }}" @if($active) aria-current="page" @endif @class(['mt-0.5 flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-semibold transition-colors', 'bg-primary text-text-primary' => $active, 'text-white/85 hover:bg-white/10 hover:text-white' => ! $active])>
                                <svg class="size-[18px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icons[$icon] !!}</svg>{{ $label }}
                            </a>
                        @endforeach
                    @endif
                @endforeach
            </nav>
        </aside>
        <div class="min-w-0">
            @php $showShortcut = $user?->can('sales.create') && ! request()->routeIs('sales.create', 'sales.review', 'sales.show', 'dashboard'); @endphp
            {{-- On phones the account menu lives in the dark bar, so this bar only appears when it carries the sale shortcut. --}}
            <header @class(['min-h-14 items-center justify-between gap-4 border-b border-border bg-surface px-5 sm:min-h-16 lg:flex lg:px-10 print:hidden', 'flex' => $showShortcut, 'hidden' => ! $showShortcut])>
                <div>
                    @if($showShortcut)
                        <a href="{{ route('sales.create') }}" class="inline-flex min-h-10 items-center gap-2 rounded-lg bg-text-primary px-4 py-2 text-sm font-semibold text-white hover:bg-zinc-700">
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>New sale</a>
                    @endif
                </div>
                @auth<x-account-menu :user="$user" :initials="$initials" class="hidden lg:block" />@endauth
            </header>
            <main id="main-content" tabindex="-1" class="mx-auto max-w-7xl px-5 py-8 lg:px-10 lg:py-10 print:p-0">
                {{-- Every action's confirmation appears in the same place on every page. --}}
                @if(session('status'))
                    <div role="status" x-data="{ shown: true }" x-show="shown" x-transition.opacity.duration.200ms
                        class="mb-6 flex items-start gap-3 rounded-xl border border-success/30 bg-success/5 p-4 text-sm print:hidden">
                        <svg class="mt-0.5 size-5 shrink-0 text-success" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/></svg>
                        <p class="flex-1 break-words">{{ session('status') }}</p>
                        <button type="button" @click="shown = false" class="-m-1 rounded p-1 text-text-secondary hover:text-text-primary" aria-label="Dismiss message">
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                        </button>
                    </div>
                @endif
                {{ $slot }}
            </main>
        </div>
    </div>
    @livewireScripts
</body>
</html>
