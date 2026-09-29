@extends('layouts.app')
@section('title','My crops')
@section('subtitle','Every growing space has a story. Keep yours close.')
@section('content')
<div class="section-heading"><p class="muted">{{ $crops->total() }} crops in your farm</p>@can('create', \App\Models\Crop::class)<a class="agri-btn agri-btn-primary" href="{{ route('crops.create') }}"><i data-lucide="plus"></i>Add crop</a>@endcan</div>
<div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">@forelse($crops as $crop)<x-crop-card :crop="$crop" />@empty<div class="agri-card col-span-full"><x-empty-state title="No crops yet" description="Add your first crop to begin monitoring its growing conditions." /></div>@endforelse</div><div class="mt-5">{{ $crops->links() }}</div>
@endsection
