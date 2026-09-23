<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\StaffService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StaffController extends Controller
{
    public function index(Request $request, StaffService $service)
    {
        $service->authorize($request->user());
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:150'], 'status' => ['nullable', Rule::in(['active', 'inactive'])], 'role_id' => ['nullable', 'integer']]);
        $users = User::with('role')->when($filters['q'] ?? null, fn ($q, $value) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$value.'%')->orWhere('email', 'like', '%'.$value.'%')))
            ->when($filters['status'] ?? null, fn ($q, $value) => $q->where('is_active', $value === 'active'))
            ->when($filters['role_id'] ?? null, fn ($q, $value) => $q->where('role_id', $value))->orderBy('name')->orderBy('id')->paginate(20)->withQueryString();

        return view('staff.index', ['users' => $users, 'filters' => $filters, 'roles' => Role::orderBy('name')->get()]);
    }

    public function create(Request $request, StaffService $service)
    {
        $service->authorize($request->user());

        return $this->form(new User);
    }

    public function edit(Request $request, User $user, StaffService $service)
    {
        $service->authorize($request->user());

        return $this->form($user);
    }

    private function form(User $user)
    {
        return view('staff.form', ['user' => $user, 'roles' => Role::whereIn('slug', ['administrator', 'salesperson'])->orderBy('name')->get()]);
    }

    public function store(Request $request, StaffService $service)
    {
        $user = $service->save($request->all(), $request->user());

        return redirect()->route('users.edit', $user)->with('status', 'Staff account created. Share the temporary password privately; it must be changed after sign-in.');
    }

    public function update(Request $request, User $user, StaffService $service)
    {
        $service->save($request->all(), $request->user(), $user);

        return redirect()->route('users.edit', $user)->with('status', 'Staff account updated. Security changes require a new sign-in.');
    }

    public function password(Request $request, User $user, StaffService $service)
    {
        $service->resetPassword($user, $request->all(), $request->user());

        return redirect()->route('users.edit', $user)->with('status', 'Temporary password set. Existing sessions were revoked. Share it privately with this staff member.');
    }

    public function roles(Request $request, StaffService $service)
    {
        $service->authorize($request->user());

        return view('staff.roles', ['roles' => Role::withCount('users')->orderBy('name')->get()]);
    }

    public function editRole(Request $request, Role $role, StaffService $service)
    {
        $service->authorize($request->user());

        return view('staff.permissions', ['role' => $role->load('permissions')->loadCount('users'), 'permissions' => Permission::orderBy('slug')->get()]);
    }

    public function updateRole(Request $request, Role $role, StaffService $service)
    {
        // An unchecked checkbox group represents an intentionally empty selection.
        $service->permissions($role, $request->all() + ['permissions' => []], $request->user());

        return redirect()->route('roles.edit', $role)->with('status', 'Role permissions updated for all members.');
    }
}
