@php
    $units = collect($rows)->sum('quantity');
    $value = collect($rows)->reduce(fn ($sum, $row) => $sum->plus(\Brick\Math\BigDecimal::of($row['unit_cost'])->multipliedBy($row['quantity'])), \Brick\Math\BigDecimal::zero());
    $query = parse_url($back, PHP_URL_QUERY);
    parse_str((string) $query, $keep);
@endphp
<x-layouts.app title="Check opening stock">
    <x-page-header title="Check these counts" :back="$back" back-label="Opening stock"
        description="Saving adds this stock and sets each item's starting cost. It is done once per item and cannot be edited here afterwards." />

    <form method="POST" action="{{ route('opening-stock.sheet.confirm') }}" class="space-y-6" data-busy>
        @csrf
        @foreach($keep as $key => $value_)<input type="hidden" name="{{ $key }}" value="{{ $value_ }}">@endforeach
        <section class="overflow-hidden rounded-2xl border border-border bg-surface" aria-labelledby="rows-heading">
            <h2 id="rows-heading" class="sr-only">Counts to save</h2>
            <ul class="divide-y divide-border">
                @foreach($rows as $id => $row)
                    @php $variant = $variants[$id]; @endphp
                    <li class="flex items-start justify-between gap-4 px-5 py-4 text-sm">
                        <div class="min-w-0">
                            <p class="font-semibold break-words">{{ $variant->product->name }}</p>
                            <p class="text-xs text-text-secondary">{{ collect([$variant->size?->name, $variant->colour?->name, $variant->sku])->filter()->join(' · ') }}</p>
                            <p class="mt-1 text-xs text-text-secondary">{{ number_format($row['quantity']) }} × @money($row['unit_cost']) each</p>
                        </div>
                        <p class="shrink-0 font-semibold tabular-nums">@money(\Brick\Math\BigDecimal::of($row['unit_cost'])->multipliedBy($row['quantity']))</p>
                        <input type="hidden" name="items[{{ $id }}][quantity]" value="{{ $row['quantity'] }}"><input type="hidden" name="items[{{ $id }}][unit_cost]" value="{{ $row['unit_cost'] }}">
                    </li>
                @endforeach
            </ul>
            <div class="flex flex-wrap items-baseline justify-between gap-2 border-t border-border bg-background/60 px-5 py-4 text-sm">
                <span class="font-semibold">{{ count($rows) }} {{ \Illuminate\Support\Str::plural('item', count($rows)) }} · {{ number_format($units) }} units</span>
                <span><span class="text-text-secondary">Stock value at cost</span> <span class="ml-2 text-xl font-bold tabular-nums">@money($value)</span></span>
            </div>
        </section>
        <div class="flex flex-wrap items-center gap-3">
            <x-button type="submit" data-busy-label="Saving…">Save opening stock</x-button>
            <x-button type="submit" variant="outline" name="action" value="edit">Go back and change</x-button>
        </div>
    </form>
</x-layouts.app>
