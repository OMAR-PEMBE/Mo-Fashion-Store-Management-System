<x-layouts.guest title="Sign in" intro="Use the email and password your administrator gave you.">
    @error('credentials')
        <div role="alert" class="mb-6 flex gap-3 rounded-lg border border-danger/30 bg-danger/5 p-4 text-sm leading-6 text-danger">
            <svg class="mt-0.5 size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7.5v5.5M12 16.5h.01"/></svg>
            <p>{{ $message }}</p>
        </div>
    @enderror
    <form method="POST" action="{{ route('login') }}" class="space-y-5" data-busy>
        @csrf
        {{-- After a failed attempt the email is kept, so the cursor goes straight to the password. --}}
        <x-input name="email" label="Email address" type="email" icon="mail" :value="old('email')" autocomplete="username" inputmode="email" autocapitalize="none" spellcheck="false" required maxlength="191" :autofocus="! old('email')" />
        <x-input name="password" label="Password" type="password" icon="lock" autocomplete="current-password" required maxlength="255" :autofocus="(bool) old('email')" revealable />
        <x-button type="submit" class="w-full" data-busy-label="Signing in…">Sign in</x-button>
    </form>
    <p class="mt-6 text-sm leading-6 text-text-secondary">Forgot your password or need an account? Ask your administrator.</p>
</x-layouts.guest>
