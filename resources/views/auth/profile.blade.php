@use('App\Support\PermissionCatalog')
@php
    $user = auth()->user();
    $mustChange = $user->must_change_password;
    $allowed = $user->role?->permissions->pluck('slug')->all() ?? [];
    $canDo = collect(PermissionCatalog::groups())->map(fn ($items) => collect($items)->filter(fn ($meta, $slug) => in_array($slug, $allowed, true))->map(fn ($meta) => $meta[0])->values())->filter->isNotEmpty();
@endphp
<x-layouts.app title="Your profile">
    <x-page-header title="Your profile" :description="$user->role?->name.' · '.$user->email" />

    @if($mustChange)
        <x-next-step title="Choose your own password to continue" description="You signed in with a temporary password. Pick a new one that only you know, then the rest of the system opens up." />
    @endif

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px] lg:items-start">
        <section @class(['rounded-2xl border bg-surface p-5 sm:p-6', 'border-primary' => $mustChange, 'border-border' => ! $mustChange]) aria-labelledby="password-heading"
            x-data="{ next: '', again: '' }">
            <h2 id="password-heading" class="text-base font-semibold">{{ $mustChange ? 'Set your new password' : 'Change password' }}</h2>
            <form method="POST" action="{{ route('password.update') }}" class="mt-4 space-y-5" data-busy>
                @csrf @method('PUT')
                <x-input name="current_password" :label="$mustChange ? 'Temporary password you were given' : 'Current password'" type="password" autocomplete="current-password" required revealable />
                <x-input name="password" label="New password" type="password" autocomplete="new-password" required minlength="10" maxlength="255" revealable x-on:input="next = $event.target.value" />
                <ul class="-mt-2 grid gap-1 text-xs sm:grid-cols-2" aria-label="Password rules">
                    @foreach([['next.length >= 10', 'At least 10 characters'], ['/[A-Za-z]/.test(next)', 'Includes a letter'], ['/[0-9]/.test(next)', 'Includes a number'], ['next !== \'\' && next === again', 'Both boxes match']] as [$rule, $text])
                        <li class="flex items-center gap-1.5" :class="{{ $rule }} ? 'text-success' : 'text-text-secondary'">
                            <svg class="size-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path x-show="{{ $rule }}" d="m5 12 5 5 9-10"/><circle x-show="!({{ $rule }})" cx="12" cy="12" r="3"/></svg>
                            <span>{{ $text }}<span class="sr-only" x-text="{{ $rule }} ? ' (done)' : ''"></span></span>
                        </li>
                    @endforeach
                </ul>
                <x-input name="password_confirmation" label="Type the new password again" type="password" autocomplete="new-password" required maxlength="255" revealable x-on:input="again = $event.target.value" />
                <x-button type="submit" data-busy-label="Saving…">{{ $mustChange ? 'Save and continue' : 'Update password' }}</x-button>
            </form>
        </section>

        <aside class="space-y-4">
            <section class="rounded-2xl border border-border bg-surface p-5" aria-labelledby="account-heading">
                <div class="flex items-center gap-3">
                    <span class="flex size-12 shrink-0 items-center justify-center rounded-full bg-text-primary text-lg font-bold text-white" aria-hidden="true">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                    <div class="min-w-0"><h2 id="account-heading" class="font-semibold break-words">{{ $user->name }}</h2><p class="text-sm break-all text-text-secondary">{{ $user->email }}</p></div>
                </div>
                <dl class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-text-secondary">Role</dt><dd class="font-semibold">{{ $user->role?->name ?? 'No role' }}</dd></div>
                    @if($user->phone)<div class="flex justify-between gap-3"><dt class="text-text-secondary">Phone</dt><dd>{{ \App\Support\Phone::display($user->phone) }}</dd></div>@endif
                    <div class="flex justify-between gap-3"><dt class="text-text-secondary">Last signed in</dt><dd>{{ $user->last_login_at?->format('j M Y, H:i') ?? 'Now' }}</dd></div>
                </dl>
                <p class="mt-3 text-xs text-text-secondary">Name, email or role wrong? Ask an administrator to change it.</p>
            </section>
            @if($canDo->isNotEmpty())
                <details class="rounded-2xl border border-border bg-surface p-5 text-sm">
                    <summary class="cursor-pointer font-semibold">What your role lets you do</summary>
                    <div class="mt-3 space-y-3">
                        @foreach($canDo as $group => $labels)
                            <div><p class="text-xs font-semibold tracking-widest text-text-secondary uppercase">{{ $group }}</p><p class="mt-0.5">{{ $labels->join(', ') }}</p></div>
                        @endforeach
                    </div>
                </details>
            @endif
        </aside>
    </div>
</x-layouts.app>
