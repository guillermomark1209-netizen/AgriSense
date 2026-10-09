<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'device_type',
        'location',
        'description',
        'status',
        'last_seen_at',
        'is_active',
        'battery_level',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function crop(): BelongsTo
    {
        return $this->belongsTo(Crop::class);
    }

    public function readings(): HasMany
    {
        return $this->hasMany(SensorReading::class);
    }

    public function authorizedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'device_user_access')->withPivot('is_selected')->withTimestamps();
    }
}
