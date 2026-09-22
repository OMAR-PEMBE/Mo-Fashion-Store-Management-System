@props(['name', 'label', 'id' => null])
@php($id = $id ?? $name)
<div>
    <label for="{{ $id }}" class="mb-2 block text-sm font-medium">{{ $label }}</label>
    <select id="{{ $id }}" name="{{ $name }}" @if($errors->has($name)) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif {{ $attributes->class(['min-h-11 w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm']) }}>{{ $slot }}</select>
    @error($name)<p id="{{ $id }}-error" class="mt-2 text-sm text-danger">{{ $message }}</p>@enderror
</div>
