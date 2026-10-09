<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SensorReading extends Model
{
    protected $table = 'sensor_readings';

    protected $fillable = [
        'reading_id',
        'device_id',
        'crop_id',
        'temperature',
        'humidity',
        'soil_moisture',
        'soil_ph',
        'light_intensity',
        'soil_temperature',
        'air_temperature',
        'air_humidity',
        'light_percent',
        'soil_raw',
        'ldr_raw',
        'ph_raw',
        'pump',
        'reading_at',
    ];

    protected $casts = [
        'reading_at' => 'datetime',
        'temperature' => 'float',
        'humidity' => 'float',
        'soil_moisture' => 'float',
        'soil_ph' => 'float',
        'light_intensity' => 'float',
        'soil_temperature' => 'float',
        'air_temperature' => 'float',
        'air_humidity' => 'float',
        'light_percent' => 'float',
        'soil_raw' => 'integer',
        'ldr_raw' => 'integer',
        'ph_raw' => 'float',
        'pump' => 'boolean',
    ];

    public function device()
    {
        return $this->belongsTo(Device::class);
    }

    public function crop()
    {
        return $this->belongsTo(Crop::class);
    }
}
