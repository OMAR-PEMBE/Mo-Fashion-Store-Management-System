@props(['label' => 'Loading…'])
<div role="status" {{ $attributes->class(['flex items-center gap-3 text-sm text-text-secondary']) }}>
    <span class="size-5 rounded-full border-2 border-border border-t-info motion-safe:animate-spin" aria-hidden="true"></span>{{ $label }}
</div>
