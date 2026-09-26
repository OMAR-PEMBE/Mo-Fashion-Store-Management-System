@props(['title', 'description' => null, 'tone' => 'action'])
{{-- The single thing that moves a record forward, shown above its details. tone: action (gold), done (green), closed (grey), blocked (red). --}}
<section x-data {{ $attributes->class(['mb-6 flex flex-wrap items-center justify-between gap-4 rounded-2xl border p-5',
    'border-primary/40 bg-selected/60' => $tone === 'action', 'border-success/30 bg-success/5' => $tone === 'done',
    'border-border bg-surface' => $tone === 'closed', 'border-danger/30 bg-danger/5' => $tone === 'blocked']) }} aria-label="{{ $tone === 'action' ? 'Next step' : 'Status' }}">
    <div class="max-w-xl">
        @if($tone === 'action')<p class="text-xs font-semibold tracking-wide text-text-secondary uppercase">Next step</p>@endif
        <h2 @class(['text-lg font-bold', 'mt-1' => $tone === 'action', 'text-danger' => $tone === 'blocked'])>{{ $title }}</h2>
        @if($description)<p class="mt-1 text-sm text-text-secondary">{{ $description }}</p>@endif
    </div>
    @if($slot->isNotEmpty())<div class="flex flex-wrap gap-3">{{ $slot }}</div>@endif
</section>
