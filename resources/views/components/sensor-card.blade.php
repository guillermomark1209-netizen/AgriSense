@props(['sensor','reading'=>null])
@php
$meta = config('agrisense.sensors.'.$sensor);
$value = match ($sensor) {
    'air_temperature' => $reading?->air_temperature ?? $reading?->temperature,
    'air_humidity' => $reading?->air_humidity ?? $reading?->humidity,
    default => $reading?->{$sensor},
};
$thresholdSensors = match ($sensor) {
    'air_temperature' => ['air_temperature', 'temperature'],
    'air_humidity' => ['air_humidity', 'humidity'],
    default => [$sensor],
};
$stale = !$reading || $reading->reading_at->lt(now()->subMinutes(config('agrisense.stale_minutes')));
$threshold = $reading?->crop?->thresholds()->whereIn('sensor_type',$thresholdSensors)->where('variety',$reading->crop->variety)->where('growth_stage',$reading->crop->growth_stage)->whereHas('source',fn($q)=>$q->eligible())->first();
$status = $value === null ? 'No reading' : ($stale ? 'Stale reading' : (!$threshold ? 'No verified range' : (($threshold->min_value !== null && $value < $threshold->min_value) || ($threshold->max_value !== null && $value > $threshold->max_value) ? 'warning' : 'Within range')));
@endphp
<div class="agri-card sensor-card"><div class="sensor-top"><span>{{ $meta['label'] }}</span><i data-lucide="{{ $meta['icon'] }}"></i></div><div class="sensor-value">{{ $value === null ? '—' : number_format($value,1) }}<small>{{ $meta['unit'] }}</small></div><x-status-badge :status="$status" /><time class="sensor-time" datetime="{{ $reading?->reading_at?->toIso8601String() }}">{{ $reading?->reading_at?->diffForHumans() ?? 'Waiting for your device' }}</time></div>
