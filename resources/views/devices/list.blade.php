@if($deviceLoadError)
<div class="agri-card p-6" role="alert"><h2>Devices could not be loaded</h2><p class="field-error mt-3">{{ $deviceLoadError }}</p><p class="muted mt-3">Automatic loading will retry. Reload the page to try again now.</p></div>
@else
<div class="grid gap-5 md:grid-cols-2">
@forelse($devices as $device)
<article class="agri-card p-6"><div class="flex justify-between"><span class="assistant-symbol"><i data-lucide="radio"></i></span><x-status-badge :status="$device->online ? 'online' : 'offline'" /></div><h2 class="mt-4">{{ $device->name }}</h2><p class="muted text-sm">{{ $device->device_id }}</p><p class="mt-4">{{ $device->crop?->name ?? 'Unassigned' }}</p><p class="muted text-xs mt-1">Last seen: @if($device->last_seen_at)<time datetime="{{ $device->last_seen_at->toIso8601String() }}" title="{{ $device->last_seen_at->toIso8601String() }}">{{ $device->last_seen_at->diffForHumans() }}</time>@else No device contact recorded @endif</p>
@unless($device->is_active)<span class="status-badge mt-3 inline-flex">Disabled</span>@endunless
<p class="muted text-sm mt-3">Type: {{ $device->device_type ?? 'Not specified' }}</p><p class="muted text-sm">Location: {{ $device->location ?? 'Not specified' }}</p>
@if($device->is_selected)<span class="status-badge status-good mt-3 inline-flex">Selected</span>@elseif($device->is_active)<form method="POST" action="{{ route('devices.select',$device) }}" class="mt-4">@csrf<button class="agri-btn agri-btn-secondary" type="submit">Switch to this device</button></form>@endif
@can('manage-system')@if($device->is_active)<details class="mt-4"><summary class="text-link">Grant access to a user</summary><form method="POST" action="{{ route('devices.access',$device) }}" class="form-stack mt-3">@csrf<label class="sr-only" for="access-user-{{ $device->id }}">Choose a user</label><select id="access-user-{{ $device->id }}" name="user_id" required><option value="">Choose a user</option>@foreach($owners as $owner)@if($owner->id !== $device->user_id)<option value="{{ $owner->id }}">{{ $owner->name }} · {{ $owner->email }}</option>@endif @endforeach</select><button class="agri-btn agri-btn-secondary" type="submit">Grant access</button></form></details>@endif @endcan
<a class="text-link mt-5" href="{{ route('devices.show',$device) }}">View device <i data-lucide="arrow-up-right"></i></a></article>
@empty
<div class="agri-card col-span-full"><x-empty-state title="No devices added yet." description="Click Add device to register your first monitoring device." icon="radio" /></div>
@endforelse
</div><div class="mt-5">{{ $devices->links() }}</div>
@endif
