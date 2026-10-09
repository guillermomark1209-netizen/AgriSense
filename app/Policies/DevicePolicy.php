<?php

namespace App\Policies;

use App\Models\Device;
use App\Models\User;

class DevicePolicy
{
    public function view(User $user, Device $device): bool
    {
        return $device->user_id === $user->id || $user->isAdmin()
            || $device->authorizedUsers()->whereKey($user->id)->exists();
    }

    public function update(User $user, Device $device): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Device $device): bool
    {
        return $user->isAdmin();
    }
}
