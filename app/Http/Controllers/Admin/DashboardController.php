<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AIMessage;
use App\Models\Alert;
use App\Models\Crop;
use App\Models\Device;
use App\Models\Source;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $activeSessions = DB::table('user_sessions')
            ->join('users', 'user_sessions.user_id', '=', 'users.id')
            ->where('user_sessions.status', 'online')
            ->where('user_sessions.last_activity', '>=', now()->subMinutes(5))
            ->orderByDesc('user_sessions.last_activity')
            ->limit(10)
            ->get(['users.name', 'users.email', 'users.role', 'user_sessions.last_activity']);

        $loginActivity = DB::table('audit_logs')
            ->leftJoin('users', 'audit_logs.user_id', '=', 'users.id')
            ->whereIn('audit_logs.action', ['LOGIN_SUCCEEDED', 'LOGOUT_SUCCEEDED'])
            ->latest('audit_logs.created_at')
            ->limit(20)
            ->get(['audit_logs.action', 'audit_logs.ip_address', 'audit_logs.created_at', 'users.name', 'users.email']);

        return view('admin.dashboard', [
            'totalUsers' => User::count(),
            'registeredCrops' => Crop::count(),
            'onlineDevices' => Device::where('is_active', true)->where('last_seen_at', '>', now()->subMinutes(config('agrisense.stale_minutes')))->count(),
            'alertsToday' => Alert::whereDate('detected_at', today())->count(),
            'verifiedSources' => Source::eligible()->count(),
            'aiQuestionsToday' => AIMessage::where('role', 'user')->whereDate('created_at', today())->count(),
            'activeSessions' => $activeSessions,
            'loginActivity' => $loginActivity,
        ]);
    }
}
