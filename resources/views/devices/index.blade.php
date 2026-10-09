@extends('layouts.app')
@section('title','Monitoring devices')
@section('subtitle','The connection between your fields and your screen.')
@section('content')
<section data-device-page data-endpoint="{{ route('devices.index', request()->only('page')) }}" data-realtime-endpoint="{{ route('realtime.credentials') }}">
<div class="section-heading"><p class="muted" data-device-count>{{ $deviceLoadError ? 'Devices unavailable' : $devices->total().' devices available to you' }}</p><div class="flex gap-3"><button class="agri-btn agri-btn-secondary" type="button" data-add-device><i data-lucide="plus"></i>Add device</button>@can('manage-system')<a class="agri-btn agri-btn-primary" href="{{ route('devices.create') }}"><i data-lucide="radio"></i>Register device</a>@endcan</div></div>
<p class="muted text-sm mb-4" data-device-list-status role="status" aria-live="polite"></p>
<div data-device-list>@include('devices.list')</div>
<dialog class="agri-card p-6 w-[calc(100%_-_2rem)] max-w-xl max-h-[90vh] overflow-y-auto rounded-2xl backdrop:bg-black/40" data-add-device-dialog aria-labelledby="add-device-title">
<h2 id="add-device-title">Add device</h2>
<p class="muted text-sm mt-2">Register a monitoring device to your account.</p>
<form class="form-stack mt-5" method="POST" action="{{ route('devices.register') }}" data-add-device-form>
@csrf
<x-input name="name" label="Device Name" maxlength="255" required />
<x-input name="device_id" label="Device ID" maxlength="100" pattern="[a-zA-Z0-9_-]+" required />
<p class="muted text-xs">Use a unique ID containing letters, numbers, dashes or underscores.</p>
<div class="field"><label for="device_type">Device Type</label><select id="device_type" name="device_type" required><option value="">Choose a type</option>@foreach(['ESP32', 'ESP8266', 'Arduino', 'Other'] as $type)<option value="{{ $type }}">{{ $type }}</option>@endforeach</select></div>
<x-input name="location" label="Device Location" maxlength="255" required />
<div class="field"><label for="description">Description (optional)</label><textarea id="description" name="description" class="form-input" maxlength="2000" rows="3"></textarea></div>
<p class="field-error" data-add-device-error role="alert"></p>
<p class="muted text-sm" data-add-device-status role="status" aria-live="polite"></p>
<div class="notice success hidden" data-add-device-success><p data-add-device-message></p><p class="mt-3">Save this device token for hardware setup. It is shown only once.</p><code class="block break-all mt-3" data-add-device-token></code></div>
<div class="form-actions"><button class="agri-btn agri-btn-secondary" type="button" data-cancel-add-device>Cancel</button><button class="agri-btn agri-btn-primary" type="submit">Add Device</button></div>
</form>
</dialog>
</section>
@endsection
