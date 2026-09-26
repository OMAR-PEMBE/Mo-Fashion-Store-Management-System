<x-layouts.app title="Your profile">
    <h1 class="mb-6 text-3xl font-bold">Your profile</h1>
    @if(auth()->user()->must_change_password)<p role="alert" class="mb-5 rounded-lg border border-warning bg-surface p-4 text-sm">Change your temporary password before using the workspace.</p>@endif
    <div class="max-w-xl space-y-6">
        <x-card title="Staff account">
            <dl class="space-y-3 text-sm">
                <div><dt class="text-text-secondary">Name</dt><dd>{{ auth()->user()->name }}</dd></div>
                <div><dt class="text-text-secondary">Email</dt><dd class="break-all">{{ auth()->user()->email }}</dd></div>
                <div><dt class="text-text-secondary">Role</dt><dd>{{ auth()->user()->role->name }}</dd></div>
            </dl>
        </x-card>
        <x-card title="Change password">
            
            <form method="POST" action="{{ route('password.update') }}" class="space-y-5">
                @csrf
                @method('PUT')
                <x-input name="current_password" label="Current password" type="password" autocomplete="current-password" required />
                <x-input name="password" label="New password" type="password" autocomplete="new-password" help="At least 10 characters, including letters and numbers." required minlength="10" maxlength="255" />
                <x-input name="password_confirmation" label="Confirm new password" type="password" autocomplete="new-password" required maxlength="255" />
                <x-button type="submit">Update password</x-button>
            </form>
        </x-card>
    </div>
</x-layouts.app>
