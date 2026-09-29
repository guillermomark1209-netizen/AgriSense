@extends('layouts.app')
@section('title',$crop->exists ? 'Edit crop' : 'Add a crop')
@section('subtitle',$crop->exists ? 'Update crop details.' : 'Upload a crop image to identify and add it.')
@section('content')
<form class="agri-card p-6 form-stack" method="POST" enctype="multipart/form-data" action="{{ $crop->exists ? route('crops.update',$crop) : route('crops.store') }}">@csrf @if($crop->exists) @method('PUT') @endif
@if(! $crop->exists)
<x-input name="image" label="Upload Crop Image" type="file" accept="image/jpeg,image/png,image/webp" capture="environment" required data-image-input /><p class="muted text-sm">JPG, JPEG, PNG, or WebP. Use your camera when supported.</p><img data-image-preview hidden class="upload-preview" alt="Selected crop photo preview">
@else
<div class="grid gap-5 md:grid-cols-2">@foreach(['name'=>'Crop name','scientific_name'=>'Scientific name','variety'=>'Variety','growth_stage'=>'Growth stage','location'=>'Location / plot'] as $key=>$label)<x-input :name="$key" :label="$label" :value="$crop->{$key}" />@endforeach<x-input name="planting_date" label="Planting date" type="date" :value="$crop->planting_date" /><x-input name="expected_harvest_date" label="Expected harvest date" type="date" :value="$crop->expected_harvest_date" /><div class="field"><label for="status">Your observed condition</label><select id="status" name="status">@foreach(['unknown','healthy','warning','critical'] as $status)<option value="{{ $status }}" @selected(old('status',$crop->status ?? 'unknown')===$status)>{{ ucfirst($status) }}</option>@endforeach</select></div><x-input name="image" label="Crop photo" type="file" accept="image/jpeg,image/png,image/webp" data-image-input /><img data-image-preview hidden class="upload-preview" alt="Selected crop photo preview"></div>
@endif
<div class="form-actions"><a class="agri-btn agri-btn-secondary" href="{{ route('crops.index') }}">Cancel</a><button class="agri-btn agri-btn-primary" type="submit">{{ $crop->exists ? 'Save crop' : 'Add Crop / Analyze Image' }}</button></div></form>
@endsection
