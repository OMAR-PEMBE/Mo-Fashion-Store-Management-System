<x-layouts.app :title="$supplier->name">
    <a href="{{ route('suppliers.index') }}" class="mb-5 inline-block text-sm underline">Back to suppliers</a>
    <div class="mb-7 flex flex-wrap items-start justify-between gap-4"><div class="min-w-0"><p class="mb-2 break-all text-sm text-text-secondary">{{ $supplier->supplier_code }}</p><h1 class="break-words text-3xl font-bold">{{ $supplier->name }}</h1></div><x-action-link :href="route('suppliers.edit', $supplier)" :secondary="true">Edit supplier</x-action-link></div>
    @if(session('status'))<p role="status" class="mb-5 rounded-lg border border-success bg-surface p-4 text-sm">{{ session('status') }}</p>@endif
    <div class="grid gap-6 lg:grid-cols-2">
        <x-card title="Supplier details">
            <x-badge :tone="$supplier->is_active ? 'success' : 'warning'">{{ $supplier->is_active ? 'Active' : 'Inactive' }}</x-badge>
            <dl class="mt-6 space-y-5 text-sm">@foreach(['contact_person' => 'Contact person', 'phone' => 'Phone', 'email' => 'Email', 'location' => 'Location', 'notes' => 'Notes'] as $field => $label)<div><dt class="mb-1 text-text-secondary">{{ $label }}</dt><dd class="whitespace-pre-line break-words">{{ $supplier->$field ?? 'Not provided' }}</dd></div>@endforeach</dl>
        </x-card>
        <x-card title="Purchase history">
            @can('purchases.manage')
                @forelse($purchases as $purchase)
                    <div class="border-b border-border py-4 text-sm"><a href="{{ route('purchases.show', $purchase) }}" class="break-all underline">{{ $purchase->purchase_number }}</a><p class="mt-2 text-text-secondary">{{ $purchase->purchase_date->format('d M Y') }} · {{ $purchase->status->value }} · @money($purchase->total_amount)</p></div>
                @empty<p class="text-sm text-text-secondary">No purchases recorded for this supplier.</p>@endforelse
                <div class="mt-4">{{ $purchases->links() }}</div>
            @else<p class="text-sm text-text-secondary">Purchase history requires purchasing permission.</p>@endcan
        </x-card>
    </div>
</x-layouts.app>
