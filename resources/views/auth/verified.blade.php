@extends('layouts.guest')
@section('content')
<span class="section-kicker">EMAIL VERIFIED</span><h1>You're all set!</h1>
<p class="muted mb-8" role="status">Your email has been verified. Your AgriSense dashboard is ready.</p>
<a href="{{ route(auth()->user()->isAdmin() ? 'admin.dashboard' : 'dashboard') }}" class="agri-btn agri-btn-primary w-full">Go to dashboard <i data-lucide="arrow-right"></i></a>
@endsection
