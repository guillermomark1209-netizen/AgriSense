@extends('layouts.app')

@section('title', 'Edit Crop')
@section('subtitle', 'Update crop details.')

@section('content')
<div class="agri-card p-5">
    <form method="POST" action="{{ route('crops.update', $crop) }}" class="space-y-5">
        @csrf
        @method('PUT')

        <div class="grid gap-5 md:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium">Crop name</label>
                <input name="name" type="text" required value="{{ $crop->name }}" class="w-full rounded-xl border border-[color:var(--agri-border)] bg-[color:var(--agri-secondary-bg)] px-3 py-2.5" />
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Scientific name</label>
                <input name="scientific_name" type="text" required value="{{ $crop->scientific_name }}" class="w-full rounded-xl border border-[color:var(--agri-border)] bg-[color:var(--agri-secondary-bg)] px-3 py-2.5" />
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Variety</label>
                <input name="variety" type="text" required value="{{ $crop->variety }}" class="w-full rounded-xl border border-[color:var(--agri-border)] bg-[color:var(--agri-secondary-bg)] px-3 py-2.5" />
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Growth stage</label>
                <input name="growth_stage" type="text" required value="{{ $crop->growth_stage }}" class="w-full rounded-xl border border-[color:var(--agri-border)] bg-[color:var(--agri-secondary-bg)] px-3 py-2.5" />
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Planting date</label>
                <input name="planting_date" type="date" required value="{{ $crop->planting_date }}" class="w-full rounded-xl border border-[color:var(--agri-border)] bg-[color:var(--agri-secondary-bg)] px-3 py-2.5" />
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Location / plot</label>
                <input name="location" type="text" required value="{{ $crop->location }}" class="w-full rounded-xl border border-[color:var(--agri-border)] bg-[color:var(--agri-secondary-bg)] px-3 py-2.5" />
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Expected harvest date</label>
                <input name="expected_harvest_date" type="date" value="{{ $crop->expected_harvest_date }}" class="w-full rounded-xl border border-[color:var(--agri-border)] bg-[color:var(--agri-secondary-bg)] px-3 py-2.5" />
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Status</label>
                <select name="status" class="w-full rounded-xl border border-[color:var(--agri-border)] bg-[color:var(--agri-secondary-bg)] px-3 py-2.5">
                    <option value="healthy" {{ $crop->status === 'healthy' ? 'selected' : '' }}>Healthy</option>
                    <option value="warning" {{ $crop->status === 'warning' ? 'selected' : '' }}>Warning</option>
                    <option value="critical" {{ $crop->status === 'critical' ? 'selected' : '' }}>Critical</option>
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="mb-1 block text-sm font-medium">Image URL</label>
                <input name="image_url" type="url" value="{{ $crop->image_url }}" class="w-full rounded-xl border border-[color:var(--agri-border)] bg-[color:var(--agri-secondary-bg)] px-3 py-2.5" />
            </div>
        </div>

        <div class="flex justify-end gap-3">
            <a href="{{ route('crops.show', $crop) }}" class="agri-btn agri-btn-secondary">Cancel</a>
            <button type="submit" class="agri-btn agri-btn-primary">Update Crop</button>
        </div>
    </form>
</div>
@endsection
