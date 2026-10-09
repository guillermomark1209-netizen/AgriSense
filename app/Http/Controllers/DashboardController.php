<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Device;
use App\Models\SensorReading;
use App\Services\SupabaseSensorReadingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request, SupabaseSensorReadingService $supabase): View
    {
        $user = $request->user();
        $crops = $user->crops()->with('devices')->latest()->limit(6)->get();
        $cropCount = $user->crops()->count();
        $activeDevices = $user->devices()->where('is_active', true)->where('last_seen_at', '>', now()->subMinutes(config('agrisense.stale_minutes')))->count();
        $alerts = Alert::where('user_id', $user->id)->where('status', 'active')->with('crop')->latest('detected_at')->limit(4)->get();
        $alertsCount = Alert::where('user_id', $user->id)->where('status', 'active')->count();
        $latestReading = SensorReading::whereHas('device', fn ($q) => $q->where('user_id', $user->id))->with('crop')->latest('reading_at')->first();
        $sensorDevice = Device::query()
            ->where('user_id', $user->id)
            ->where('device_id', config('agrisense.supabase_sensor_device_identifier'))
            ->where('is_active', true)
            ->whereHas('crop', fn ($query) => $query->where('user_id', $user->id))
            ->first();
        if ($sensorDevice || $user->isAdmin()) {
            $latestReading = null;
            try {
                $remoteLatest = $supabase->readings('1970-01-01T00:00:00Z', now()->toIso8601String(), 1)[0] ?? null;
                if ($remoteLatest) {
                    $latestReading = new SensorReading($remoteLatest);
                    $latestReading->setRelation('crop', $sensorDevice?->crop);
                }
            } catch (\Throwable $exception) {
                report($exception);
            }
        }
        $notice = DB::table('system_settings')->where('key', 'farm_notice')->value('value');

        $devices = $user->isAdmin() ? Device::query()->with('crop')->get() : $user->devices()->with('crop')->get();

        return view('dashboard.index', compact('crops', 'cropCount', 'activeDevices', 'alerts', 'alertsCount', 'latestReading', 'notice', 'devices', 'sensorDevice'));
    }
}
