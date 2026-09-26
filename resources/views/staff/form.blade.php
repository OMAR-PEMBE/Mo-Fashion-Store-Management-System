@php
    $editing = $user->exists;
    $self = $editing && $user->id === auth()->id();
    $roleId = (string) old('role_id', $user->role_id ?? $roles->firstWhere('slug', 'salesperson')?->id);
    $active = (string) old('is_active', (int) ($user->is_active ?? true)) === '1';
    $roleText = [
        'administrator' => 'Everything: staff, settings, refunds, costs and reports.',
        'salesperson' => 'Sells, takes orders, returns and exchanges, adds customers. Cannot change prices or see costs.',
    ];
    $card = 'relative flex cursor-pointer flex-col gap-1 rounded-xl border border-border bg-surface p-4 text-sm hover:bg-background has-[:checked]:border-text-primary has-[:checked]:bg-selected has-[:disabled]:cursor-not-allowed has-[:disabled]:opacity-60 has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-info';
@endphp
<x-layouts.app :title="$editing ? 'Edit staff member' : 'Add staff member'">
    <x-page-header :title="$editing ? $user->name : 'Add staff member'" :back="route('users.index')" back-label="Staff & access"
        :description="$editing ? ($user->last_login_at ? 'Last signed in '.$user->last_login_at->format('j M Y, H:i') : 'Has not signed in yet') : 'They sign in with their email and a temporary password, then choose their own.'">
        <x-slot:badge>@if($editing && ! $user->is_active)<x-badge tone="neutral">Switched off</x-badge>@endif</x-slot:badge>
    </x-page-header>

    @if($errors->any())<div role="alert" class="mb-6 rounded-xl border border-danger/30 bg-danger/5 p-4 text-sm text-danger">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif

    <form method="POST" action="{{ $editing ? route('users.update', $user) : route('users.store') }}" class="max-w-2xl space-y-6" data-busy>
        @csrf @if($editing) @method('PUT')<input type="hidden" name="revision" value="{{ old('revision', $user->revision) }}">@endif

        <section class="space-y-5 rounded-2xl border border-border bg-surface p-5 sm:p-6" aria-labelledby="person-heading">
            <h2 id="person-heading" class="text-base font-semibold">Person</h2>
            <x-input name="name" label="Full name" :value="old('name', $user->name)" required maxlength="150" />
            <div class="grid gap-5 sm:grid-cols-2">
                <x-input name="email" label="Sign-in email" type="email" :value="old('email', $user->email)" required maxlength="191" autocomplete="off" />
                <x-input name="phone" label="Phone (optional)" type="tel" :value="old('phone', $user->phone)" maxlength="30" />
            </div>
        </section>

        <section class="space-y-4 rounded-2xl border border-border bg-surface p-5 sm:p-6" aria-labelledby="access-heading">
            <h2 id="access-heading" class="text-base font-semibold">Access</h2>
            <fieldset>
                <legend class="mb-2 text-sm font-medium">Role</legend>
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach($roles as $role)
                        <label class="{{ $card }}">
                            <input type="radio" name="role_id" value="{{ $role->id }}" class="sr-only" required @checked($roleId === (string) $role->id) @disabled($self && (string) $user->role_id !== (string) $role->id)>
                            <span class="font-semibold">{{ $role->name }}</span>
                            <span class="text-text-secondary">{{ $roleText[$role->slug] ?? '' }}</span>
                        </label>
                    @endforeach
                </div>
                @error('role_id')<p class="mt-2 text-sm text-danger">{{ $message }}</p>@enderror
                <a href="{{ route('roles.index') }}" class="mt-2 inline-block text-xs font-semibold underline underline-offset-4">See exactly what each role can do</a>
            </fieldset>
            @if($self)<input type="hidden" name="is_active" value="1">@else<input type="hidden" name="is_active" value="0">@endif
            <label @class(['flex items-start gap-3 text-sm', 'opacity-60' => $self])><input type="checkbox" name="is_active" value="1" class="mt-0.5 size-4" @checked($active) @disabled($self)><span><span class="block font-semibold">Can sign in</span><span class="block text-text-secondary">{{ $self ? 'You cannot switch off your own account or change your own role. Ask another administrator.' : 'Switch off when someone leaves. Their sales and history stay in the records.' }}</span></span></label>
            @if($editing)<p class="text-xs text-text-secondary">Changing the email, role or sign-in signs them out everywhere.</p>@endif
        </section>

        @unless($editing)
            <section class="space-y-4 rounded-2xl border border-border bg-surface p-5 sm:p-6" aria-labelledby="password-heading" x-data="{ suggested: '' }">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 id="password-heading" class="text-base font-semibold">Temporary password</h2>
                    <button type="button" class="text-sm font-semibold underline underline-offset-4" @click="suggested = suggestPassword(); ['password', 'password_confirmation'].forEach(id => { const el = document.getElementById(id); el.value = suggested; el.type = 'text' })">Suggest one</button>
                </div>
                <p x-show="suggested" x-cloak class="rounded-lg bg-selected px-3 py-2 text-sm">Give <strong class="font-mono tracking-wide" x-text="suggested"></strong> to them privately. They must change it when they first sign in.</p>
                <x-input name="password" label="Temporary password" type="password" required minlength="10" maxlength="255" autocomplete="new-password" revealable help="At least 10 characters with letters and numbers." />
                <x-input name="password_confirmation" label="Type it again" type="password" required maxlength="255" autocomplete="new-password" revealable />
            </section>
        @endunless

        <section class="space-y-4 rounded-2xl border border-border bg-surface p-5 sm:p-6">
            <x-input name="current_password" label="Your password, to confirm" type="password" required maxlength="255" autocomplete="current-password" revealable />
            <x-button type="submit" data-busy-label="Saving…">{{ $editing ? 'Save changes' : 'Create account' }}</x-button>
        </section>
    </form>

    @if($editing && ! $self)
        <section class="mt-8 max-w-2xl space-y-4 rounded-2xl border border-border bg-surface p-5 sm:p-6" aria-labelledby="reset-heading" x-data="{ suggested: '' }">
            <div>
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 id="reset-heading" class="text-base font-semibold">Forgot their password?</h2>
                    <button type="button" class="text-sm font-semibold underline underline-offset-4" @click="suggested = suggestPassword(); ['reset-password', 'reset-confirmation'].forEach(id => { const el = document.getElementById(id); el.value = suggested; el.type = 'text' })">Suggest one</button>
                </div>
                <p class="mt-1 text-sm text-text-secondary">Set a temporary password and give it to them privately. They are signed out everywhere and must choose a new one. This does not switch the account back on.</p>
            </div>
            <p x-show="suggested" x-cloak class="rounded-lg bg-selected px-3 py-2 text-sm">Give <strong class="font-mono tracking-wide" x-text="suggested"></strong> to {{ \Illuminate\Support\Str::of($user->name)->before(' ') }} privately.</p>
            <form method="POST" action="{{ route('users.password', $user) }}" class="space-y-4" data-busy>
                @csrf<input type="hidden" name="revision" value="{{ $user->revision }}">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-input name="password" id="reset-password" label="New temporary password" type="password" required minlength="10" maxlength="255" autocomplete="new-password" revealable />
                    <x-input name="password_confirmation" id="reset-confirmation" label="Type it again" type="password" required maxlength="255" autocomplete="new-password" revealable />
                </div>
                <x-input name="current_password" id="reset-administrator-password" label="Your password, to confirm" type="password" required maxlength="255" autocomplete="current-password" revealable />
                <x-button type="submit" variant="outline" data-busy-label="Setting…">Set temporary password</x-button>
            </form>
        </section>
    @endif
</x-layouts.app>
