<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\SensorReading;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class HistoryController extends Controller
{
    public function index(Request $request): View
    {
        $crops = $request->user()->crops()->get();
        $sensorReadings = SensorReading::with('device')->whereHas('device', fn ($q) => $q->where('user_id', $request->user()->id))->latest('reading_at')->paginate(20);
        $alerts = Alert::where('user_id', $request->user()->id)->latest('detected_at')->limit(15)->get();
        $events = DB::table('crop_events')->join('crops', 'crops.id', '=', 'crop_events.crop_id')->where('crops.user_id', $request->user()->id)->select('crop_events.*', 'crops.name')->latest('crop_events.created_at')->limit(20)->get();
        $conversations = $request->user()->conversations()->latest()->limit(10)->get();

        return view('history.index', compact('crops', 'sensorReadings', 'alerts', 'events', 'conversations'));
    }
}
