<x-layouts.guest title="Welcome back">
    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf
        <x-input name="email" label="Email address" type="email" :value="old('email')" autocomplete="username" required autofocus maxlength="191" />
        <x-input name="password" label="Password" type="password" autocomplete="current-password" required maxlength="255" />
        <div class="text-right"><a href="{{ route('password.request') }}" class="text-sm underline underline-offset-4">Forgot password?</a></div>
        <x-button type="submit" class="w-full">Sign in</x-button>
    </form>
    <p class="mt-6 text-sm text-text-secondary">Need an account? Contact your administrator.</p>
</x-layouts.guest>
