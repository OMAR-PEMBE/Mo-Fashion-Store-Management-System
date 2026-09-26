@props(['title', 'description' => null, 'back' => null, 'backLabel' => null])
{{-- Shared page heading: optional back link, title, one-line description and actions on the right. --}}
<div {{ $attributes->class(['mb-6 flex flex-wrap items-end justify-between gap-4 sm:mb-8']) }}>
    <div class="min-w-0">
        @if($back)
            <a href="{{ $back }}" class="mb-2 inline-flex items-center gap-1.5 text-sm font-semibold text-text-secondary hover:text-text-primary">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>{{ $backLabel }}</a>
        @endif
        <div class="flex flex-wrap items-center gap-3">
            <h1 class="text-2xl font-bold tracking-tight break-words sm:text-3xl">{{ $title }}</h1>
            {{ $badge ?? '' }}
        </div>
        @if($description)<p class="mt-1.5 max-w-2xl text-sm text-text-secondary">{{ $description }}</p>@endif
    </div>
    @isset($actions)<div class="flex flex-wrap gap-3">{{ $actions }}</div>@endisset
</div>
