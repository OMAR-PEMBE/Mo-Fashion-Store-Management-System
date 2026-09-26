<x-layouts.app title="Sale needs review">
    <div class="mx-auto max-w-xl py-6">
        <div class="rounded-2xl border border-warning/40 bg-surface p-6 sm:p-8">
            <span class="flex size-12 items-center justify-center rounded-full bg-warning/15 text-warning" aria-hidden="true">
                <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>
            </span>
            <h1 class="mt-4 text-2xl font-bold">This sale was not recorded</h1>
            <p role="alert" class="mt-2 text-sm">{{ $message }}</p>
            <p class="mt-3 text-sm text-text-secondary">Your cart is kept, and nothing was charged or taken out of stock. Before trying again, check the sales history: if the same cart was already sent once (for example a double tap or a slow connection), the first sale may have gone through.</p>
            <div class="mt-6 flex flex-wrap gap-3">
                <x-action-link :href="route('sales.create')">Return to cart</x-action-link>
                <a href="{{ route('sales.index') }}" class="inline-flex min-h-11 items-center rounded-lg border border-border bg-surface px-4 text-sm font-semibold hover:bg-background">Check recent sales</a>
            </div>
        </div>
    </div>
</x-layouts.app>
