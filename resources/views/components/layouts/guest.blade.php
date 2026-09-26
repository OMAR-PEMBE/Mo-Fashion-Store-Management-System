@props(['title', 'intro' => null])
@php
    $monogram = mb_strtoupper(mb_substr(trim($businessName), 0, 1)) ?: 'M';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#18181B">
    <title>{{ $title }} · {{ $businessName }}</title>
    <link rel="icon" href="/favicon.ico" sizes="32x32">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-text-primary">
    <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-50 focus:rounded-lg focus:bg-surface focus:p-3 focus:text-text-primary">Skip to content</a>

    {{-- Full-screen brand cover behind everything; decorative only. --}}
    <div class="auth-cover" aria-hidden="true">
        <div class="glow glow-gold"></div>
        <div class="glow glow-bronze"></div>
        <div class="glow glow-cream"></div>
        <span class="pointer-events-none absolute right-[44%] -bottom-[6vmax] hidden select-none text-[42vmax] leading-none font-extrabold text-transparent [-webkit-text-stroke:1px_rgba(201,162,39,0.14)] lg:block">{{ $monogram }}</span>
        <div class="grain"></div>
    </div>

    <div class="mx-auto grid min-h-dvh max-w-7xl content-start gap-7 px-4 py-8 sm:px-8 md:content-center lg:grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)] lg:content-center lg:items-center lg:gap-12 lg:px-16 lg:py-12">
        <header class="text-white">
            <div class="flex items-center gap-3">
                <span aria-hidden="true" class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary text-lg font-bold text-text-primary">{{ $monogram }}</span>
                <p class="min-w-0 text-sm font-bold tracking-[0.25em] break-words text-primary uppercase">{{ $businessName }}</p>
            </div>
            <div class="mt-9 mb-5 h-0.5 w-16 bg-primary lg:mt-16 lg:mb-7"></div>
            <p class="max-w-[14ch] text-3xl leading-[1.08] font-extrabold tracking-tight sm:text-4xl lg:text-5xl xl:text-[3.5rem]">Sales, stock and orders in one place.</p>
            <p class="mt-4 max-w-[38ch] text-[0.95rem] leading-7 text-white/70 lg:mt-5 lg:text-lg">The workspace for everyone who keeps the store running, from the counter to the stockroom.</p>
            <p class="mt-16 hidden text-xs tracking-[0.2em] text-white/45 uppercase lg:block">Business Management System · Staff only</p>
        </header>

        <main id="main-content" class="auth-card w-full max-w-md justify-self-start rounded-2xl lg:justify-self-center bg-surface p-6 shadow-[0_30px_80px_-20px_rgba(0,0,0,0.55)] ring-1 ring-white/5 sm:p-10">
            <h1 class="text-3xl font-bold tracking-tight">{{ $title }}</h1>
            @if($intro)<p class="mt-2 text-sm leading-6 text-text-secondary">{{ $intro }}</p>@endif
            @if(session('status'))
                <div role="status" class="mt-6 flex gap-3 rounded-lg border border-success/30 bg-success/5 p-4 text-sm leading-6">
                    <svg class="mt-0.5 size-5 shrink-0 text-success" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/></svg>
                    <p>{{ session('status') }}</p>
                </div>
            @endif
            <div class="mt-8">{{ $slot }}</div>
        </main>
    </div>
</body>
</html>
