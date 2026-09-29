@extends('layouts.guest')
@section('auth-layout-class', 'auth-layout-centered')
@section('content')
<span class="section-kicker">WELCOME BACK</span><h1>Sign in to your farm</h1><p class="muted mb-8">Your crops. Your conditions. All in one place.</p>
<form method="POST" action="{{ route('login') }}" class="form-stack">@csrf
<x-input name="email" label="Email address" type="email" autocomplete="email" required />
<x-input name="password" label="Password" type="password" autocomplete="current-password" required />
<div class="flex justify-between gap-3 text-sm"><label class="check-label"><input type="checkbox" name="remember" value="1">Remember me</label><a class="text-link" href="{{ route('password.request') }}">Forgot password?</a></div>
<button class="agri-btn agri-btn-primary w-full" type="submit">Sign in <i data-lucide="arrow-right"></i></button></form>
<div class="auth-divider">New to {{ config('app.name') }}?</div><a href="{{ route('register') }}" class="agri-btn agri-btn-secondary w-full">Create an account</a>
@endsection
