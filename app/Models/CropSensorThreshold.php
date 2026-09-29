<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CropSensorThreshold extends Model
{
    protected $table = 'crop_sensor_thresholds';

    protected $fillable = [
        'source_id',
        'variety',
        'growth_stage',
        'crop_id',
        'sensor_type',
        'min_value',
        'max_value',
        'optimal_min',
        'optimal_max',
        'unit',
        'source_reference',
    ];

    public function crop()
    {
        return $this->belongsTo(Crop::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }
}
