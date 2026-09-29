@extends('layouts.app')
@section('title','Your profile')
@section('subtitle','The person behind the growing spaces.')
@section('content')
<div class="agri-card p-5 mb-5 flex items-center gap-3"><span class="muted">Account role</span><x-status-badge :status="$user->role" /></div>
<form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="agri-card p-6 form-stack">@csrf
@if($user->profile?->avatar_url)<img src="{{ route('profile.image') }}" alt="Your profile photo" class="upload-preview">@endif
<div class="grid gap-5 md:grid-cols-2"><x-input name="name" label="Full name" :value="$user->name" required /><x-input name="email" label="Email address" type="email" :value="$user->email" required /><x-input name="phone" label="Phone (optional)" :value="$user->profile?->phone" /><x-input name="address" label="Farm address (optional)" :value="$user->profile?->address" /></div><div class="field"><label for="bio">About your farm</label><textarea name="bio" id="bio" rows="3" maxlength="1000">{{ old('bio',$user->profile?->bio) }}</textarea></div><x-input type="file" name="avatar" label="Profile photo · up to 5 MB" accept="image/jpeg,image/png,image/webp" data-image-input /><img data-image-preview hidden class="upload-preview" alt="Selected profile photo preview"><div class="form-actions"><button class="agri-btn agri-btn-primary">Save profile</button></div></form>
@endsection
