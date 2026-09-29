@extends('layouts.guest')
@section('content')
<h1>Choose a new password</h1><form method="POST" action="{{ route('password.update') }}" class="form-stack mt-6">@csrf<input name="token" type="hidden" value="{{ $token }}"><x-input name="email" label="Email address" type="email" :value="$email" required /><x-input name="password" label="New password · at least 12 characters" type="password" minlength="12" required /><x-input name="password_confirmation" label="Confirm password" type="password" required /><button class="agri-btn agri-btn-primary">Reset password</button></form>
@endsection
