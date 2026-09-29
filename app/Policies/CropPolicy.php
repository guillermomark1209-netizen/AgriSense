<?php

namespace App\Policies;

use App\Models\Crop;
use App\Models\User;

class CropPolicy
{
    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, Crop $crop): bool
    {
        return $crop->user_id === $user->id || $user->hasRole('admin');
    }

    public function update(User $user, Crop $crop): bool
    {
        return $crop->user_id === $user->id || $user->isAdmin();
    }

    public function delete(User $user, Crop $crop): bool
    {
        return $user->isAdmin();
    }
}
