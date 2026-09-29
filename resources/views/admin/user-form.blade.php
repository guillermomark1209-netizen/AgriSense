@extends('layouts.app')
@section('title', $user->exists ? 'Edit user' : 'Create user')
@section('subtitle', 'Administration · Account management')
@section('content')
<form class="agri-card p-6 form-stack" method="POST" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}">
    @csrf
    @if($user->exists) @method('PUT') @endif
    <x-input name="name" label="Name" :value="$user->name" required />
    <x-input name="email" label="Email" type="email" :value="$user->email" required />
    @unless($user->exists)
        <x-input name="password" label="Password · at least 12 characters" type="password" autocomplete="new-password" required />
        <x-input name="password_confirmation" label="Confirm password" type="password" autocomplete="new-password" required />
        <div class="field"><label for="role">Role</label><select id="role" name="role"><option value="user">User</option><option value="admin" @selected(old('role') === 'admin')>Admin</option></select></div>
    @endunless
    <div class="form-actions"><a class="agri-btn agri-btn-secondary" href="{{ route('admin.manage', 'users') }}">Cancel</a><button class="agri-btn agri-btn-primary">Save user</button></div>
</form>
@endsection
