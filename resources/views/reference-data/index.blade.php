<x-layouts.app :title="$type->label()">
    <div class="mb-8 flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="mb-2 text-sm text-text-secondary">Catalogue setup</p>
            <h1 class="text-3xl font-bold">{{ $type->label() }}</h1>
            <p class="mt-3 text-sm text-text-secondary">Manage the {{ strtolower($type->label()) }} used to organise your products.</p>
        </div>
        <a href="{{ route('reference.create', $type->value) }}" class="inline-flex min-h-11 items-center rounded-lg bg-primary px-4 py-2 text-sm font-semibold hover:bg-primary-hover">Add {{ $type->singular() }}</a>
    </div>
    @if(session('status'))<p role="status" class="mb-5 rounded-lg border border-success bg-surface p-4 text-sm">{{ session('status') }}</p>@endif
    <form method="GET" class="mb-6 grid items-end gap-4 rounded-xl border border-border bg-surface p-5 sm:grid-cols-[1fr_180px_auto]">
        <x-input name="q" label="Search" :value="$filters['q'] ?? ''" placeholder="Name or code" maxlength="150" />
        <x-select name="status" label="Status">
            <option value="">All statuses</option>
            <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
            <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option>
        </x-select>
        <div class="flex items-center gap-3"><x-button type="submit" variant="secondary">Filter</x-button><a href="{{ route('reference.index', $type->value) }}" class="text-sm underline">Clear</a></div>
    </form>
    <div class="overflow-hidden rounded-xl border border-border bg-surface">
        @if($records->isEmpty())
            <div class="px-6 py-14 text-center">
                <h2 class="font-semibold">No {{ strtolower($type->label()) }} found</h2>
                <p class="mt-2 text-sm text-text-secondary">Add a {{ $type->singular() }} or change your filters.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <caption class="sr-only">{{ $type->label() }} and their current status</caption>
                    <thead class="border-b border-border bg-background"><tr>
                        <th scope="col" class="px-5 py-4 font-medium">Name</th>
                        <th scope="col" class="px-5 py-4 font-medium">{{ ucfirst($type->identifier()) }}</th>
                        @if($type->value === 'sizes')<th scope="col" class="px-5 py-4 font-medium">Order</th>@endif
                        <th scope="col" class="px-5 py-4 font-medium">Status</th>
                        <th scope="col" class="px-5 py-4 font-medium"><span class="sr-only">Actions</span></th>
                    </tr></thead>
                    <tbody class="divide-y divide-border">
                    @foreach($records as $record)
                        <tr class="hover:bg-selected/50">
                            <th scope="row" class="max-w-xs break-words px-5 py-4 font-medium">
                                @if($type->value === 'colours' && $record->hex_code)<span aria-hidden="true" class="mr-2 inline-block size-4 rounded-full border border-border align-middle" style="background-color: {{ $record->hex_code }}"></span>@endif
                                {{ $record->name }}
                            </th>
                            <td class="max-w-xs break-all px-5 py-4 text-text-secondary">{{ $record->{$type->identifier()} }}</td>
                            @if($type->value === 'sizes')<td class="px-5 py-4">{{ $record->sort_order }}</td>@endif
                            <td class="px-5 py-4"><x-badge :tone="$record->is_active ? 'success' : 'warning'">{{ $record->is_active ? 'Active' : 'Inactive' }}</x-badge></td>
                            <td class="px-5 py-4 text-right"><a href="{{ route('reference.edit', [$type->value, $record->id]) }}" class="inline-flex min-h-11 items-center font-medium underline underline-offset-4" aria-label="Edit {{ $record->name }}">Edit</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-border p-5">{{ $records->links() }}</div>
        @endif
    </div>
</x-layouts.app>
