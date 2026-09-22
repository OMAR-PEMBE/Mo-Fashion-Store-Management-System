@props(['name', 'label', 'type' => 'text', 'id' => null, 'help' => null])
@php($id = $id ?? $name)
<div>
    <label for="{{ $id }}" class="mb-2 block text-sm font-medium">{{ $label }}</label>
    <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" @if($errors->has($name)) aria-invalid="true" aria-describedby="{{ $id }}-error" @elseif($help) aria-describedby="{{ $id }}-help" @endif {{ $attributes->class(['min-h-11 w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm', 'border-danger' => $errors->has($name)]) }}>
    @if($help)<p id="{{ $id }}-help" class="mt-2 text-xs text-text-secondary">{{ $help }}</p>@endif
    @error($name)<p id="{{ $id }}-error" class="mt-2 text-sm text-danger">{{ $message }}</p>@enderror
</div>
