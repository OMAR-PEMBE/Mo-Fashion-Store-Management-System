@props(['secondary' => false])
<a {{ $attributes->class(['inline-flex min-h-11 items-center justify-center rounded-lg px-4 py-2 text-sm font-semibold', 'bg-text-primary text-white hover:bg-zinc-700' => $secondary, 'bg-primary text-text-primary hover:bg-primary-hover' => !$secondary]) }}>{{ $slot }}</a>
