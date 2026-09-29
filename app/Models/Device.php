<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    protected $hidden = ['token_hash'];

    protected $casts = ['last_seen_at' => 'datetime', 'is_active' => 'boolean'];

    public function getOnlineAttribute(): bool
    {
        return $this->is_active && $this->last_seen_at?->gt(now()->subMinutes(config('agrisense.stale_minutes')));
    }

    protected $table = 'devices';

    protected $fillable = [
        'user_id',
        'crop_id',
        'device_id',
        'name',
        'status',
        'last_seen_at',
        'is_active',
        'battery_level',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function crop()
    {
        return $this->belongsTo(Crop::class);
    }

    public function readings()
    {
        return $this->hasMany(SensorReading::class);
    }
}
