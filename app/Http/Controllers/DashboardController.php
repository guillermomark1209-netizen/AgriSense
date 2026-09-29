<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\SensorReading;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $crops = $user->crops()->with('devices')->latest()->limit(6)->get();
        $cropCount = $user->crops()->count();
        $activeDevices = $user->devices()->where('is_active', true)->where('last_seen_at', '>', now()->subMinutes(config('agrisense.stale_minutes')))->count();
        $alerts = Alert::where('user_id', $user->id)->where('status', 'active')->with('crop')->latest('detected_at')->limit(4)->get();
        $alertsCount = Alert::where('user_id', $user->id)->where('status', 'active')->count();
        $latestReading = SensorReading::whereHas('device', fn ($q) => $q->where('user_id', $user->id))->with('crop')->latest('reading_at')->first();
        $notice = DB::table('system_settings')->where('key', 'farm_notice')->value('value');

        return view('dashboard.index', compact('crops', 'cropCount', 'activeDevices', 'alerts', 'alertsCount', 'latestReading', 'notice'));
    }
}
