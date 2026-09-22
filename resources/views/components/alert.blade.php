@props(['tone' => 'info'])
<div role="{{ $tone === 'danger' ? 'alert' : 'status' }}" {{ $attributes->class(['rounded-xl border border-border bg-surface p-4 text-sm']) }}>
    <x-badge :tone="$tone">{{ ucfirst($tone) }}</x-badge>
    <div class="mt-2 leading-6">{{ $slot }}</div>
</div>
