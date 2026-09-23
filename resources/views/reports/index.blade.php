<x-layouts.app title="Reports">
    <h1 class="mb-4 text-3xl font-bold">Reports</h1><p class="mb-7 text-sm text-text-secondary">Review transactions, stock and period profitability. All financial values use TZS.</p>
    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">@forelse($types as $type)<x-card :title="ucfirst($type). ' report'"><a href="{{ route('reports.show', $type) }}" class="inline-block py-3 text-sm font-semibold underline">Open {{ $type }} report</a></x-card>@empty<x-card><p class="text-sm">Your account has no permitted report types.</p></x-card>@endforelse</div>
</x-layouts.app>
