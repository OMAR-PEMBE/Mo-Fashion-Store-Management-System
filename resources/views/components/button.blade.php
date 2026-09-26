@props(['variant' => 'primary', 'type' => 'button'])
@php
    $style = match ($variant) {
        'secondary' => 'bg-text-primary text-white hover:bg-zinc-700',
        'danger' => 'bg-danger text-white hover:brightness-90',
        'outline' => 'border border-border bg-surface text-text-primary hover:bg-background',
        default => 'bg-primary text-text-primary hover:bg-primary-hover',
    };
@endphp
<button type="{{ $type }}" {{ $attributes->class(['inline-flex min-h-11 items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold transition disabled:cursor-not-allowed disabled:opacity-50', $style]) }}>{{ $slot }}</button>
