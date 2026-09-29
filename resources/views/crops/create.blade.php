@extends('layouts.app')

@section('title', 'Add Crop')
@section('subtitle', 'Upload a crop image to identify and add it.')

@section('content')
<div class="agri-card p-5">
    <form method="POST" action="{{ route('crops.store') }}" enctype="multipart/form-data" class="space-y-5">
        @csrf

        <div class="field">
            <x-input type="file" name="image" label="Upload Crop Image" accept="image/jpeg,image/png,image/webp" capture="environment" required data-image-input />
            <p class="muted text-sm mt-2">JPG, JPEG, PNG, or WebP. On supported devices, you can take a photo with your camera.</p>
        </div>

        <img class="upload-preview" data-image-preview hidden alt="Selected crop image preview">

        <div class="flex justify-end gap-3">
            <a href="{{ route('crops.index') }}" class="agri-btn agri-btn-secondary">Cancel</a>
            <button type="submit" class="agri-btn agri-btn-primary">Add Crop / Analyze Image <i data-lucide="sparkles"></i></button>
        </div>
    </form>
</div>
@endsection
