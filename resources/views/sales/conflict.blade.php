<x-layouts.app title="Sale needs review">
    <h1 class="mb-7 text-3xl font-bold">Sale needs review</h1><x-card class="max-w-2xl"><p role="alert" class="mb-6 text-sm">{{ $message }}</p><div class="flex flex-wrap gap-4"><x-action-link :href="route('sales.create')">Return to cart</x-action-link><x-action-link :href="route('sales.index')" :secondary="true">Check sales history</x-action-link></div><p class="mt-5 text-sm text-text-secondary">No additional sale was recorded by this attempt. Check the original sale before retrying a previously submitted cart.</p></x-card>
</x-layouts.app>
