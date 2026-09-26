@use('App\Support\AuditLabels')
@php
    $advanced = ($filters['entity_type'] ?? null) || ($filters['entity_id'] ?? null) || ($filters['date_from'] ?? null) || ($filters['date_to'] ?? null);
    $filtered = $advanced || ($filters['user_id'] ?? null) || ($filters['action'] ?? null);
    $days = $entries->getCollection()->groupBy(fn ($entry) => \Illuminate\Support\Carbon::parse($entry->created_at)->toDateString());
    $dayLabel = function (string $date) {
        $day = \Illuminate\Support\Carbon::parse($date);

        return $day->isToday() ? 'Today' : ($day->isYesterday() ? 'Yesterday' : $day->format('l, j F Y'));
    };
@endphp
<x-layouts.app title="Audit logs">
    <x-page-header title="Activity log" description="Who did what, and when. Nothing here can be edited or deleted. Times are Dar es Salaam time." />

    <form method="GET" class="mb-6 space-y-3">
        <div class="flex flex-wrap items-end gap-3">
            <div class="w-full sm:w-56"><x-select name="user_id" label="Who" x-data x-on:change="$el.form.requestSubmit()"><option value="">Anyone</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected(($filters['user_id'] ?? '') == $user->id)>{{ $user->name }}</option>@endforeach</x-select></div>
            <div class="w-full sm:w-72"><x-select name="action" label="What" x-data x-on:change="$el.form.requestSubmit()"><option value="">Any activity</option>@foreach(AuditLabels::actions($actions) as $code => $label)<option value="{{ $code }}" @selected(($filters['action'] ?? '') === $code)>{{ $label }}</option>@endforeach</x-select></div>
            @if($filtered)<a href="{{ route('audit.index') }}" class="py-3 text-sm font-semibold underline underline-offset-4">Clear all</a>@endif
        </div>
        <details class="rounded-2xl border border-border bg-surface" @if($advanced) open @endif>
            <summary class="cursor-pointer px-5 py-3 text-sm font-semibold">Dates and a specific record</summary>
            <div class="grid gap-4 border-t border-border p-5 sm:grid-cols-2 lg:grid-cols-5">
                <x-input name="date_from" label="From" type="date" :value="$filters['date_from'] ?? ''" />
                <x-input name="date_to" label="To" type="date" :value="$filters['date_to'] ?? ''" />
                <x-select name="entity_type" label="Kind of record"><option value="">Any</option>@foreach($types as $type)<option value="{{ $type }}" @selected(($filters['entity_type'] ?? '') === $type)>{{ AuditLabels::type($type) }}</option>@endforeach</x-select>
                <x-input name="entity_id" label="Record number" type="number" min="1" :value="$filters['entity_id'] ?? ''" />
                <div class="flex items-end"><x-button type="submit" variant="secondary">Apply</x-button></div>
            </div>
        </details>
    </form>

    @if($entries->isEmpty())
        <div class="rounded-2xl border border-border bg-surface p-10 text-center"><p class="font-semibold">No matching activity.</p>@if($filtered)<p class="mt-1 text-sm text-text-secondary">Try another person, activity or date range.</p>@endif</div>
    @else
        <div class="space-y-6">
            @foreach($days as $date => $dayEntries)
                <section aria-labelledby="day-{{ $date }}">
                    <h2 id="day-{{ $date }}" class="mb-2 text-xs font-semibold tracking-widest text-text-secondary uppercase">{{ $dayLabel($date) }}</h2>
                    <ol class="divide-y divide-border overflow-hidden rounded-2xl border border-border bg-surface">
                        @foreach($dayEntries as $entry)
                            <li class="relative flex items-start gap-4 px-5 py-3 text-sm hover:bg-selected/50">
                                <time class="w-12 shrink-0 pt-0.5 text-xs text-text-secondary tabular-nums" datetime="{{ \Illuminate\Support\Carbon::parse($entry->created_at)->toIso8601String() }}">{{ \Illuminate\Support\Carbon::parse($entry->created_at)->format('H:i') }}</time>
                                <div class="min-w-0 flex-1">
                                    <a href="{{ route('audit.show', $entry->id) }}" class="row-link">{{ AuditLabels::action($entry->action) }}</a>
                                    <p class="text-xs text-text-secondary">{{ $entry->actor_name ?? 'System' }} · {{ AuditLabels::type($entry->entity_type) }}{{ $entry->entity_id === null ? '' : ' #'.$entry->entity_id }} · <span class="font-mono">{{ $entry->action }}</span></p>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </section>
            @endforeach
        </div>
        @if($entries->hasPages())<div class="mt-4">{{ $entries->links() }}</div>@endif
    @endif
</x-layouts.app>
