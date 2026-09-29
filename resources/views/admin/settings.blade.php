@extends('layouts.app')
@section('title','System settings')
@section('subtitle','Manage farm-wide information and review integration readiness.')
@section('content')
<form method="POST" action="{{ route('admin.settings') }}" class="agri-card p-6 form-stack">@csrf<div class="field"><label for="farm_notice">Farm notice shown on dashboards</label><textarea name="farm_notice" id="farm_notice" maxlength="500">{{ old('farm_notice',$settings['farm_notice'] ?? '') }}</textarea></div><button class="agri-btn agri-btn-primary self-start">Save notice</button></form>
<section class="agri-card p-6 mt-6"><h2>Integration readiness</h2><p class="muted text-sm mt-2">These indicators show configuration presence. They do not verify external connectivity.</p><dl class="grid gap-4 mt-5 md:grid-cols-2"><div><dt>Gemini</dt><dd>{{ config('agrisense.gemini_key') ? 'Key configured' : 'Key missing' }}</dd></div><div><dt>Supabase Storage</dt><dd>{{ config('agrisense.supabase_url') && config('agrisense.supabase_key') ? 'Configured' : 'Configuration missing' }}</dd></div><div><dt>Database driver</dt><dd>{{ config('database.default') }}</dd></div><div><dt>Private Realtime</dt><dd>{{ config('agrisense.realtime_enabled') ? 'Enabled; requires database setup' : 'Disabled; monitoring uses polling' }}</dd></div></dl></section>
@endsection
