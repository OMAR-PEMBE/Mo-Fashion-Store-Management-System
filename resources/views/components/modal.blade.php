@props(['name', 'title'])
<dialog x-data @open-modal.window="if ($event.detail === @js($name)) $el.showModal()" @click="if ($event.target === $el) $el.close()" aria-labelledby="{{ $name }}-title" {{ $attributes->class(['fixed inset-0 m-auto max-h-[85vh] w-[calc(100%-2rem)] max-w-lg overflow-y-auto rounded-2xl border border-border bg-surface p-6 text-text-primary backdrop:bg-black/40']) }}>
    <div class="mb-5 flex items-center justify-between gap-4"><h2 id="{{ $name }}-title" class="text-lg font-semibold">{{ $title }}</h2><x-button variant="secondary" @click="$el.closest('dialog').close()" aria-label="Close dialog">Close</x-button></div>
    {{ $slot }}
</dialog>
