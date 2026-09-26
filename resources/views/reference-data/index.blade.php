@php
    $about = [
        'categories' => 'Group products so they are easy to find and report on, for example Dresses or Denim.',
        'sizes' => 'The sizes you sell, in the order they should appear everywhere (S before M before L).',
        'colours' => 'Colour names used on product options.',
    ];
    $usedBy = fn ($n) => $type->value === 'categories' ? \Illuminate\Support\Str::plural('product', $n) : \Illuminate\Support\Str::plural('product option', $n);
    // Display only: a swatch when the name is a plain colour word browsers know (e.g. "Navy").
    $swatch = fn ($name) => preg_match('/^[a-z]{3,20}$/', $word = strtolower(str_replace(' ', '', $name))) ? $word : null;
@endphp
<x-layouts.app :title="$type->label()">
    <x-page-header title="Catalogue setup" :description="$about[$type->value]">
        <x-slot:actions><x-action-link :href="route('reference.create', $type->value)">Add {{ $type->singular() }}</x-action-link></x-slot:actions>
    </x-page-header>

    <nav aria-label="Catalogue lists" class="mb-5 flex gap-1 border-b border-border">
        @foreach(\App\Enums\ReferenceType::cases() as $case)
            <a href="{{ route('reference.index', $case->value) }}" @if($case === $type) aria-current="page" @endif
                @class(['-mb-px border-b-2 px-4 py-2.5 text-sm font-semibold', 'border-primary text-text-primary' => $case === $type, 'border-transparent text-text-secondary hover:text-text-primary' => $case !== $type])>{{ $case->label() }}</a>
        @endforeach
    </nav>

    <form method="GET" class="mb-4" role="search">
        @if($filters['status'] ?? null)<input type="hidden" name="status" value="{{ $filters['status'] }}">@endif
        <div class="relative max-w-md">
            <label for="reference-search" class="sr-only">Search {{ strtolower($type->label()) }}</label>
            <svg class="pointer-events-none absolute top-1/2 left-3.5 size-5 -translate-y-1/2 text-text-secondary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <input id="reference-search" name="q" type="search" value="{{ $filters['q'] ?? '' }}" maxlength="150" placeholder="{{ $type->value === 'colours' ? 'Colour name' : 'Name or code' }}" class="min-h-11 w-full rounded-lg border border-border bg-surface py-2 pr-3 pl-11 text-sm">
        </div>
    </form>
    <x-filter-tabs :options="['' => 'All', 'active' => 'In use', 'inactive' => 'Switched off']" :counts="$counts" class="mb-5" />

    <div class="overflow-hidden rounded-2xl border border-border bg-surface">
        @if($records->isEmpty())
            <div class="p-10 text-center">
                <p class="font-semibold">No {{ strtolower($type->label()) }} found</p>
                <p class="mt-1 text-sm text-text-secondary">Add a {{ $type->singular() }} or change your search.</p>
            </div>
        @else
            <ul class="divide-y divide-border">
                @foreach($records as $record)
                    @php $used = (int) ($usage[$record->id] ?? 0); @endphp
                    <li @class(['relative flex items-center gap-4 px-5 py-3.5 hover:bg-selected/50', 'opacity-70' => ! $record->is_active])>
                        @if($type->value === 'colours')
                            <span class="size-7 shrink-0 rounded-full border border-border" @if($colour = $swatch($record->name)) style="background-color: {{ $colour }}" @endif aria-hidden="true"></span>
                        @elseif($type->value === 'sizes')
                            <span class="flex h-7 min-w-10 shrink-0 items-center justify-center rounded-md bg-background px-2 text-xs font-bold" aria-hidden="true">{{ $record->code }}</span>
                        @endif
                        <div class="min-w-0 flex-1">
                            <a href="{{ route('reference.edit', [$type->value, $record->id]) }}" class="row-link break-words" aria-label="Edit {{ $record->name }}"><span>{{ $record->name }}</span></a>
                            @unless($record->is_active)<x-badge tone="neutral" class="ml-2">Switched off</x-badge>@endunless
                            @if($type->value === 'categories' && $record->description)<p class="text-sm break-words text-text-secondary">{{ \Illuminate\Support\Str::limit($record->description, 120) }}</p>@endif
                        </div>
                        <p class="shrink-0 text-right text-xs text-text-secondary">{{ $used ? number_format($used).' '.$usedBy($used) : 'Not used yet' }}</p>
                    </li>
                @endforeach
            </ul>
        @endif
        @if($records->hasPages())<div class="border-t border-border p-4">{{ $records->links() }}</div>@endif
    </div>
</x-layouts.app>
