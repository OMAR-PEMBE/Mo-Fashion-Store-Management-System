<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Receipt {{ $sale->sale_number }} · {{ $business['business_name'] }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-background px-4 py-8 text-text-primary antialiased">
    <main class="mx-auto max-w-xl space-y-4">
        @if(($sale->status->value ?? $sale->status) === 'CANCELLED')
            <p role="status" class="rounded-xl border border-warning/40 bg-warning/10 p-4 text-sm">This sale was cancelled after the receipt was sent.</p>
        @endif
        <x-receipt :sale="$sale" :business="$business" :internal="false" />
        <div class="flex justify-center print:hidden">
            <button type="button" onclick="window.print()" class="inline-flex min-h-11 items-center rounded-lg border border-border bg-surface px-4 text-sm font-semibold hover:bg-background">Print or save as PDF</button>
        </div>
        <p class="text-center text-xs text-text-secondary print:hidden">
            Keep this link private.@if(ctype_digit((string) request('expires'))) It works until {{ \Illuminate\Support\Carbon::createFromTimestamp((int) request('expires'))->timezone(config('app.timezone'))->format('j F Y') }}.@endif
            @if($business['business_phone']) Questions? Call {{ $business['business_phone'] }}.@endif
        </p>
    </main>
</body>
</html>
