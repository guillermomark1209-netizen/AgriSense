<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PasswordController extends Controller
{
    public function forgot(): View
    {
        return view('auth.forgot-password');
    }

    public function email(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'string', 'email', 'max:255']]);
        try {
            Password::sendResetLink($request->only('email'));
        } catch (\RuntimeException) {
            return back()->with('success', 'If an account exists for that email, a password reset link has been sent.');
        }

        return back()->with('success', 'If an account exists for that email, a password reset link has been sent.');
    }

    public function reset(Request $request, string $token): View
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate(['token' => ['required', 'string'], 'email' => ['required', 'string', 'email', 'max:255'], 'password' => ['required', 'string', 'min:12', 'max:255', 'confirmed']]);
        $status = Password::reset($request->only('email', 'password', 'password_confirmation', 'token'), function (User $user, string $password): void {
            $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
            event(new PasswordReset($user));
        });

        return $status === Password::PasswordReset ? redirect()->route('login')->with('success', __($status)) : back()->withErrors(['email' => __($status)]);
    }
}
