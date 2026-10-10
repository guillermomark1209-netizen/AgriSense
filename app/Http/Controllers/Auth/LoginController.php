<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AdminAuditService;
use App\Services\UserSessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function showLoginForm(): View
    {
        return view('auth.login');
    }

    public function login(Request $request, AdminAuditService $audit, UserSessionService $userSessions): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            $userSessions->recordLogin($request->user(), $request->session()->getId(), $request->ip());
            $request->session()->put('user_session_last_activity', now()->getTimestamp());
            $audit->record('LOGIN_SUCCEEDED', 'users', $request->user()->id, 'User signed in.');

            if (! $request->user()->hasVerifiedEmail()) {
                return redirect()->route('verification.notice');
            }

            if ($request->user()->isAdmin()) {
                $request->session()->forget('url.intended');

                return redirect()->route('admin.dashboard');
            }

            return redirect()->intended(route('dashboard'));
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    public function logout(Request $request, AdminAuditService $audit, UserSessionService $userSessions): RedirectResponse
    {
        $audit->record('LOGOUT_SUCCEEDED', 'users', $request->user()->id, 'User signed out.');
        $userSessions->recordLogout($request->user());
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
