@props(['action', 'recent', 'value' => '', 'heading' => 'Which sale?', 'emptyText' => 'No recent sales.', 'windowText' => null])
{{-- Step one of returns, refunds and exchanges: type a sale number or tap a recent sale. --}}
<section class="mb-6 rounded-2xl border border-border bg-surface p-5 sm:p-6" aria-labelledby="sale-picker-heading">
    <h2 id="sale-picker-heading" class="text-base font-semibold">{{ $heading }}</h2>
    <form method="GET" action="{{ $action }}" class="mt-4 flex flex-wrap items-end gap-3">
        <div class="min-w-0 flex-1 sm:max-w-sm">
            <label for="sale_number" class="mb-2 block text-sm font-medium">Sale number</label>
            <input id="sale_number" name="sale_number" value="{{ $value }}" required maxlength="100" autocomplete="off" placeholder="For example MFS-SAL-000123"
                class="min-h-11 w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm">
        </div>
        <x-button type="submit" variant="secondary">Find sale</x-button>
    </form>
    <h3 class="mt-6 text-sm font-semibold">Or pick a recent sale</h3>
    @if($windowText)<p class="text-xs text-text-secondary">{{ $windowText }}</p>@endif
    @if($recent->isEmpty())
        <p class="mt-3 rounded-xl border border-dashed border-border p-5 text-center text-sm text-text-secondary">{{ $emptyText }}</p>
    @else
        <ul class="mt-3 grid gap-2 sm:grid-cols-2">
            @foreach($recent as $sale)
                <li class="relative rounded-xl border border-border px-4 py-3 text-sm hover:border-primary hover:bg-selected/40">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <a href="{{ $action }}?sale_number={{ urlencode($sale['number']) }}" class="row-link break-words">{{ $sale['customer'] }}</a>
                            <p class="text-xs text-text-secondary">{{ $sale['number'] }} · {{ $sale['completed_at']->format('j M, H:i') }}</p>
                        </div>
                        <p class="shrink-0 font-semibold tabular-nums">@money($sale['total'])</p>
                    </div>
                    @if($sale['deadline'])<p class="mt-1 text-xs text-text-secondary">Window closes {{ $sale['deadline']->format('j M, H:i') }}</p>@endif
                </li>
            @endforeach
        </ul>
    @endif
</section>
