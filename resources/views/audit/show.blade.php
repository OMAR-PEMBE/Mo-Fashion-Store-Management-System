@use('App\Support\AuditLabels')
@php
    $when = \Illuminate\Support\Carbon::parse($entry->created_at);
    $link = AuditLabels::link($entry->entity_type, $entry->entity_id);
    $record = AuditLabels::type($entry->entity_type).($entry->entity_id === null ? '' : ' #'.$entry->entity_id);
@endphp
<x-layouts.app title="Audit detail">
    <x-page-header :title="AuditLabels::action($entry->action)" :back="route('audit.index')" back-label="Activity log"
        :description="($entry->actor_name ?? 'System').' · '.$when->format('l, j F Y \a\t H:i:s')" />

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_300px] lg:items-start">
        <section class="overflow-hidden rounded-2xl border border-border bg-surface" aria-labelledby="changes-heading">
            <h2 id="changes-heading" class="border-b border-border px-5 py-4 text-base font-semibold">{{ $hasBefore ? 'What changed' : 'What was recorded' }}</h2>
            @if(empty($rows))
                <p class="p-5 text-sm text-text-secondary">No values recorded.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <caption class="sr-only">Values before and after this activity</caption>
                        <thead><tr><th scope="col">Field</th>@if($hasBefore)<th scope="col">Before</th>@endif<th scope="col">{{ $hasBefore ? 'After' : 'Value' }}</th></tr></thead>
                        <tbody>
                            @foreach($rows as $row)
                                <tr @class(['bg-selected/60' => $row['changed']])>
                                    <th scope="row" class="font-medium whitespace-nowrap">{{ AuditLabels::field($row['field']) }}@if($row['changed'])<span class="sr-only"> (changed)</span>@endif</th>
                                    @if($hasBefore)<td class="max-w-xs break-words text-text-secondary">{{ $row['before'] ?? '—' }}</td>@endif
                                    <td @class(['max-w-xs break-words', 'font-semibold' => $row['changed']])>{{ $row['after'] ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if($hasBefore)<p class="border-t border-border px-5 py-3 text-xs text-text-secondary">Highlighted rows changed. Older entries may not contain every field.</p>@endif
            @endif
        </section>
        <section class="rounded-2xl border border-border bg-surface p-5 text-sm" aria-labelledby="about-heading">
            <h2 id="about-heading" class="text-base font-semibold">Details</h2>
            <dl class="mt-3 space-y-3">
                <div><dt class="text-text-secondary">Record</dt><dd class="font-semibold">@if($link)<a href="{{ $link }}" class="underline underline-offset-4">{{ $record }}</a>@else{{ $record }}@endif</dd></div>
                <div><dt class="text-text-secondary">Done by</dt><dd>{{ $entry->actor_name ?? 'System' }}</dd></div>
                <div><dt class="text-text-secondary">Activity code</dt><dd class="font-mono text-xs">{{ $entry->action }}</dd></div>
                <div><dt class="text-text-secondary">Network address</dt><dd>{{ $entry->ip_address ?? 'Not recorded' }}</dd></div>
                <div><dt class="text-text-secondary">Log entry</dt><dd>#{{ $entry->id }}</dd></div>
            </dl>
            <p class="mt-4 text-xs text-text-secondary">Passwords and secret keys are always hidden here.</p>
        </section>
    </div>
</x-layouts.app>
