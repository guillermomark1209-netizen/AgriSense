<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        Gate::define('manage-system', fn (User $user): bool => $user->isAdmin());

        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by(
                strtolower($request->input('email', '')).'|'.$request->ip()
            ),
            Limit::perMinute(20)->by($request->ip()),
        ]);

        RateLimiter::for('device-readings', fn (Request $request) => [
            Limit::perMinute(120)->by(
                hash('sha256', $request->bearerToken() ?? $request->ip())
            ),
            Limit::perMinute(300)->by($request->ip()),
        ]);
    }
}