@props(['tone' => 'info'])
@php
    $style = match ($tone) {
        'success' => 'bg-success/10 text-success',
        'warning' => 'bg-warning/20 text-text-primary',
        'danger' => 'bg-danger/10 text-danger',
        'neutral' => 'bg-zinc-100 text-text-secondary',
        default => 'bg-info/10 text-info',
    };
@endphp
<span {{ $attributes->class(['inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold', $style]) }}>{{ $slot }}</span>
