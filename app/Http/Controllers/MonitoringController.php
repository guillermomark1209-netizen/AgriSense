<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\SensorReading;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MonitoringController extends Controller
{
    public function latest(Request $request): JsonResponse
    {
        try {
            $device = $request->user()->monitoringDevice();
            abort_if($request->filled('device_id') && (int) $request->input('device_id') !== (int) $device?->id, 404);

            $reading = $device?->readings()->with('crop')->latest('reading_at')->first();
            $observedAt = $reading?->reading_at;
            $stale = ! $device?->is_active || ! $observedAt || $observedAt->lt(now()->subMinutes(config('agrisense.stale_minutes')));
            $state = ! $device || ! $reading ? 'unknown' : ($stale ? 'offline' : 'online');

            return response()->json([
                'device' => $device ? ['id' => $device->id, 'name' => $device->name, 'device_id' => $device->device_id] : null,
                'reading' => $reading?->only([...array_keys(config('agrisense.sensors')), 'temperature', 'humidity', 'pump', 'reading_at', 'created_at', 'crop_id']),
                'stale' => $stale,
                'status' => $state,
                'last_seen_at' => $observedAt?->toIso8601String(),
                'synced_at' => now()->toIso8601String(),
                'crop' => $device?->crop?->name,
                'device_online' => $state === 'online',
                'active_devices' => $request->user()->availableDevices()->where('is_active', true)
                    ->whereHas('readings', fn ($query) => $query->where('reading_at', '>', now()->subMinutes(config('agrisense.stale_minutes'))))->count(),
            ])->header('Cache-Control', 'no-store, private');
        } catch (QueryException $exception) {
            report($exception);

            return response()->json(['message' => 'Sensor readings could not be loaded from Supabase. Please retry shortly.'], 503)->header('Cache-Control', 'no-store, private');
        }
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $crops = $user->crops()->get();
        $devices = $user->availableDevices()->with('crop')->orderBy('devices.name')->get();
        $selectedDeviceId = $user->monitoringDevice()?->id;

        return view('monitoring.index', compact('crops', 'devices', 'selectedDeviceId'));
    }

    public function data(Request $request): JsonResponse
    {
        $selectedDeviceId = $request->user()->monitoringDevice()?->id;
        abort_if($request->filled('device_id') && (int) $request->input('device_id') !== (int) $selectedDeviceId, 404);
        $device = $selectedDeviceId ? Device::query()->with('crop')->find($selectedDeviceId) : null;

        $data = $request->validate([
            'crop_id' => ['nullable', 'integer', Rule::exists('crops', 'id')->where('user_id', $request->user()->id)],
            'device_id' => ['nullable', 'integer'],
            'sensor' => ['sometimes', Rule::in(array_keys(config('agrisense.sensors')))],
            'range' => ['sometimes', Rule::in(['24h', '7d', '30d', 'custom'])],
            'from' => ['required_if:range,custom', 'nullable', 'date'],
            'to' => ['required_if:range,custom', 'nullable', 'date', 'after_or_equal:from'],
        ]);

        $sensor = $data['sensor'] ?? 'air_temperature';
        $range = $data['range'] ?? '24h';
        $start = match ($range) {
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            'custom' => Carbon::parse($data['from'])->startOfDay(),
            default => now()->subDay(),
        };
        $end = $range === 'custom' ? Carbon::parse($data['to'])->endOfDay() : now();
        abort_if($start->diffInDays($end) > 366, 422, 'Choose a period of at most one year.');

        $base = SensorReading::query()->where('device_id', $selectedDeviceId ?: 0)
            ->when(isset($data['crop_id']), fn ($query) => $query->where('crop_id', $data['crop_id']));
        $query = (clone $base)->whereBetween('reading_at', [$start, $end]);
        $statistics = (clone $query)->selectRaw("MIN({$sensor}) AS minimum, MAX({$sensor}) AS maximum, AVG({$sensor}) AS average, COUNT({$sensor}) AS count")->first();
        $readings = $query->orderByDesc('reading_at')->limit(2000)->get()->reverse()->values();
        $latest = (clone $base)->latest('reading_at')->first();
        $stale = ! $device?->is_active || ! $latest || $latest->reading_at->lt(now()->subMinutes(config('agrisense.stale_minutes')));

        return response()->json([
            'device' => $device ? ['id' => $device->id, 'name' => $device->name, 'device_id' => $device->device_id] : null,
            'points' => $readings->map(fn (SensorReading $reading): array => ['time' => $reading->reading_at->toIso8601String(), 'value' => $reading->{$sensor} === null ? null : (float) $reading->{$sensor}, 'device_id' => $reading->device_id]),
            'statistics' => $statistics,
            'latest' => $latest?->only([...array_keys(config('agrisense.sensors')), 'reading_at', 'crop_id']),
            'crop' => $device?->crop?->name,
            'stale' => $stale,
            'status' => ! $device || ! $latest ? 'unknown' : ($stale ? 'offline' : 'online'),
            'synced_at' => now()->toIso8601String(),
            'limited' => $readings->count() === 2000,
        ])->header('Cache-Control', 'no-store, private');
    }
}
