@props(['title', 'description' => null])
<div {{ $attributes->class(['rounded-xl border border-dashed border-border p-10 text-center']) }}>
    <h2 class="text-lg font-semibold">{{ $title }}</h2>
    @if($description)<p class="mt-2 text-sm text-text-secondary">{{ $description }}</p>@endif
    {{ $slot }}
</div>
