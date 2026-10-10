<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function showRegistrationForm(): View
    {
        return view('auth.register');
    }

    public function register(RegisterRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $user = DB::transaction(function () use ($data) {
            $user = User::create([...$data, 'password' => Hash::make($data['password'])]);
            $user->profile()->create(['full_name' => $data['name']]);

            return $user;
        });
        Auth::login($user);
        $request->session()->regenerate();
        try {
            event(new Registered($user));
        } catch (\RuntimeException) {
            return redirect()->route('verification.notice')->withErrors(['otp' => 'Your account was created, but email delivery is temporarily unavailable. Wait 60 seconds and request a new code.']);
        }

        return redirect()->route('verification.notice')->with('success', 'Your account was created. Check your inbox for your verification code.');
    }
}
