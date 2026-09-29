<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}"><meta name="theme-color" content="#1B4332">
<meta name="agrisense-user" content="{{ auth()->id() }}"><meta name="agrisense-base" content="{{ url('/') }}/">
<title>@yield('title', __('ui.dashboard')) · {{ config('app.name') }}</title>
<link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
<script>document.documentElement.dataset.theme=localStorage.getItem('agrisense-theme')??(matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light');</script>
@vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body x-data="{ menuOpen: false }">
<a class="skip-link" href="#main">{{ __('ui.skip_to_content') }}</a>
<div class="app-frame"><div x-show="menuOpen" x-cloak class="drawer-backdrop" @click="menuOpen=false"></div>
@include('partials.sidebar')
<div class="workspace"><header class="topbar">
<div class="flex items-center gap-3"><button class="icon-button lg:hidden" @click="menuOpen=!menuOpen" :aria-expanded="menuOpen" aria-controls="navigation" aria-label="{{ __('ui.toggle_navigation') }}"><i data-lucide="menu"></i></button><span class="eyebrow">{{ __('ui.farm_connected') }}</span></div>
<div class="flex items-center gap-4"><span class="status-badge" data-account-role="{{ auth()->user()->role }}" aria-label="{{ __('ui.account_role', ['role' => ucfirst(auth()->user()->role)]) }}">{{ ucfirst(auth()->user()->role) }}</span><span class="connection-status" data-connection role="status">{{ __('ui.checking_connection') }}</span><button type="button" class="icon-button theme-toggle" data-theme-toggle aria-label="{{ __('ui.switch_to_dark_mode') }}" aria-pressed="false"><i data-lucide="sun" class="theme-icon-light"></i><i data-lucide="moon" class="theme-icon-dark"></i></button><a class="icon-button" href="{{ route('alerts.index') }}" aria-label="{{ __('ui.open_alerts') }}"><i data-lucide="bell"></i></a><a href="{{ route('profile.index') }}" class="avatar" aria-label="{{ __('ui.your_profile') }}">{{ mb_strtoupper(mb_substr(auth()->user()->name,0,1)) }}</a></div>
</header><main id="main" class="main-content">
<div class="page-heading"><div><h1>@yield('title', __('ui.dashboard'))</h1><p>@yield('subtitle', __('ui.closer_connection'))</p></div><span class="date-label"><i data-lucide="calendar-days"></i>{{ now()->translatedFormat('D, d M Y') }}</span></div>
@if(session('success'))<div class="notice success" role="status">{{ session('success') }}</div>@endif
@if($errors->any())<div class="notice error" role="alert"><strong>{{ __('ui.please_check_following') }}</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@yield('content')
<footer class="page-footer">{{ config('app.name') }} · {{ __('ui.growing_with_information') }}<a href="{{ route('help') }}">{{ __('ui.help_guidance') }}</a></footer>
</main></div></div>
@stack('scripts')
</body></html>
