<x-layouts.app title="Suppliers">
    <div class="mb-8 flex flex-wrap items-start justify-between gap-4"><div><h1 class="text-3xl font-bold">Suppliers</h1><p class="mt-3 text-sm text-text-secondary">Manage the businesses you buy stock from.</p></div><x-action-link :href="route('suppliers.create')">Add supplier</x-action-link></div>
    @if(session('status'))<p role="status" class="mb-5 rounded-lg border border-success bg-surface p-4 text-sm">{{ session('status') }}</p>@endif
    <form method="GET" class="mb-6 grid items-end gap-4 rounded-xl border border-border bg-surface p-5 sm:grid-cols-[1fr_180px_auto]">
        <x-input name="q" label="Search" :value="$filters['q'] ?? ''" placeholder="Name, code, contact, phone or email" maxlength="191" />
        <x-select name="status" label="Status"><option value="">All statuses</option><option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option><option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option></x-select>
        <div class="flex items-center gap-3"><x-button type="submit" variant="secondary">Filter</x-button><a href="{{ route('suppliers.index') }}" class="text-sm underline">Clear</a></div>
    </form>
    <div class="overflow-hidden rounded-xl border border-border bg-surface">
        @if($suppliers->isEmpty())<div class="px-6 py-14 text-center"><h2 class="font-semibold">No suppliers found</h2><p class="mt-2 text-sm text-text-secondary">Add a supplier or change your filters.</p></div>
        @else
        <div class="relative overflow-x-auto"><table class="w-full min-w-[700px] text-left text-sm"><caption class="sr-only">Suppliers and contact details</caption>
            <thead class="border-b border-border bg-background"><tr>@foreach(['Supplier', 'Contact', 'Phone', 'Status', 'Actions'] as $heading)<th scope="col" class="px-5 py-4 font-medium">{{ $heading }}</th>@endforeach</tr></thead>
            <tbody class="divide-y divide-border">@foreach($suppliers as $supplier)<tr class="hover:bg-selected/50">
                <th scope="row" class="min-w-40 max-w-xs break-words px-5 py-4 font-medium">{{ $supplier->name }}<span class="mt-1 block break-all text-xs font-normal text-text-secondary">{{ $supplier->supplier_code }}</span></th>
                <td class="max-w-xs break-words px-5 py-4">{{ $supplier->contact_person ?? '—' }}</td><td class="px-5 py-4">{{ $supplier->phone ?? '—' }}</td>
                <td class="px-5 py-4"><x-badge :tone="$supplier->is_active ? 'success' : 'warning'">{{ $supplier->is_active ? 'Active' : 'Inactive' }}</x-badge></td>
                <td class="px-5 py-4"><a href="{{ route('suppliers.show', $supplier) }}" class="inline-flex min-h-11 items-center underline" aria-label="View {{ $supplier->name }}">View</a></td>
            </tr>@endforeach</tbody>
        </table></div><div class="border-t border-border p-5">{{ $suppliers->links() }}</div>
        @endif
    </div>
</x-layouts.app>
