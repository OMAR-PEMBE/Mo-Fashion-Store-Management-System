@php
    $summaries = [
        'administrator' => 'Everything, including staff, settings, refunds and the activity log.',
        'salesperson' => 'Sells at the counter, takes orders, returns and exchanges, and adds customers.',
    ];
@endphp
<x-layouts.app title="Role permissions">
    <x-page-header title="What each role can do" :back="route('users.index')" back-label="Staff & access"
        description="Everyone in a role gets the same permissions. Changes apply from their next page load." />

    <ul class="grid gap-4 sm:grid-cols-2">
        @foreach($roles as $role)
            <li class="relative rounded-2xl border border-border bg-surface p-5 hover:border-primary hover:bg-selected/40">
                <div class="flex items-start justify-between gap-3">
                    <a class="row-link text-lg" href="{{ route('roles.edit', $role) }}">{{ $role->name }}</a>
                    <span class="shrink-0 text-sm text-text-secondary">{{ number_format($role->permissions_count) }} of {{ number_format($total) }} permissions</span>
                </div>
                <p class="mt-1 text-sm text-text-secondary">{{ $summaries[$role->slug] ?? 'A custom role.' }}</p>
                <p class="mt-3 text-sm">{{ number_format($role->active_users_count) }} {{ \Illuminate\Support\Str::plural('person', $role->active_users_count) }} can sign in with this role @if($role->users_count > $role->active_users_count) · {{ $role->users_count - $role->active_users_count }} switched off @endif</p>
                <p class="mt-3 text-sm font-semibold underline underline-offset-4" aria-hidden="true">Change what they can do</p>
            </li>
        @endforeach
    </ul>
</x-layouts.app>
