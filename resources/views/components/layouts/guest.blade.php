@props(['title', 'intro' => null])
@php($monogram = mb_strtoupper(mb_substr(trim($businessName), 0, 1)) ?: 'M')
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
<body class="bg-background">
    <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-50 focus:rounded-lg focus:bg-surface focus:p-3 focus:text-text-primary">Skip to content</a>
    <div class="min-h-dvh lg:grid lg:grid-cols-[minmax(0,5fr)_minmax(0,6fr)]">
        <header class="relative isolate overflow-hidden bg-text-primary text-white lg:flex lg:min-h-dvh lg:flex-col lg:justify-between lg:p-12 xl:p-16">
            <div aria-hidden="true" class="absolute inset-0 -z-10 bg-[radial-gradient(circle_at_15%_0%,rgba(201,162,39,0.22),transparent_55%)]"></div>
            <span aria-hidden="true" class="pointer-events-none absolute -right-16 -bottom-16 -z-10 hidden select-none text-[20rem] xl:text-[24rem] leading-none font-bold text-transparent [-webkit-text-stroke:2px_rgba(201,162,39,0.16)] lg:block">{{ $monogram }}</span>

            <div class="flex items-center gap-3 px-6 py-5 lg:p-0">
                <span aria-hidden="true" class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary text-lg font-bold text-text-primary">{{ $monogram }}</span>
                <p class="min-w-0 text-sm font-bold tracking-[0.25em] break-words text-primary uppercase">{{ $businessName }}</p>
            </div>

            <div class="hidden lg:block">
                <div class="mb-8 h-px w-16 bg-primary"></div>
                <p class="max-w-md text-4xl leading-tight font-bold tracking-tight xl:text-5xl">Sales, stock and orders in one place.</p>
                <p class="mt-5 max-w-sm text-base leading-7 text-white/70">The workspace for everyone who keeps the store running, from the counter to the stockroom.</p>
            </div>

            <p class="hidden text-xs tracking-[0.2em] text-white/50 uppercase lg:block">Business Management System · Staff only</p>
        </header>

        <main id="main-content" class="flex items-start justify-center px-5 py-10 sm:px-8 sm:py-16 lg:min-h-dvh lg:items-center lg:bg-surface lg:py-12">
            <div class="w-full max-w-sm">
                <h1 class="text-3xl font-bold tracking-tight">{{ $title }}</h1>
                @if($intro)<p class="mt-2 text-sm leading-6 text-text-secondary">{{ $intro }}</p>@endif
                @if(session('status'))
                    <div role="status" class="mt-6 flex gap-3 rounded-lg border border-success/30 bg-success/5 p-4 text-sm leading-6">
                        <svg class="mt-0.5 size-5 shrink-0 text-success" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/></svg>
                        <p>{{ session('status') }}</p>
                    </div>
                @endif
                <div class="mt-8">{{ $slot }}</div>
            </div>
        </main>
    </div>
</body>
</html>
