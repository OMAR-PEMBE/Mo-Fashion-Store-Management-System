<x-layouts.app title="Overview">
    <div class="mb-10">
        <p class="mb-3 text-xs font-semibold uppercase tracking-[0.2em] text-info">Mo Fashion Store</p>
        <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">A fresh start for your store.</h1>
        <p class="mt-4 max-w-xl text-sm leading-7 text-text-secondary">A dedicated home for your daily operations, with everything in one place.</p>
    </div>
    <x-card class="overflow-hidden !p-0">
        <div class="grid lg:grid-cols-[1.3fr_1fr]">
            <div class="px-7 py-10 sm:p-12">
                <x-badge>Welcome</x-badge>
                <h2 class="mt-6 text-2xl font-bold tracking-tight">Your workspace is taking shape.</h2>
                <p class="mt-4 max-w-lg text-sm leading-7 text-text-secondary">Store setup is in progress. Staff access and business tools will become available as setup is completed.</p>
                <div class="mt-8 border-t border-border pt-6 text-sm"><span class="font-semibold">One store. One clear view.</span><p class="mt-2 text-text-secondary">Made for Mo Fashion Store.</p></div>
            </div>
            <div class="relative flex min-h-64 items-center justify-center overflow-hidden bg-selected p-12" aria-hidden="true">
                <div class="absolute size-72 rounded-full border border-primary/25"></div>
                <div class="absolute size-56 rounded-full border border-primary/40"></div>
                <div class="relative flex size-36 rotate-[-8deg] items-center justify-center rounded-3xl bg-text-primary text-6xl font-bold text-primary shadow-lg">Mo</div>
            </div>
        </div>
    </x-card>
</x-layouts.app>
