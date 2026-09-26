@php
    $filtered = ($filters['q'] ?? null) || ($filters['status'] ?? null) || ($filters['role_id'] ?? null);
    $lastSeen = fn ($user) => $user->last_login_at ? 'Last signed in '.$user->last_login_at->diffForHumans() : 'Never signed in';
@endphp
<x-layouts.app title="Staff & access">
    <x-page-header title="Staff & access" description="Who can sign in and what their role lets them do. Switch accounts off instead of deleting them, so their sales stay linked to them.">
        <x-slot:actions>
            <a href="{{ route('roles.index') }}" class="inline-flex min-h-11 items-center rounded-lg border border-border bg-surface px-4 text-sm font-semibold hover:bg-background">What each role can do</a>
            <x-action-link :href="route('users.create')">Add staff member</x-action-link>
        </x-slot:actions>
    </x-page-header>

    <form method="GET" class="mb-4 flex flex-wrap items-center gap-3" role="search">
        @if($filters['status'] ?? null)<input type="hidden" name="status" value="{{ $filters['status'] }}">@endif
        <div class="relative w-full max-w-md">
            <label for="staff-search" class="sr-only">Search staff</label>
            <svg class="pointer-events-none absolute top-1/2 left-3.5 size-5 -translate-y-1/2 text-text-secondary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <input id="staff-search" name="q" type="search" value="{{ $filters['q'] ?? '' }}" maxlength="150" placeholder="Name or email" class="min-h-11 w-full rounded-lg border border-border bg-surface py-2 pr-3 pl-11 text-sm">
        </div>
        <label for="role-filter" class="sr-only">Role</label>
        <select id="role-filter" name="role_id" x-data @change="$el.form.requestSubmit()" class="min-h-11 rounded-lg border border-border bg-surface px-3 text-sm">
            <option value="">All roles</option>
            @foreach($roles as $role)<option value="{{ $role->id }}" @selected(($filters['role_id'] ?? '') == $role->id)>{{ $role->name }}</option>@endforeach
        </select>
    </form>
    <x-filter-tabs :options="['' => 'All', 'active' => 'Can sign in', 'inactive' => 'Switched off']" :counts="$counts" class="mb-5" />

    <div class="overflow-hidden rounded-2xl border border-border bg-surface">
        @if($users->isEmpty())
            <div class="p-10 text-center"><p class="font-semibold">No staff accounts found.</p>@if($filtered)<a href="{{ route('users.index') }}" class="mt-2 inline-block text-sm font-semibold underline underline-offset-4">Clear search</a>@endif</div>
        @else
            <ul class="divide-y divide-border">
                @foreach($users as $user)
                    <li @class(['relative flex items-center gap-4 px-5 py-4 hover:bg-selected/50', 'opacity-70' => ! $user->is_active])>
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-text-primary text-sm font-bold text-white" aria-hidden="true">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="flex flex-wrap items-center gap-2">
                                <a class="row-link break-words" href="{{ route('users.edit', $user) }}">{{ $user->name }}</a>
                                @if($user->id === auth()->id())<span class="text-xs text-text-secondary">(you)</span>@endif
                                @unless($user->is_active)<x-badge tone="neutral">Switched off</x-badge>@endunless
                            </p>
                            <p class="text-sm break-all text-text-secondary">{{ $user->email }}</p>
                            <p class="text-xs text-text-secondary sm:hidden">{{ $user->role?->name ?? 'No role' }} · {{ $lastSeen($user) }}</p>
                        </div>
                        <div class="hidden shrink-0 text-right text-sm sm:block">
                            <x-badge :tone="$user->role?->slug === 'administrator' ? 'warning' : 'neutral'">{{ $user->role?->name ?? 'No role' }}</x-badge>
                            <p class="mt-1 text-xs text-text-secondary" @if($user->last_login_at) title="{{ $user->last_login_at->format('j M Y, H:i') }}" @endif>{{ $lastSeen($user) }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
        @if($users->hasPages())<div class="border-t border-border p-4">{{ $users->links() }}</div>@endif
    </div>
</x-layouts.app>
