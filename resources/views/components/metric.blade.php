@props(['label', 'value', 'money' => false])
<div class="rounded-xl border border-border bg-surface p-5"><p class="text-sm text-text-secondary">{{ $label }}</p><p class="mt-3 break-words text-2xl font-bold">@if($money)<span class="text-sm font-medium">TZS </span>@endif{{ $value }}</p></div>
