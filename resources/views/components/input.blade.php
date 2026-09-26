@props(['name', 'label', 'type' => 'text', 'id' => null, 'help' => null, 'revealable' => false, 'icon' => null])
@php
    $id = $id ?? $name;
    // Decorative leading icons; the label always carries the meaning.
    $iconPaths = [
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3.5 6.5 8.5 6.5 8.5-6.5"/>',
        'lock' => '<rect x="4.5" y="10.5" width="15" height="10" rx="2"/><path d="M8 10.5V7.5a4 4 0 0 1 8 0v3"/><path d="M12 14.5v2"/>',
    ];
@endphp
<div>
    <label for="{{ $id }}" class="mb-2 block text-sm font-medium">{{ $label }}</label>
    <div @class(['relative' => $revealable || $icon])>
        <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" @if($errors->has($name)) aria-invalid="true" aria-describedby="{{ $id }}-error" @elseif($help) aria-describedby="{{ $id }}-help" @endif {{ $attributes->class(['peer min-h-11 w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm', 'pl-10' => $icon, 'pr-12' => $revealable, 'border-danger' => $errors->has($name)]) }}>
        @if($icon)
            <svg class="pointer-events-none absolute top-1/2 left-3 size-5 -translate-y-1/2 text-text-secondary transition-colors peer-focus:text-text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $iconPaths[$icon] !!}</svg>
        @endif
        @if($revealable)
            {{-- Enhanced by resources/js/app.js; without JavaScript the field stays a normal password input. --}}
            <button type="button" data-password-toggle="{{ $id }}" aria-controls="{{ $id }}" aria-pressed="false" aria-label="Show password" hidden
                class="absolute inset-y-0 right-0 flex w-11 items-center justify-center rounded-r-lg text-text-secondary hover:text-text-primary">
                <svg data-icon="show" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg data-icon="hide" class="hidden size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 3l18 18"/><path d="M10.6 5.1A10.4 10.4 0 0 1 12 5c6.5 0 10 7 10 7a17.6 17.6 0 0 1-3.2 4.2M6.6 6.6C3.6 8.5 2 12 2 12s3.5 7 10 7a9.8 9.8 0 0 0 5.4-1.6"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>
            </button>
        @endif
    </div>
    @if($help)<p id="{{ $id }}-help" class="mt-2 text-xs text-text-secondary">{{ $help }}</p>@endif
    @error($name)<p id="{{ $id }}-error" class="mt-2 text-sm text-danger">{{ $message }}</p>@enderror
</div>
