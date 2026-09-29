@extends('layouts.app')

@section('title', 'Register Device')
@section('subtitle', 'Assign an ESP32 monitoring device to a crop and track its health.')

@section('content')
<div class="agri-card p-5">
    <form method="POST" action="{{ route('devices.store') }}" class="space-y-5">
        @csrf

        <div class="grid gap-5 md:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium">Device name</label>
                <input name="name" type="text" required class="w-full rounded-xl border border-[color:var(--agri-border)] bg-[color:var(--agri-secondary-bg)] px-3 py-2.5" />
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Device ID</label>
                <input name="device_id" type="text" required class="w-full rounded-xl border border-[color:var(--agri-border)] bg-[color:var(--agri-secondary-bg)] px-3 py-2.5" />
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Crop</label>
                <select name="crop_id" class="w-full rounded-xl border border-[color:var(--agri-border)] bg-[color:var(--agri-secondary-bg)] px-3 py-2.5">
                    <option value="">Unassigned</option>
                    @foreach (App\Models\Crop::where('user_id', Auth::id())->get() as $crop)
                        <option value="{{ $crop->id }}">{{ $crop->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Status</label>
                <select name="status" class="w-full rounded-xl border border-[color:var(--agri-border)] bg-[color:var(--agri-secondary-bg)] px-3 py-2.5">
                    <option value="online">Online</option>
                    <option value="offline">Offline</option>
                </select>
            </div>
        </div>

        <div class="flex justify-end gap-3">
            <a href="{{ route('devices.index') }}" class="agri-btn agri-btn-secondary">Cancel</a>
            <button type="submit" class="agri-btn agri-btn-primary">Register Device</button>
        </div>
    </form>
</div>
@endsection
