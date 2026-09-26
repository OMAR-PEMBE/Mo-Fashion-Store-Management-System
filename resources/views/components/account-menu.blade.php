@props(['user', 'initials', 'dark' => false])
<details {{ $attributes->class(['relative']) }} x-data @click.outside="$el.removeAttribute('open')" @keydown.escape="$el.removeAttribute('open')">
    <summary @class(['flex cursor-pointer list-none items-center gap-3 rounded-lg px-2 py-1.5 [&::-webkit-details-marker]:hidden', 'hover:bg-white/10' => $dark, 'hover:bg-background' => ! $dark])>
        <span @class(['flex size-9 items-center justify-center rounded-full text-xs font-bold', 'bg-white text-text-primary' => $dark, 'bg-text-primary text-white' => ! $dark]) aria-hidden="true">{{ $initials ?: '?' }}</span>
        @unless($dark)<span class="hidden text-left sm:block"><span class="block text-sm leading-tight font-semibold">{{ $user->name }}</span><span class="block text-xs text-text-secondary">{{ $user->role?->name }}</span></span>@endunless
        <svg @class(['size-4', 'text-white/70' => $dark, 'text-text-secondary' => ! $dark]) viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
        <span class="sr-only">Account menu</span>
    </summary>
    <div class="absolute right-0 z-30 mt-2 w-64 rounded-xl border border-border bg-surface p-2 text-text-primary shadow-lg">
        <div class="border-b border-border px-3 pt-2 pb-3">
            <p class="text-sm font-semibold break-words">{{ $user->name }}</p>
            <p class="text-xs break-words text-text-secondary">{{ $user->email }}</p>
        </div>
        <a href="{{ route('profile') }}" class="mt-1 flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm hover:bg-background">
            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>Profile &amp; password</a>
        <form method="POST" action="{{ route('logout') }}">@csrf
            <button type="submit" class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm hover:bg-background">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3M10 17l5-5-5-5M15 12H4"/></svg>Sign out</button>
        </form>
    </div>
</details>
