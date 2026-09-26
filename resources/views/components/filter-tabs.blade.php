@props(['options', 'counts' => [], 'param' => 'status', 'label' => 'Filter by status'])
{{-- One-tap status filters that keep the current search. $options: value => label ('' = all). --}}
@php
    $current = (string) request()->query($param, '');
    $total = collect($counts)->sum();
@endphp
<nav aria-label="{{ $label }}" {{ $attributes->class(['-mx-1 flex gap-1.5 overflow-x-auto px-1 pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden']) }}>
    @foreach($options as $value => $text)
        @php
            $active = $current === (string) $value;
            $count = $value === '' ? $total : ($counts[$value] ?? 0);
        @endphp
        <a href="{{ request()->fullUrlWithQuery([$param => $value === '' ? null : $value, 'page' => null]) }}" @if($active) aria-current="page" @endif
            @class(['inline-flex shrink-0 items-center gap-2 rounded-full border px-3.5 py-1.5 text-sm font-semibold transition-colors',
                'border-text-primary bg-text-primary text-white' => $active, 'border-border bg-surface hover:bg-selected' => ! $active])>
            {{ $text }}<span @class(['rounded-full px-1.5 text-xs tabular-nums', 'bg-white/20' => $active, 'bg-background text-text-secondary' => ! $active])>{{ number_format($count) }}</span>
        </a>
    @endforeach
</nav>
