<x-layouts.guest title="Reset your password" intro="Enter your staff email address to request a reset link.">
    <form method="POST" action="{{ route('password.email') }}" class="space-y-5" data-busy>
        @csrf
        <x-input name="email" label="Email address" type="email" icon="mail" :value="old('email')" autocomplete="email" inputmode="email" autocapitalize="none" spellcheck="false" required autofocus maxlength="191" />
        <x-button type="submit" class="w-full" data-busy-label="Sending…">Send reset link</x-button>
    </form>
    <a href="{{ route('login') }}" class="mt-6 inline-block text-sm underline underline-offset-4">Back to sign in</a>
</x-layouts.guest>
