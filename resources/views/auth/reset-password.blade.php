<x-layouts.guest title="Choose a new password">
    <form method="POST" action="{{ route('password.store') }}" class="space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-input name="email" label="Email address" type="email" :value="old('email', $email)" autocomplete="username" required maxlength="191" />
        <x-input name="password" label="New password" type="password" autocomplete="new-password" help="At least 10 characters, including letters and numbers." required minlength="10" maxlength="255" />
        <x-input name="password_confirmation" label="Confirm new password" type="password" autocomplete="new-password" required maxlength="255" />
        <x-button type="submit" class="w-full">Reset password</x-button>
    </form>
    <a href="{{ route('password.request') }}" class="mt-6 inline-block text-sm underline underline-offset-4">Request a new link</a>
</x-layouts.guest>
