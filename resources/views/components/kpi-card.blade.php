@props(['label', 'value', 'description' => null])
<x-card {{ $attributes }}>
    <p class="text-sm text-text-secondary">{{ $label }}</p>
    <p class="mt-3 text-3xl font-bold">{{ $value }}</p>
    @if($description)<p class="mt-2 text-xs text-text-secondary">{{ $description }}</p>@endif
</x-card>
