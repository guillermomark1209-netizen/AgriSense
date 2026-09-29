<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class UserSessionService
{
    public function recordLogin(User $user, string $sessionId, ?string $ipAddress): void
    {
        $now = now();

        DB::table('user_sessions')->upsert([
            [
                'user_id' => $user->id,
                'session_id' => $sessionId,
                'ip_address' => $ipAddress,
                'status' => 'online',
                'login_at' => $now,
                'last_activity' => $now,
                'logout_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ], ['user_id'], ['session_id', 'ip_address', 'status', 'login_at', 'last_activity', 'logout_at', 'updated_at']);
    }

    public function recordActivity(User $user): void
    {
        DB::table('user_sessions')
            ->where('user_id', $user->id)
            ->where('status', 'online')
            ->where('last_activity', '<=', now()->subMinute())
            ->update(['last_activity' => now(), 'updated_at' => now()]);
    }

    public function recordLogout(User $user): void
    {
        $now = now();

        DB::table('user_sessions')
            ->where('user_id', $user->id)
            ->update([
                'status' => 'inactive',
                'logout_at' => $now,
                'last_activity' => $now,
                'updated_at' => $now,
            ]);
    }
}
