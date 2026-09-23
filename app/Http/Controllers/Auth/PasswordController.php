<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class PasswordController extends Controller
{
    public function email(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:191']]);
        Password::sendResetLink(['email' => Str::lower($data['email']), 'is_active' => true,
            fn ($query) => $query->whereHas('role')]);

        return back()->with('status', 'If an active account matches that email, a password reset link will be sent.');
    }

    public function reset(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:191'],
            'password' => ['required', 'confirmed', 'max:255', PasswordRule::defaults()],
        ]);
        $data['email'] = Str::lower($data['email']);
        $status = Password::reset($data + ['is_active' => true, fn ($query) => $query->whereHas('role')], function (User $user, string $password) {
            DB::transaction(function () use ($user, $password) {
                $fresh = User::lockForUpdate()->findOrFail($user->id);
                if (! $fresh->canAccessWorkspace() || $fresh->security_version !== $user->security_version || $fresh->email !== $user->email) {
                    throw ValidationException::withMessages(['email' => 'This account changed. Request a new password reset link.']);
                }
                $fresh->forceFill(['password' => $password, 'remember_token' => Str::random(60), 'must_change_password' => false, 'revision' => $fresh->revision + 1])->save();
            });
            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors(['email' => 'This reset link is invalid or has expired. Request a new link.'])->withInput($request->only('email'));
        }

        return redirect()->route('login')->with('status', 'Your password has been reset. Please sign in.');
    }

    public function update(Request $request): RedirectResponse
    {
        Gate::authorize('changePassword', $request->user());
        $data = $request->validate([
            'current_password' => ['required', 'current_password:web'],
            'password' => ['required', 'confirmed', 'different:current_password', 'max:255', PasswordRule::defaults()],
        ]);
        $user = DB::transaction(function () use ($request, $data) {
            $user = User::lockForUpdate()->findOrFail($request->user()->id);
            abort_unless($user->canAccessWorkspace() && $user->security_version === $request->user()->security_version, 403);
            if (! Hash::check($data['current_password'], $user->password)) {
                throw ValidationException::withMessages(['current_password' => 'Your password changed. Sign in again.']);
            }
            $user->forceFill(['password' => $data['password'], 'remember_token' => Str::random(60), 'must_change_password' => false, 'revision' => $user->revision + 1])->save();

            return $user;
        });
        // Keep this session; auth.session rejects old password hashes on other devices.
        Auth::setUser($user);
        $request->session()->put('password_hash_web', $user->getAuthPassword());
        $request->session()->regenerate();

        return back()->with('status', 'Password updated. Other sessions will require a new sign-in.');
    }
}
