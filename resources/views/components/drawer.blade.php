@props(['name', 'title'])
<dialog x-data @open-drawer.window="if ($event.detail === @js($name)) $el.showModal()" @click="if ($event.target === $el) $el.close()" aria-labelledby="{{ $name }}-title" {{ $attributes->class(['fixed inset-y-0 right-0 left-auto m-0 h-dvh max-h-none w-full max-w-md overflow-y-auto border-l border-border bg-surface p-6 text-text-primary backdrop:bg-black/40']) }}>
    <div class="mb-5 flex items-center justify-between gap-4"><h2 id="{{ $name }}-title" class="text-lg font-semibold">{{ $title }}</h2><x-button variant="secondary" @click="$el.closest('dialog').close()" aria-label="Close drawer">Close</x-button></div>
    {{ $slot }}
</dialog>
