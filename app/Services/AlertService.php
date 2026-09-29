<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\SensorReading;

class AlertService
{
    public function evaluate(SensorReading $reading): void
    {
        $crop = $reading->crop;
        foreach ($crop->thresholds()->where('variety', $crop->variety)->where('growth_stage', $crop->growth_stage)->whereHas('source', fn ($q) => $q->eligible())->get() as $threshold) {
            $value = $reading->{$threshold->sensor_type};
            if ($value === null) {
                continue;
            }
            $low = $threshold->min_value !== null && $value < $threshold->min_value;
            $high = $threshold->max_value !== null && $value > $threshold->max_value;
            $query = Alert::where('device_id', $reading->device_id)->where('crop_id', $crop->id)->where('sensor_type', $threshold->sensor_type)->where('status', 'active');
            if (! $low && ! $high) {
                $query->update(['status' => 'resolved', 'resolved_at' => now()]);

                continue;
            }
            $alert = $query->first() ?? new Alert;
            $alert->fill([
                'user_id' => $crop->user_id, 'crop_id' => $crop->id, 'device_id' => $reading->device_id,
                'sensor_type' => $threshold->sensor_type, 'severity' => 'warning', 'status' => 'active',
                'message' => config('agrisense.sensors.'.$threshold->sensor_type.'.label').' is '.($low ? 'below' : 'above').' the verified range for '.$crop->name.'.',
                'current_value' => $value, 'threshold_value' => $low ? $threshold->min_value : $threshold->max_value,
                'detected_at' => $reading->reading_at,
            ])->save();
        }
    }
}
