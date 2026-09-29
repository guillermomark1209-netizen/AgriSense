@extends('layouts.guest')
@section('content')
<span class="section-kicker">ACCOUNT RECOVERY</span><h1>Forgot your password?</h1><p class="muted mb-8">Enter your account email to request a reset link.</p><form method="POST" action="{{ route('password.email') }}" class="form-stack">@csrf<x-input name="email" label="Email address" type="email" required /><button class="agri-btn agri-btn-primary">Send reset link</button></form><a href="{{ route('login') }}" class="text-link mt-6 inline-block">Back to sign in</a>
@endsection
