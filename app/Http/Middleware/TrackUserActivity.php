<?php

namespace App\Http\Middleware;

use App\Services\UserSessionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackUserActivity
{
    public function __construct(private UserSessionService $userSessions) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $now = now()->getTimestamp();
        $lastTrackedAt = (int) $request->session()->get('user_session_last_activity', 0);

        if ($lastTrackedAt <= $now - 60) {
            $this->userSessions->recordActivity($request->user());
            $request->session()->put('user_session_last_activity', $now);
        }

        return $next($request);
    }
}
