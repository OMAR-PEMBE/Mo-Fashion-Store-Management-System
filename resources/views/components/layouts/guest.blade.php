@props(['title'])
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>{{ $title }} · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <a href="#main-content" class="sr-only focus:not-sr-only">Skip to content</a>
    <main id="main-content" class="flex min-h-screen items-center justify-center px-5 py-12">
        <div class="w-full max-w-md">
            <div class="mb-8 text-center">
                <span class="mx-auto mb-4 flex size-14 items-center justify-center rounded-2xl bg-primary text-2xl font-bold">M</span>
                <p class="text-xl font-bold">Mo Fashion Store</p>
                <p class="mt-2 text-sm text-text-secondary">Your store. Your workspace.</p>
            </div>
            <section class="rounded-2xl border border-border bg-surface p-6 shadow-sm sm:p-8" aria-labelledby="page-title">
                <h1 id="page-title" class="mb-6 text-2xl font-bold">{{ $title }}</h1>
                @if(session('status'))<div role="status" class="mb-5 rounded-lg border border-success p-3 text-sm">{{ session('status') }}</div>@endif
                {{ $slot }}
            </section>
            <p class="mt-6 text-center text-xs text-text-secondary">Staff access · Business Management System</p>
        </div>
    </main>
</body>
</html>
