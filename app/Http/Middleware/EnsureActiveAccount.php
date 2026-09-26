<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $fresh = $request->user()?->fresh();
        if ($fresh) {
            Auth::setUser($fresh);
        }
        $version = $request->session()->get('security_version', 1);
        if (! $fresh?->canAccessWorkspace() || (int) $version !== $fresh->security_version) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect()->route('login')->withErrors(['email' => 'Please sign in with an active staff account.']);
        }

        if ($fresh->must_change_password && ! $request->routeIs('profile', 'password.update')) {
            // The profile page explains why in its own "Next step" panel.
            return redirect()->route('profile');
        }

        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
