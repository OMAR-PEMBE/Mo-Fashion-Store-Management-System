@props(['title' => null])
<section {{ $attributes->class(['rounded-2xl border border-border bg-surface p-6']) }}>
    @if($title)<h2 class="mb-4 text-lg font-semibold">{{ $title }}</h2>@endif
    {{ $slot }}
</section>
