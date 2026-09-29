@extends('layouts.app')
@section('title','Monitoring devices')
@section('subtitle','The connection between your fields and your screen.')
@section('content')
<div class="section-heading"><p class="muted">{{ $devices->total() }} registered devices</p>@can('manage-system')<a class="agri-btn agri-btn-primary" href="{{ route('devices.create') }}"><i data-lucide="plus"></i>Register device</a>@endcan</div>
<div class="grid gap-5 md:grid-cols-2">@forelse($devices as $device)<article class="agri-card p-6"><div class="flex justify-between"><span class="assistant-symbol"><i data-lucide="radio"></i></span><x-status-badge :status="$device->online ? 'online' : 'offline'" /></div><h2 class="mt-4">{{ $device->name }}</h2><p class="muted text-sm">{{ $device->device_id }}</p><p class="mt-4">{{ $device->crop?->name ?? 'Unassigned' }}</p><p class="muted text-xs mt-1">Last contact: {{ $device->last_seen_at?->diffForHumans() ?? 'Never' }}</p><a class="text-link mt-5" href="{{ route('devices.show',$device) }}">View device <i data-lucide="arrow-up-right"></i></a></article>@empty<div class="agri-card col-span-full"><x-empty-state title="Bring your field online" description="Register an ESP32 and assign a crop to receive sensor readings." icon="radio" /></div>@endforelse</div><div class="mt-5">{{ $devices->links() }}</div>
@endsection
