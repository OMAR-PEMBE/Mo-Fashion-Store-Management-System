<x-layouts.app title="Edit role permissions">
    <a href="{{ route('roles.index') }}" class="text-sm underline">Role permissions</a><h1 class="mb-5 mt-2 text-3xl font-bold">{{ $role->name }} permissions</h1><p class="mb-5 text-sm">Changes apply to all {{ $role->users_count }} staff accounts in this role. Staff administration, audit logs, settings and refund approval/completion remain administrator-only.</p>
    
    @if($errors->any())<div role="alert" class="mb-5 text-sm text-danger">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <x-card><form method="POST" action="{{ route('roles.update', $role) }}" class="space-y-6">@csrf @method('PUT')<input type="hidden" name="revision" value="{{ old('revision', $role->revision) }}">
        <div class="grid gap-4 sm:grid-cols-2">@foreach($permissions as $permission)@php($restricted = $role->slug !== 'administrator' && in_array($permission->slug, ['users.manage', 'refunds.approve', 'refunds.complete', 'audit.view', 'settings.manage']))<label class="flex min-w-0 gap-3 rounded-lg border border-border p-4 text-sm"><input type="checkbox" name="permissions[]" value="{{ $permission->id }}" class="mt-1" @checked(in_array($permission->id, old('permissions', $role->permissions->modelKeys()))) @disabled($restricted)><span class="break-words">{{ str($permission->name)->replace('.', ' ')->headline() }}@if($restricted)<span class="mt-1 block text-xs text-text-secondary">Administrator only</span>@endif</span></label>@endforeach</div>
        <p class="text-sm text-text-secondary">Keep staff-management permission on the Administrator role so the store retains administrative access.</p>
        <x-input name="current_password" label="Your administrator password" type="password" required autocomplete="current-password" maxlength="255" /><x-button type="submit">Save role permissions</x-button>
    </form></x-card>
</x-layouts.app>
