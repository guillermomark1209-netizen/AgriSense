<?php

use App\Models\User;
use App\Services\AdminAuditService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Schedule::call(fn (): int => DB::table(config('session.table'))
    ->where('last_activity', '<=', now()->subMinutes(config('session.lifetime'))->getTimestamp())
    ->delete())->daily();

Artisan::command('agrisense:admin {email}', function () {
    $user = User::where('email', $this->argument('email'))->first();
    if (! $user) {
        $this->error('Register this account through the application first.');

        return 1;
    }
    DB::transaction(function () use ($user): void {
        $user->role = 'admin';
        $user->save();
        app(AdminAuditService::class)->record('USER_ROLE_CHANGED', 'users', $user->id, 'Administrator access granted through the server console.');
    });
    $this->info('Administrator access granted to the existing account.');

    return 0;
})->purpose('Grant administrator access to an existing registered account');
