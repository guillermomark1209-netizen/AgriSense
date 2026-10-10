<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\VerifyEmailOtpRequest;
use App\Services\EmailVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VerificationController extends Controller
{
    public function notice(Request $request, EmailVerificationService $verification): View|RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('verification.success');
        }

        return view('auth.verify-email', ['cooldown' => $verification->cooldown($request->user())]);
    }

    public function verify(VerifyEmailOtpRequest $request, EmailVerificationService $verification): RedirectResponse
    {
        if (! $verification->verify($request->user(), $request->validated('otp'))) {
            return back()->withErrors(['otp' => 'This code is invalid, expired, or has reached its attempt limit. Request a new code.']);
        }
        $request->session()->regenerate();

        return redirect()->route('verification.success');
    }

    public function resend(Request $request, EmailVerificationService $verification): RedirectResponse
    {
        try {
            $verification->send($request->user());
        } catch (\RuntimeException) {
            return back()->withErrors(['otp' => 'Email delivery is temporarily unavailable. Wait 60 seconds and try again.']);
        }

        return back()->with('success', 'A new verification code has been requested. Check your inbox.');
    }
}
