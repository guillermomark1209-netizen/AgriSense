@extends('layouts.guest')
@section('content')
<span class="section-kicker">SECURE YOUR ACCOUNT</span>
<h1>Check your inbox</h1>
<p class="muted mb-8">Enter the six-digit code sent to <strong>{{ auth()->user()->email }}</strong>. It expires after five minutes.</p>
<form method="POST" action="{{ route('verification.verify') }}" class="form-stack" data-otp-form>
@csrf
<div class="field">
<label for="otp">Verification code</label>
<input id="otp" name="otp" type="text" class="form-input" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" required aria-describedby="otp-help @error('otp') otp-error @enderror" @error('otp') aria-invalid="true" @enderror>
<div class="otp-digits" data-otp-digits hidden aria-label="Six-digit verification code">
@for($index = 0; $index < 6; $index++)
<input type="text" class="form-input" inputmode="numeric" maxlength="1" aria-label="Digit {{ $index + 1 }}" autocomplete="{{ $index === 0 ? 'one-time-code' : 'off' }}">
@endfor
</div>
<p id="otp-help" class="muted text-sm">Paste your code or enter each digit.</p>
@error('otp')<p id="otp-error" class="field-error" role="alert">{{ $message }}</p>@enderror
</div>
<button type="submit" class="agri-btn agri-btn-primary w-full">Verify email</button>
</form>
<form method="POST" action="{{ route('verification.send') }}" class="mt-6" data-resend-form data-cooldown="{{ $cooldown }}">
@csrf
<button type="submit" class="agri-btn agri-btn-secondary w-full" data-resend-button>Send a new code</button>
<p class="muted text-sm mt-3" data-resend-status role="status"></p>
</form>
<form method="POST" action="{{ route('logout') }}" class="mt-6">@csrf<button type="submit" class="text-link">Sign out</button></form>
@endsection
