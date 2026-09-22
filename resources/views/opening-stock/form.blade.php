<x-layouts.app :title="$review ? 'Review opening stock' : 'Enter opening stock'">
    <a href="{{ route('opening-stock.index') }}" class="mb-5 inline-block text-sm underline">Back to opening stock</a>
    <h1 class="text-3xl font-bold">{{ $review ? 'Review opening stock' : 'Enter opening stock' }}</h1>
    <p class="mb-7 mt-3 break-words text-sm text-text-secondary">{{ $variant->product->name }} · {{ $variant->sku }} · {{ $variant->size?->name ?? 'One size' }} / {{ $variant->colour?->name ?? 'No colour' }}</p>
    <x-card class="max-w-2xl"><form method="POST" action="{{ route($review ? 'opening-stock.confirm' : 'opening-stock.review', $variant) }}" class="space-y-6">@csrf
        @if($errors->any())<div role="alert" class="text-sm text-danger">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
        @if($review)
            <dl class="grid grid-cols-2 gap-5 text-sm"><div><dt class="text-text-secondary">Existing quantity</dt><dd class="mt-2 text-xl font-semibold">{{ $data['quantity'] }}</dd></div><div><dt class="text-text-secondary">Unit cost / Initial WAC (TZS)</dt><dd class="mt-2 break-all text-xl font-semibold">{{ $data['unit_cost'] }}</dd></div></dl>
            <input type="hidden" name="quantity" value="{{ $data['quantity'] }}"><input type="hidden" name="unit_cost" value="{{ $data['unit_cost'] }}">
            <p class="text-sm text-text-secondary">Confirming records this stock and its initial average cost permanently. Check the physical count and cost before proceeding. This entry cannot be repeated or edited here.</p>
            <x-button type="submit">Confirm opening stock</x-button><a href="{{ route('opening-stock.create', $variant) }}" class="ml-4 text-sm underline">Start over</a>
        @else
            <x-input name="quantity" label="Existing quantity" type="number" min="1" max="2147483647" step="1" :value="old('quantity')" required />
            <x-input name="unit_cost" label="Unit cost (TZS)" inputmode="decimal" maxlength="16" :value="old('unit_cost')" required help="The cost paid per unit, not the selling price. Use at most two decimal places." />
            <p class="text-sm text-text-secondary">Only unused variants with zero stock and no movement history can receive opening stock.</p>
            <x-button type="submit">Review opening stock</x-button>
        @endif
    </form></x-card>
</x-layouts.app>
