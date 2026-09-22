@props(['title' => 'Welcome'])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#FAF8F3">
    <title>{{ $title }} · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body>
    <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-50 focus:rounded-lg focus:bg-surface focus:p-3">Skip to content</a>
    <div class="min-h-screen lg:grid lg:grid-cols-[260px_1fr]" x-data="{ navigationOpen: false }">
        <aside class="border-b border-border bg-text-primary text-white lg:min-h-screen lg:border-b-0">
            <div class="flex items-center justify-between px-6 py-7 lg:px-8 lg:py-10">
                <a href="{{ route('home') }}" class="flex items-center gap-3" aria-label="Mo Fashion Store home">
                    <span class="flex size-11 items-center justify-center rounded-xl bg-primary text-xl font-bold text-text-primary">M</span>
                    <span><span class="block text-lg font-bold tracking-tight">Mo Fashion</span><span class="block text-xs tracking-[0.2em] text-white/70">STORE</span></span>
                </a>
                <button type="button" class="rounded-lg border border-white/30 px-3 py-2 text-sm lg:hidden" @click="navigationOpen = !navigationOpen" :aria-expanded="navigationOpen" aria-controls="main-navigation">Menu</button>
            </div>
            <nav id="main-navigation" aria-label="Main navigation" class="px-5 pb-6 lg:block" :class="navigationOpen ? 'block' : 'hidden lg:block'">
                <p class="px-4 pb-3 text-xs font-medium uppercase tracking-[0.18em] text-white/60">Workspace</p>
                <a href="{{ route('home') }}" aria-current="page" class="flex items-center gap-3 rounded-xl bg-primary px-4 py-3 text-sm font-semibold text-text-primary"><span aria-hidden="true">⌂</span> Overview</a>
            </nav>
            <div class="hidden px-9 py-8 text-xs leading-6 text-white/60 lg:block">Mo Fashion Store<br>Business Management System</div>
        </aside>
        <div class="min-w-0">
            <header class="flex min-h-20 items-center justify-between gap-4 border-b border-border bg-surface px-6 lg:px-10">
                <span class="text-sm font-medium">Your store workspace</span>
                <div class="flex items-center gap-3">
                    <span class="hidden rounded-full border border-border px-3 py-1 text-xs font-medium sm:block">{{ config('business.currency') }}</span>
                    @auth
                    <details class="relative">
                        <summary class="cursor-pointer rounded-lg border border-border px-3 py-2 text-sm">Your account</summary>
                        <div class="absolute right-0 z-20 mt-2 w-60 rounded-xl border border-border bg-surface p-4 shadow-lg">
                            <p class="break-words text-sm font-semibold">{{ auth()->user()->name }}</p>
                            <a href="{{ route('profile') }}" class="my-3 block text-sm underline underline-offset-4">Profile &amp; password</a>
                            <form method="POST" action="{{ route('logout') }}">@csrf<x-button type="submit" variant="secondary" class="w-full">Sign out</x-button></form>
                        </div>
                    </details>
                    @endauth
                </div>
            </header>
            <main id="main-content" tabindex="-1" class="mx-auto max-w-7xl px-6 py-10 lg:px-10 lg:py-14">{{ $slot }}</main>
            <footer class="px-6 py-6 text-xs text-text-secondary lg:px-10">Mo Fashion Store · Built around your business.</footer>
        </div>
    </div>
    @livewireScripts
</body>
</html>
