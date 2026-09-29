@extends('layouts.guest')
@section('content')
<span class="section-kicker">LET'S GET GROWING</span><h1>Create your account</h1><p class="muted mb-8">Start a more connected chapter for your farm.</p>
<form method="POST" action="{{ route('register') }}" class="form-stack">@csrf
<x-input name="name" label="Full name" autocomplete="name" required />
<x-input name="email" label="Email address" type="email" autocomplete="email" required />
<x-input name="password" label="Password · at least 12 characters" type="password" autocomplete="new-password" minlength="12" required />
<x-input name="password_confirmation" label="Confirm password" type="password" autocomplete="new-password" required />
<button class="agri-btn agri-btn-primary w-full" type="submit">Create account <i data-lucide="arrow-right"></i></button>
</form><p class="mt-6 text-sm muted">Already growing with us? <a class="text-link" href="{{ route('login') }}">Sign in</a></p>
@endsection
