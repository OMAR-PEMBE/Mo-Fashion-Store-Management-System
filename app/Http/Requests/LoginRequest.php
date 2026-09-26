<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->email)) {
            $this->merge(['email' => Str::lower(trim($this->email))]);
        }
    }

    public function rules(): array
    {
        return ['email' => ['required', 'string', 'email', 'max:191'], 'password' => ['required', 'string', 'max:255']];
    }

    public function authenticate(): void
    {
        // Sign-in failures use their own key so the page shows one banner instead of blaming a field.
        $key = 'login:'.hash('sha256', $this->email.'|'.$this->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'credentials' => 'Too many tries. Please wait about a minute, then try again.',
            ])->status(429);
        }

        if (! Auth::attemptWhen(
            $this->only('email', 'password') + ['is_active' => true],
            fn ($user) => $user->canAccessWorkspace(),
        )) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['credentials' => 'The email or password is incorrect. Check both and try again.']);
        }

        RateLimiter::clear($key);
        $this->session()->regenerate();
        $this->session()->put('password_hash_web', Auth::user()->getAuthPassword());
        $this->session()->put('security_version', Auth::user()->security_version);
        Auth::user()->forceFill(['last_login_at' => now()])->save();
    }
}
