@extends('layouts.app')
@section('title',$device->exists ? 'Edit monitoring device' : 'Register a device')
@section('subtitle','Connect an ESP32 to your growing space.')
@section('content')
<form class="agri-card p-6 form-stack" method="POST" action="{{ $device->exists ? route('devices.update',$device) : route('devices.store') }}">@csrf @if($device->exists) @method('PUT') @endif
<x-input name="name" label="Device name" :value="$device->name" required />
@unless($device->exists)<div class="field"><label for="user_id">Device owner</label><select name="user_id" id="user_id">@foreach($owners as $owner)<option value="{{ $owner->id }}" @selected(old('user_id', auth()->id()) == $owner->id)>{{ $owner->name }} · {{ $owner->email }}</option>@endforeach</select>@error('user_id')<p class="field-error">{{ $message }}</p>@enderror<p class="muted text-xs">The assigned crop must belong to this owner.</p></div>@endunless
@if(!$device->exists)<x-input name="device_id" label="Unique device ID · letters, numbers, dashes or underscores" required />@endif
<div class="field"><label for="crop_id">Assigned crop</label><select name="crop_id" id="crop_id"><option value="">Unassigned</option>@foreach($crops as $crop)<option value="{{ $crop->id }}" @selected(old('crop_id',$device->crop_id)==$crop->id)>{{ $crop->name }} · {{ $crop->location }} · {{ $crop->user->name }}</option>@endforeach</select>@error('crop_id')<p class="field-error">{{ $message }}</p>@enderror</div>
@if($device->exists)<div class="field"><label for="is_active">Device access</label><select name="is_active" id="is_active"><option value="1" @selected($device->is_active)>Enabled</option><option value="0" @selected(!$device->is_active)>Disabled</option></select></div>@endif
<p class="notice">Online status is determined by the last authenticated device contact.</p>
<div class="form-actions"><a class="agri-btn agri-btn-secondary" href="{{ route('devices.index') }}">Cancel</a><button class="agri-btn agri-btn-primary">Save device</button></div></form>
@endsection
