<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceUserAccess extends Model
{
    protected $table = 'device_user_access';

    protected $fillable = ['device_id', 'user_id', 'is_selected'];

    protected $casts = ['is_selected' => 'boolean'];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
