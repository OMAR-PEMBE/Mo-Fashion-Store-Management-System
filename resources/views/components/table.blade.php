@props(['caption'])
<div class="overflow-x-auto rounded-xl border border-border" role="region" aria-label="{{ $caption }}" tabindex="0">
    <table {{ $attributes->class(['w-full text-left text-sm [&_th]:px-4 [&_th]:py-3 [&_th]:font-semibold [&_td]:border-t [&_td]:border-border [&_td]:px-4 [&_td]:py-3 [&_tbody_tr:hover]:bg-selected']) }}>
        <caption class="sr-only">{{ $caption }}</caption>
        {{ $slot }}
    </table>
</div>
