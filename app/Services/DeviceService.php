<?php

namespace App\Services;

use App\Models\Device;
use Illuminate\Support\Str;

class DeviceService
{
    public function rotateToken(Device $device): string
    {
        $token = Str::random(64);
        $device->forceFill(['token_hash' => hash('sha256', $token)])->save();

        return $token;
    }
}
