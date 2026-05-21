<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    private const MAX_LOGIN_ATTEMPTS = 5;

    public function showLogin(): View|RedirectResponse
    {
        if (Auth::guard('admin')->check()) {
            return redirect()->route($this->homeRouteFor(Auth::guard('admin')->user()));
        }

        return view('admin.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $this->ensureIsNotRateLimited($request);

        if (Auth::guard('admin')->attempt([...$credentials, 'is_active' => true])) {
            RateLimiter::clear($this->throttleKey($request));

            $request->session()->regenerate();

            Auth::guard('admin')->user()->forceFill([
                'last_login_at' => now(),
            ])->save();

            return redirect()->intended(route($this->homeRouteFor(Auth::guard('admin')->user())));
        }

        RateLimiter::hit($this->throttleKey($request), 300);

        throw ValidationException::withMessages([
            'email' => 'Credenziali non valide o account disattivato.',
        ]);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    private function ensureIsNotRateLimited(Request $request): void
    {
        $key = $this->throttleKey($request);

        if (! RateLimiter::tooManyAttempts($key, self::MAX_LOGIN_ATTEMPTS)) {
            return;
        }

        $seconds = RateLimiter::availableIn($key);

        throw ValidationException::withMessages([
            'email' => "Troppi tentativi di accesso. Riprova tra {$seconds} secondi.",
        ]);
    }

    private function throttleKey(Request $request): string
    {
        return Str::lower((string) $request->input('email')) . '|' . $request->ip();
    }

    private function homeRouteFor($user): string
    {
        if ($user?->hasAdminPermission('whatsapp.view')) {
            return 'admin.dashboard';
        }

        if ($user?->hasAdminPermission('leads.view')) {
            return 'admin.leads.index';
        }

        if ($user?->canManageAdminUsers()) {
            return 'admin.users.index';
        }

        return 'admin.dashboard';
    }
}
