<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeviceRequest;
use App\Models\Crop;
use App\Models\Device;
use App\Models\User;
use App\Services\AdminAuditService;
use App\Services\DeviceService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DeviceController extends Controller
{
    private function authorizeDevice(Device $device): void
    {
        Gate::authorize('view', $device);
    }

    public function index(Request $request): Response|JsonResponse
    {
        $user = $request->user();
        $devices = null;
        $owners = collect();
        $deviceLoadError = null;

        try {
            $devices = Device::query()
                ->when(! $user->isAdmin(), fn ($query) => $query->where(fn ($query) => $query->where('devices.user_id', $user->id)
                    ->orWhereIn('devices.id', DB::table('device_user_access')->select('device_id')->where('user_id', $user->id))))
                ->with('crop')->orderByDesc('devices.id')->paginate(12)->withQueryString();
            $selectedDeviceId = $user->selectedDeviceAccess()->value('device_id');
            foreach ($devices as $device) {
                $device->setAttribute('is_selected', (string) $device->id === (string) $selectedDeviceId);
            }
            $owners = $user->isAdmin() ? User::query()->orderBy('name')->get(['id', 'name', 'email']) : collect();
        } catch (QueryException $exception) {
            report($exception);
            $deviceLoadError = in_array((string) $exception->getCode(), ['42501', '28000', '28P01'], true)
                ? 'The database denied access to your devices. Contact the administrator.'
                : 'Your devices could not be loaded. Check the database connection and try again.';
        }

        $data = compact('devices', 'owners', 'deviceLoadError');
        if ($request->expectsJson()) {
            if ($deviceLoadError) {
                return response()->json(['message' => $deviceLoadError], 503)->header('Cache-Control', 'no-store, private');
            }

            return response()->json([
                'html' => view('devices.list', $data)->render(),
                'total' => $devices->total(),
                'devices' => $devices->map(fn (Device $device): array => [
                    'id' => $device->id,
                    'device_id' => $device->device_id,
                    'name' => $device->name,
                    'device_type' => $device->device_type,
                    'location' => $device->location,
                    'status' => $device->online ? 'online' : 'offline',
                    'crop' => $device->crop?->name,
                    'last_seen_at' => $device->last_seen_at?->toIso8601String(),
                    'is_active' => $device->is_active,
                    'selected' => $device->is_selected,
                ]),
            ])->header('Cache-Control', 'no-store, private');
        }

        return response()->view('devices.index', $data, $deviceLoadError ? 503 : 200)->header('Cache-Control', 'no-store, private');
    }

    public function select(Request $request, Device $device): RedirectResponse
    {
        DB::transaction(function () use ($request, $device): void {
            $userId = $request->user()->id;
            User::query()->whereKey($userId)->lockForUpdate()->firstOrFail();
            $device = Device::query()->whereKey($device->id)->lockForUpdate()->firstOrFail();
            $hasAssociation = DB::table('device_user_access')->where('user_id', $userId)->where('device_id', $device->id)->exists();
            $isOwner = $device->user_id === $userId;
            abort_unless($hasAssociation || ($isOwner && $device->is_active) || ($request->user()->isAdmin() && $device->is_active), 404);

            DB::table('device_user_access')->where('user_id', $userId)->update(['is_selected' => false, 'updated_at' => now()]);
            DB::table('device_user_access')->upsert(
                [['device_id' => $device->id, 'user_id' => $userId, 'is_selected' => true, 'created_at' => now(), 'updated_at' => now()]],
                ['device_id', 'user_id'],
                ['is_selected', 'updated_at'],
            );
        });

        return redirect()->route('devices.index')->with('success', 'Selected device updated.');
    }

    public function grantAccess(Request $request, Device $device): RedirectResponse
    {
        Gate::authorize('manage-system');
        $data = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id']]);
        abort_if(! $device->is_active, 422, 'Enable the device before granting access.');
        abort_if((int) $data['user_id'] === (int) $device->user_id, 422, 'The device owner already has access.');

        $granted = DB::transaction(function () use ($data, $device): bool {
            User::query()->whereKey($data['user_id'])->lockForUpdate()->firstOrFail();
            Device::query()->whereKey($device->id)->lockForUpdate()->firstOrFail();

            return DB::table('device_user_access')->insertOrIgnore([
                'user_id' => $data['user_id'],
                'device_id' => $device->id,
                'is_selected' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]) === 1;
        });

        return back()->with('success', $granted ? 'Device access granted.' : 'This user already has access to the device.');
    }

    public function create(Request $request): View
    {
        Gate::authorize('manage-system');
        $owners = User::orderBy('name')->get(['id', 'name', 'email']);
        $selectedOwner = $owners->firstWhere('id', $request->old('user_id'));

        return view('devices.form', ['device' => new Device, 'crops' => Crop::where('user_id', $selectedOwner?->id)->with('user')->get(), 'owners' => $owners]);
    }

    public function ownerCrops(Request $request): JsonResponse
    {
        Gate::authorize('manage-system');
        $data = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id']]);

        return response()->json(['crops' => Crop::where('user_id', $data['user_id'])->orderBy('name')->get(['id', 'name', 'location'])])->header('Cache-Control', 'no-store, private');
    }

    public function store(DeviceRequest $request, DeviceService $service): RedirectResponse|JsonResponse
    {
        try {
            [$device, $token] = DB::transaction(function () use ($request, $service): array {
                $ownerId = $request->routeIs('devices.register') ? $request->user()->id : $request->integer('user_id');
                User::query()->whereKey($ownerId)->lockForUpdate()->firstOrFail();
                $device = Device::create([...$request->validated(), 'user_id' => $ownerId, 'status' => 'offline', 'is_active' => true]);
                DB::table('device_user_access')->updateOrInsert(
                    ['device_id' => $device->id, 'user_id' => $device->user_id],
                    ['is_selected' => ! DB::table('device_user_access')->where('user_id', $device->user_id)->where('is_selected', true)->exists(), 'created_at' => now(), 'updated_at' => now()],
                );
                app(AdminAuditService::class)->record('SENSOR_CREATED', 'devices', $device->id, 'Device registered.');

                return [$device, $service->rotateToken($device)];
            });
        } catch (QueryException $exception) {
            report($exception);
            $duplicate = in_array((string) $exception->getCode(), ['23505', '23000'], true)
                && str_contains($exception->getMessage(), 'device_id');
            $message = $duplicate ? 'This device ID is already registered.' : (in_array((string) $exception->getCode(), ['42501', '28000', '28P01'], true) ? 'Device registration was denied by the database. Contact the administrator.' : 'Device registration failed. Check the connection and try again.');
            $errors = [$duplicate ? 'device_id' : 'registration' => $message];

            return $request->expectsJson() ? response()->json(['message' => $message, 'errors' => $errors], 422) : back()->withInput()->withErrors($errors);
        }

        if ($request->routeIs('devices.register') && $request->expectsJson()) {
            return response()->json([
                'message' => 'Device added successfully. It will remain offline until it communicates with AgriSense.',
                'id' => $device->id,
                'device_token' => $token,
            ], 201)->header('Cache-Control', 'no-store, private');
        }

        $request->session()->flash('device_token', $token);
        $request->session()->flash('success', 'Device registered. Save its token now; it is shown only once.');

        return $request->expectsJson() ? response()->json(['redirect' => route('devices.show', $device)], 201) : redirect()->route('devices.show', $device);
    }

    public function show(Device $device): View
    {
        $this->authorizeDevice($device);
        $device->load('crop');
        $readings = $device->readings()->latest('reading_at')->limit(10)->get();
        $device->setAttribute('last_seen_at', $readings->first()?->reading_at ?? $device->last_seen_at);
        $sensorReadError = null;

        return view('devices.show', compact('device', 'readings', 'sensorReadError'));
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
