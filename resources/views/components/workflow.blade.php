@props(['steps', 'current', 'label' => 'Progress'])
{{-- Progress through a fixed workflow: dots on wider screens, a compact bar on phones.
     $current is the 0-based index of the step in progress; pass count($steps) when every step is finished. --}}
@php
    $count = count($steps);
    $finished = $current >= $count;
    $shown = min($current, $count - 1);
@endphp
<div {{ $attributes->class(['mb-6']) }}>
    <div class="rounded-2xl border border-border bg-surface p-4 sm:hidden">
        <div class="flex items-baseline justify-between text-sm"><p class="font-semibold">{{ $finished ? $steps[$count - 1] : $steps[$shown] }}</p><p class="text-xs text-text-secondary">{{ $finished ? 'All steps done' : 'Step '.($shown + 1).' of '.$count }}</p></div>
        <div class="mt-2 h-1.5 rounded-full bg-background" aria-hidden="true"><div @class(['h-1.5 rounded-full', 'bg-success' => $finished, 'bg-primary' => ! $finished]) style="width: {{ $finished ? 100 : round(($shown + 1) / $count * 100) }}%"></div></div>
        @if(! $finished && $shown + 1 < $count)<p class="mt-2 text-xs text-text-secondary">Then: {{ $steps[$shown + 1] }}</p>@endif
    </div>
    <ol class="hidden rounded-2xl border border-border bg-surface p-5 sm:grid" style="grid-template-columns: repeat({{ $count }}, minmax(0, 1fr))" aria-label="{{ $label }}">
        @foreach($steps as $index => $step)
            @php $state = $index < $current ? 'done' : ($index === $current ? 'current' : 'todo'); @endphp
            <li class="relative flex flex-col items-center text-center" @if($state === 'current') aria-current="step" @endif>
                @if($index > 0)<span @class(['absolute top-3.5 right-1/2 h-0.5 w-full', 'bg-primary' => $index <= $current, 'bg-border' => $index > $current]) aria-hidden="true"></span>@endif
                <span @class(['relative z-10 flex size-7 items-center justify-center rounded-full text-xs font-bold',
                    'bg-primary text-text-primary' => $state === 'done', 'bg-text-primary text-white ring-4 ring-selected' => $state === 'current', 'border-2 border-border bg-surface text-text-secondary' => $state === 'todo'])>
                    @if($state === 'done')<svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 5 5 9-10"/></svg>@else{{ $index + 1 }}@endif
                </span>
                <span @class(['mt-2 text-xs', 'font-semibold' => $state !== 'todo', 'text-text-secondary' => $state === 'todo'])>{{ $step }}<span class="sr-only">{{ $state === 'done' ? ' (done)' : ($state === 'current' ? ' (current step)' : '') }}</span></span>
            </li>
        @endforeach
    </ol>
</div>
