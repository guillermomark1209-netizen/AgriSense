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
        'reading_at',
    ];

    protected $casts = [
        'reading_at' => 'datetime',
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
