<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Alert extends Model
{
    protected $table = 'alerts';

    protected $fillable = [
        'resolved_at',
        'user_id',
        'crop_id',
        'device_id',
        'sensor_type',
        'severity',
        'status',
        'message',
        'current_value',
        'threshold_value',
        'detected_at',
    ];

    protected $casts = [
        'detected_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function crop()
    {
        return $this->belongsTo(Crop::class);
    }

    public function device()
    {
        return $this->belongsTo(Device::class);
    }
}
