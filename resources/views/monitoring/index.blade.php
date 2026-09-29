@extends('layouts.app')
@section('title','Live environmental monitoring')
@section('subtitle','Follow conditions over time, one crop and device at a time.')
@section('content')
<x-chart-card :crops="$crops" :devices="$devices" />
<div class="notice mt-5"><i data-lucide="info"></i>Readings refresh every 30 seconds while this page is visible. A device is considered offline after {{ config('agrisense.stale_minutes') }} minutes without contact.</div>
<a class="text-link" href="{{ route('devices.index') }}">Manage your monitoring devices <i data-lucide="arrow-right"></i></a>
@endsection
