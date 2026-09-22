<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

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
            $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
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
        $request->user()->forceFill(['password' => $data['password'], 'remember_token' => Str::random(60)])->save();
        // Keep this session; auth.session rejects old password hashes on other devices.
        $request->session()->put('password_hash_web', $request->user()->getAuthPassword());
        $request->session()->regenerate();

        return back()->with('status', 'Password updated. Other sessions will require a new sign-in.');
    }
}
