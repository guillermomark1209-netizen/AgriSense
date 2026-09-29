<aside id="navigation" class="sidebar" :class="{ 'is-open': menuOpen }" @keydown.escape.window="menuOpen=false">
<a href="{{ route(auth()->user()->isAdmin() ? 'admin.dashboard' : 'dashboard') }}" class="brand"><span class="brand-mark"><i data-lucide="sprout"></i></span><span>{{ config('app.name') }}<small>ROOTED IN INTELLIGENCE</small></span></a>
<div class="sidebar-label">{{ request()->is('admin/*') ? __('ui.administration') : __('ui.farm_workspace') }}</div>
<nav aria-label="Main navigation">
@php
$items = [[auth()->user()->isAdmin() ? __('navigation.admin_dashboard') : __('navigation.dashboard'),auth()->user()->isAdmin() ? 'admin.dashboard' : 'dashboard','layout-dashboard'],[__('navigation.my_crops'),'crops.index','sprout'],[__('navigation.monitoring'),'monitoring.index','activity'],[__('navigation.devices'),'devices.index','radio'],[__('navigation.alerts'),'alerts.index','bell-ring'],[__('navigation.ai_assistant'),'ai.assistant','sparkles'],[__('navigation.history'),'history.index','chart-no-axes-combined'],[__('navigation.profile'),'profile.index','user-round']];
@endphp
@foreach($items as [$label,$route,$icon])
<a href="{{ route($route) }}" @class(['nav-link','active'=>request()->routeIs($route) || ($route==='crops.index' && request()->routeIs('crops.*')) || ($route==='devices.index' && request()->routeIs('devices.*'))]) @if(request()->routeIs($route)) aria-current="page" @endif><i data-lucide="{{ $icon }}"></i>{{ $label }}</a>
@endforeach
@if(auth()->user()->hasRole('admin'))
<div class="sidebar-label">{{ __('ui.admin_tools') }}</div>
<a href="{{ route('admin.dashboard') }}" class="nav-link"><i data-lucide="shield-check"></i>{{ __('ui.admin_overview') }}</a>
<a href="{{ route('admin.sources.index') }}" class="nav-link"><i data-lucide="library"></i>{{ __('ui.knowledge_sources') }}</a>
<a href="{{ route('admin.documents.index') }}" class="nav-link"><i data-lucide="files"></i>{{ __('ui.documents') }}</a>
@foreach(['users','crops','devices','sensor-data','alerts','conversations','audit-logs','ai-audit-logs'] as $section)
<a href="{{ route('admin.manage',$section) }}" class="nav-link compact">{{ ucwords(str_replace('-',' ',$section)) }}</a>
@endforeach
<a href="{{ route('admin.settings') }}" class="nav-link compact">{{ __('ui.system_settings') }}</a>
@endif
</nav>
<div class="sidebar-bottom"><div class="field-note"><i data-lucide="leaf"></i><p>{{ __('ui.small_observations') }}</p></div>
<form method="POST" action="{{ route('locale.update') }}" class="locale-form">@csrf
<label for="locale"><i data-lucide="languages"></i>{{ __('navigation.language') }}</label>
<select id="locale" name="locale" onchange="this.form.submit()" aria-label="{{ __('navigation.choose_language') }}">
<option value="en" @selected(app()->getLocale() === 'en')>English</option>
<option value="tl" @selected(app()->getLocale() === 'tl')>Filipino (Tagalog)</option>
<option value="ceb" @selected(app()->getLocale() === 'ceb')>Bisaya (Cebuano)</option>
</select>
</form>
<a href="{{ route('settings') }}" class="nav-link"><i data-lucide="settings-2"></i>{{ __('navigation.settings') }}</a>
<a href="{{ route('help') }}" class="nav-link"><i data-lucide="circle-help"></i>{{ __('navigation.help') }}</a>
<form method="POST" action="{{ route('logout') }}" data-logout>@csrf<button class="nav-link w-full" type="submit"><i data-lucide="log-out"></i>{{ __('navigation.sign_out') }}</button></form>
</div></aside>
