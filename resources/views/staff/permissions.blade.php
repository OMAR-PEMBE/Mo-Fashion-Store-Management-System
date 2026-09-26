@use('App\Support\PermissionCatalog')
@php
    $bySlug = $permissions->keyBy('slug');
    $checked = collect(old('permissions', $role->permissions->modelKeys()))->map(fn ($id) => (int) $id)->all();
    $groups = PermissionCatalog::groups();
    $known = collect($groups)->flatMap(fn ($items) => array_keys($items))->all();
    $others = $permissions->reject(fn ($permission) => in_array($permission->slug, $known, true));
    if ($others->isNotEmpty()) {
        $groups['Other'] = $others->mapWithKeys(fn ($permission) => [$permission->slug => [$permission->name, '']])->all();
    }
    $isAdmin = $role->slug === 'administrator';
@endphp
<x-layouts.app title="Edit role permissions">
    <x-page-header :title="'What '.(preg_match('/^[AEIOU]/i', $role->name) ? 'an ' : 'a ').$role->name.' can do'" :back="route('roles.index')" back-label="Roles"
        :description="'Changes apply to all '.$role->users_count.' '.\Illuminate\Support\Str::plural('account', $role->users_count).' with this role.'" />

    <form method="POST" action="{{ route('roles.update', $role) }}" class="space-y-6" data-busy>
        @csrf @method('PUT')<input type="hidden" name="revision" value="{{ old('revision', $role->revision) }}">
        @if($errors->any())<div role="alert" class="rounded-xl border border-danger/30 bg-danger/5 p-4 text-sm text-danger">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
        @if($isAdmin)<p class="rounded-xl border border-warning/40 bg-warning/10 p-4 text-sm">Keep <strong>Manage staff and access</strong> switched on here, or nobody will be able to manage accounts.</p>@endif

        <div class="grid gap-6 lg:grid-cols-2">
            @foreach($groups as $group => $items)
                @php $present = collect($items)->filter(fn ($meta, $slug) => $bySlug->has($slug)); @endphp
                @if($present->isNotEmpty())
                    <fieldset class="rounded-2xl border border-border bg-surface">
                        <legend class="sr-only">{{ $group }}</legend>
                        <p class="border-b border-border px-5 py-3 font-semibold" aria-hidden="true">{{ $group }}</p>
                        <div class="divide-y divide-border">
                            @foreach($present as $slug => [$label, $description])
                                @php
                                    $permission = $bySlug[$slug];
                                    $locked = ! $isAdmin && in_array($slug, PermissionCatalog::ADMIN_ONLY, true);
                                @endphp
                                <label @class(['flex items-start gap-3 px-5 py-3 text-sm', 'cursor-pointer hover:bg-selected/40' => ! $locked, 'cursor-not-allowed opacity-60' => $locked])>
                                    <input type="checkbox" name="permissions[]" value="{{ $permission->id }}" class="mt-0.5 size-4 shrink-0" @checked(in_array($permission->id, $checked, true)) @disabled($locked)>
                                    <span class="min-w-0">
                                        <span class="block font-semibold">{{ $label }}@if($locked)<span class="ml-2 text-xs font-normal text-text-secondary">Administrators only</span>@endif</span>
                                        @if($description)<span class="block text-text-secondary">{{ $description }}</span>@endif
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endif
            @endforeach
        </div>

        <section class="max-w-xl space-y-4 rounded-2xl border border-border bg-surface p-5 sm:p-6">
            <x-input name="current_password" label="Your password, to confirm" type="password" required autocomplete="current-password" maxlength="255" revealable />
            <x-button type="submit" data-busy-label="Saving…">Save for every {{ $role->name }}</x-button>
        </section>
    </form>
</x-layouts.app>
