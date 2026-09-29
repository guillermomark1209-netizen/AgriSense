@props(['title','description'=>'','icon'=>'sprout'])
<div class="empty-state"><span><i data-lucide="{{ $icon }}"></i></span><h3>{{ $title }}</h3><p>{{ $description }}</p>{{ $slot }}</div>
