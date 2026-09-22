<x-layouts.guest title="Reset your password">
    <p class="mb-5 text-sm text-text-secondary">Enter your staff email address to request a reset link.</p>
    <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
        @csrf
        <x-input name="email" label="Email address" type="email" :value="old('email')" autocomplete="email" required autofocus maxlength="191" />
        <x-button type="submit" class="w-full">Send reset link</x-button>
    </form>
    <a href="{{ route('login') }}" class="mt-6 inline-block text-sm underline underline-offset-4">Back to sign in</a>
</x-layouts.guest>
