<?php

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\PrivateResponse;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\TrackUserActivity;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

        // Trust Vercel's reverse proxy and forwarded HTTPS headers.
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR |
                     Request::HEADER_X_FORWARDED_HOST |
                     Request::HEADER_X_FORWARDED_PORT |
                     Request::HEADER_X_FORWARDED_PROTO
        );

        $middleware->alias([
            'admin' => AdminMiddleware::class,
            'track-user-activity' => TrackUserActivity::class,
        ]);

        $middleware->redirectUsersTo(
            fn (Request $request): string => route(
                $request->user()->isAdmin()
                    ? 'admin.dashboard'
                    : 'dashboard'
            )
        );

        $middleware->web(append: [
            SetLocale::class,
            PrivateResponse::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['otp', 'token']);
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })
    ->create();
