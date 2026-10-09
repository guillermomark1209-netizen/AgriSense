<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeviceRequest;
use App\Models\Crop;
use App\Models\Device;
use App\Models\SensorReading;
use App\Models\User;
use App\Services\AdminAuditService;
use App\Services\DeviceService;
use App\Services\SupabaseSensorReadingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DeviceController extends Controller
{
    private function authorizeDevice(Device $device): void
    {
        Gate::authorize('view', $device);
    }

    public function index(Request $request, SupabaseSensorReadingService $supabase): View
    {
        $devices = Device::query()
            ->when(! $request->user()->isAdmin(), fn ($query) => $query->where('user_id', $request->user()->id))
            ->with('crop')
            ->latest()
            ->paginate(12);

        $sensorDevice = $devices->getCollection()->firstWhere('device_id', config('agrisense.supabase_sensor_device_identifier'));
        if ($sensorDevice?->is_active) {
            try {
                $latest = $supabase->readings('1970-01-01T00:00:00Z', now()->toIso8601String(), 1)[0] ?? null;
                if ($latest) {
                    $sensorDevice->setAttribute('last_seen_at', $latest['reading_at']);
                }
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return view('devices.index', compact('devices'));
    }

    public function create(Request $request): View
    {
        Gate::authorize('manage-system');

        return view('devices.form', ['device' => new Device, 'crops' => Crop::with('user')->get(), 'owners' => User::orderBy('name')->get(['id', 'name', 'email'])]);
    }

    public function store(DeviceRequest $request, DeviceService $service): RedirectResponse
    {
        $device = DB::transaction(function () use ($request): Device {
            $device = Device::create([...$request->validated(), 'user_id' => $request->integer('user_id', $request->user()->id), 'status' => 'offline', 'is_active' => true]);
            app(AdminAuditService::class)->record('SENSOR_CREATED', 'devices', $device->id, 'Device registered.');

            return $device;
        });

        return redirect()->route('devices.show', $device)->with('device_token', $service->rotateToken($device))->with('success', 'Device registered. Save its token now; it is shown only once.');
    }

    public function show(Device $device, SupabaseSensorReadingService $supabase): View
    {
        $this->authorizeDevice($device);
        $device->load('crop');
        $usesSupabaseReadings = $device->device_id === config('agrisense.supabase_sensor_device_identifier');
        $readings = $device->readings()->latest('reading_at')->limit(10)->get();
        $sensorReadError = null;

        if ($usesSupabaseReadings && $device->is_active) {
            try {
                $remoteReadings = $supabase->readings('1970-01-01T00:00:00Z', now()->toIso8601String(), 10);
                $readings = collect($remoteReadings)->map(fn (array $reading) => new SensorReading($reading));

                if ($remoteReadings !== []) {
                    $device->setAttribute('last_seen_at', $remoteReadings[0]['reading_at']);
                }
            } catch (\Throwable $exception) {
                report($exception);
                $sensorReadError = str_contains($exception->getMessage(), 'not configured')
                    ? 'Set SUPABASE_SENSOR_SECRET_KEY in .env to read protected Supabase data.'
                    : 'Supabase readings could not be loaded. Check the Laravel log for the API error.';
                $readings = collect();
            }
        }

        return view('devices.show', compact('device', 'readings', 'usesSupabaseReadings', 'sensorReadError'));
    }

    public function edit(Device $device): View
    {
        Gate::authorize('update', $device);

        return view('devices.form', ['device' => $device, 'crops' => $device->user->crops()->with('user')->get()]);
    }

    public function update(DeviceRequest $request, Device $device): RedirectResponse
    {
        Gate::authorize('update', $device);
        DB::transaction(function () use ($request, $device): void {
            $device->update($request->safe()->except('device_id'));
            app(AdminAuditService::class)->record('SENSOR_UPDATED', 'devices', $device->id, 'Device details updated.');
        });

        return redirect()->route('devices.show', $device)->with('success', 'Device updated.');
    }

    public function rotate(Device $device, DeviceService $service): RedirectResponse
    {
        Gate::authorize('update', $device);

        $token = DB::transaction(function () use ($device, $service): string {
            $token = $service->rotateToken($device);
            app(AdminAuditService::class)->record('SENSOR_TOKEN_CHANGED', 'devices', $device->id, 'Device token rotated.');

            return $token;
        });

        return back()->with('device_token', $token)->with('success', 'Token replaced. Update your ESP32 with the new token.');
    }

    public function destroy(Device $device): RedirectResponse
    {
        Gate::authorize('delete', $device);
        DB::transaction(function () use ($device): void {
            $device->update(['is_active' => false, 'crop_id' => null, 'status' => 'offline']);
            $device->forceFill(['token_hash' => null])->save();
            app(AdminAuditService::class)->record('SENSOR_DELETED', 'devices', $device->id, 'Device disconnected and token revoked; historical readings retained.');
        });

        return redirect()->route('devices.index')->with('success', 'Device disconnected and its token revoked. Historical readings are retained.');
    }
}
