<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Crop extends Model
{
    protected $table = 'crops';

    protected $fillable = [
        'user_id',
        'name',
        'crop_name',
        'common_name',
        'scientific_name',
        'variety',
        'planting_date',
        'growth_stage',
        'location',
        'expected_harvest_date',
        'status',
        'image_url',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function devices()
    {
        return $this->hasMany(Device::class);
    }

    public function thresholds()
    {
        return $this->hasMany(CropSensorThreshold::class);
    }

    public function alerts()
    {
        return $this->hasMany(Alert::class);
    }

    public function readings()
    {
        return $this->hasMany(SensorReading::class);
    }
}
