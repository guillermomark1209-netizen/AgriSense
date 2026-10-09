<?php

namespace App\Http\Controllers;

use App\Models\Alert;
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
        $devices = $user->availableDevices()->with('crop')->get();
        $activeDevices = $user->availableDevices()->where('is_active', true)
            ->whereHas('readings', fn ($query) => $query->where('reading_at', '>', now()->subMinutes(config('agrisense.stale_minutes'))))->count();
        $alerts = Alert::where('user_id', $user->id)->where('status', 'active')->with('crop')->latest('detected_at')->limit(4)->get();
        $alertsCount = Alert::where('user_id', $user->id)->where('status', 'active')->count();
        $sensorDevice = $user->monitoringDevice();
        $latestReading = $sensorDevice?->readings()->with('crop')->latest('reading_at')->first();
        $canViewSupabaseSensor = (bool) $sensorDevice;
        $liveSensorDevice = $sensorDevice;
        $notice = DB::table('system_settings')->where('key', 'farm_notice')->value('value');

        return view('dashboard.index', compact('crops', 'cropCount', 'activeDevices', 'alerts', 'alertsCount', 'latestReading', 'notice', 'devices', 'sensorDevice', 'liveSensorDevice', 'canViewSupabaseSensor'));
    }
}
