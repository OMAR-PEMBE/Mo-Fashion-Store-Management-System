@php
    $name = collect([$variant->size?->name, $variant->colour?->name])->filter()->join(' · ') ?: 'One size';
@endphp
<x-layouts.app :title="$review ? 'Review opening stock' : 'Enter opening stock'">
    <x-page-header :title="$review ? 'Review opening stock' : 'Enter opening stock'" :back="route('opening-stock.index')" back-label="Opening stock"
        :description="$variant->product->name.' · '.$name.' · '.$variant->sku" />

    <form method="POST" action="{{ route($review ? 'opening-stock.confirm' : 'opening-stock.review', $variant) }}" class="max-w-xl space-y-6 rounded-2xl border border-border bg-surface p-5 sm:p-6" data-busy>
        @csrf
        @if($errors->any())<div role="alert" class="rounded-xl border border-danger/30 bg-danger/5 p-4 text-sm text-danger">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
        @if($review)
            <dl class="grid grid-cols-2 gap-5 text-sm">
                <div><dt class="text-text-secondary">In the shop</dt><dd class="mt-1 text-xl font-bold tabular-nums">{{ number_format($data['quantity']) }}</dd></div>
                <div><dt class="text-text-secondary">Cost each</dt><dd class="mt-1 text-xl font-bold tabular-nums">@money($data['unit_cost'])</dd></div>
            </dl>
            <input type="hidden" name="quantity" value="{{ $data['quantity'] }}"><input type="hidden" name="unit_cost" value="{{ $data['unit_cost'] }}">
            <p class="text-sm text-text-secondary">Saving adds this stock and sets its starting cost. It is done once per item and cannot be edited here afterwards.</p>
            <div class="flex flex-wrap items-center gap-4"><x-button type="submit" data-busy-label="Saving…">Save opening stock</x-button><a href="{{ route('opening-stock.create', $variant) }}" class="text-sm font-semibold underline underline-offset-4">Start over</a></div>
        @else
            <x-input name="quantity" label="How many are in the shop" type="number" min="1" max="2147483647" step="1" :value="old('quantity')" required />
            <x-input name="unit_cost" label="Cost each (TZS)" inputmode="decimal" maxlength="16" :value="old('unit_cost')" required help="What you paid per piece, not the selling price." />
            <x-button type="submit">Review</x-button>
        @endif
    </form>
</x-layouts.app>
