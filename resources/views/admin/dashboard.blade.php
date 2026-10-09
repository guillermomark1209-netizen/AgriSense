@extends('layouts.app')

@section('title', 'Admin Dashboard')
@section('subtitle', 'Administrative overview for users, devices, sources, and AI activity.')

@section('content')
<div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
    <div class="agri-card p-5">
        <p class="text-sm text-[color:var(--agri-secondary-text)]">Total Users</p>
        <p class="mt-2 text-3xl font-bold text-[color:var(--agri-primary-text)]">{{ $totalUsers }}</p>
    </div>
    <div class="agri-card p-5">
        <p class="text-sm text-[color:var(--agri-secondary-text)]">Registered Crops</p>
        <p class="mt-2 text-3xl font-bold text-[color:var(--agri-primary-text)]">{{ $registeredCrops }}</p>
    </div>
    <div class="agri-card p-5">
        <p class="text-sm text-[color:var(--agri-secondary-text)]">Online Devices</p>
        <p class="mt-2 text-3xl font-bold text-[color:var(--agri-primary-text)]">{{ $onlineDevices }}</p>
    </div>
    <div class="agri-card p-5">
        <p class="text-sm text-[color:var(--agri-secondary-text)]">Alerts Today</p>
        <p class="mt-2 text-3xl font-bold text-[color:var(--agri-primary-text)]">{{ $alertsToday }}</p>
    </div>
    <div class="agri-card p-5">
        <p class="text-sm text-[color:var(--agri-secondary-text)]">Verified Sources</p>
        <p class="mt-2 text-3xl font-bold text-[color:var(--agri-primary-text)]">{{ $verifiedSources }}</p>
    </div>
    <div class="agri-card p-5">
        <p class="text-sm text-[color:var(--agri-secondary-text)]">AI Questions Today</p>
        <p class="mt-2 text-3xl font-bold text-[color:var(--agri-primary-text)]">{{ $aiQuestionsToday }}</p>
    </div>
</div>
<div class="grid gap-5 mt-6 xl:grid-cols-2">
    <section class="agri-card flex flex-col p-5" x-data="{ page: 1, pageSize: 5, recordCount: {{ $activeSessions->count() }}, get pageCount() { return Math.max(1, Math.ceil(this.recordCount / this.pageSize)); } }" x-effect="if (page > pageCount) page = pageCount">
        <div class="section-heading"><div><h2>Active Sessions</h2><p class="muted text-sm">Authenticated users active within the last 5 minutes.</p></div><span class="status-badge">{{ $activeSessions->count() }}</span></div>
        <div class="table-wrap mt-4"><table><thead><tr><th>User</th><th>Role</th><th>Last activity</th></tr></thead><tbody>@forelse($activeSessions as $session)<tr x-cloak x-show="page === Math.ceil({{ $loop->iteration }} / pageSize)"><td><strong>{{ $session->name }}</strong><br><span class="muted text-xs">{{ $session->email }}</span></td><td>{{ ucfirst($session->role) }}</td><td>{{ \Carbon\Carbon::parse($session->last_activity)->diffForHumans() }}</td></tr>@empty<tr><td colspan="3">No active users.</td></tr>@endforelse</tbody></table></div>
        <nav class="mt-auto flex items-center justify-between gap-3 pt-4" aria-label="Active sessions pages">
            <button type="button" class="agri-btn agri-btn-secondary" @click="page = Math.max(1, page - 1)" :disabled="page <= 1" aria-label="Previous page">← Previous</button>
            <span class="muted text-sm" aria-live="polite">Page <span x-text="page"></span> of <span x-text="pageCount"></span></span>
            <button type="button" class="agri-btn agri-btn-secondary" @click="page = Math.min(pageCount, page + 1)" :disabled="page >= pageCount" aria-label="Next page">Next →</button>
        </nav>
    </section>
    <section class="agri-card flex flex-col p-5" x-data="{ page: 1, pageSize: 5, recordCount: {{ $loginActivity->count() }}, get pageCount() { return Math.max(1, Math.ceil(this.recordCount / this.pageSize)); } }" x-effect="if (page > pageCount) page = pageCount">
        <div class="section-heading"><div><h2>Login activity</h2><p class="muted text-sm">Most recent successful sign-ins and sign-outs.</p></div></div>
        <div class="table-wrap mt-4"><table><thead><tr><th>Event</th><th>User</th><th>IP address</th><th>When</th></tr></thead><tbody>@forelse($loginActivity as $activity)<tr x-cloak x-show="page === Math.ceil({{ $loop->iteration }} / pageSize)"><td>{{ $activity->action === 'LOGIN_SUCCEEDED' ? 'Signed in' : 'Signed out' }}</td><td>{{ $activity->name ?? 'Deleted user' }}<br><span class="muted text-xs">{{ $activity->email }}</span></td><td>{{ $activity->ip_address ?? 'Unavailable' }}</td><td>{{ \Carbon\Carbon::parse($activity->created_at)->diffForHumans() }}</td></tr>@empty<tr><td colspan="4">No login activity has been recorded yet.</td></tr>@endforelse</tbody></table></div>
        <nav class="mt-auto flex items-center justify-between gap-3 pt-4" aria-label="Login activity pages">
            <button type="button" class="agri-btn agri-btn-secondary" @click="page = Math.max(1, page - 1)" :disabled="page <= 1" aria-label="Previous page">← Previous</button>
            <span class="muted text-sm" aria-live="polite">Page <span x-text="page"></span> of <span x-text="pageCount"></span></span>
            <button type="button" class="agri-btn agri-btn-secondary" @click="page = Math.min(pageCount, page + 1)" :disabled="page >= pageCount" aria-label="Next page">Next →</button>
        </nav>
    </section>
</div>
@endsection
