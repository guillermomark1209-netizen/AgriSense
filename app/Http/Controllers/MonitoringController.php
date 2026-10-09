<?php

namespace App\Http\Controllers;

use App\Models\Crop;
use App\Models\Device;
use App\Models\SensorReading;
use App\Services\SupabaseSensorReadingService;
use Carbon\Carbon;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class MonitoringController extends Controller
{
    public function latest(Request $request, SupabaseSensorReadingService $supabase): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate(['device_id' => ['required', 'integer', 'exists:devices,id']]);
        $device = Device::query()
            ->whereKey($data['device_id'])
            ->where('device_id', config('agrisense.supabase_sensor_device_identifier'))
            ->where('is_active', true)
            ->when(! $user->isAdmin(), fn ($query) => $query->where('user_id', $user->id)
                ->whereHas('crop', fn ($crop) => $crop->where('user_id', $user->id)))
            ->firstOrFail();

        try {
            $reading = $supabase->readings('1970-01-01T00:00:00Z', now()->toIso8601String(), 1)[0] ?? null;
        } catch (RequestException $exception) {
            report($exception);

            return response()->json([
                'message' => 'Supabase rejected the sensor read.',
                'upstream_status' => $exception->response->status(),
            ], 503)->header('Cache-Control', 'no-store, private');
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => 'Add SUPABASE_SENSOR_SECRET_KEY to .env, then clear Laravel config cache.',
                'configured' => false,
            ], 503)->header('Cache-Control', 'no-store, private');
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Supabase sensor read failed. Check Laravel logs and the configured server key.',
            ], 503)
                ->header('Cache-Control', 'no-store, private');
        }

        $observedAt = $reading ? Carbon::parse($reading['reading_at']) : null;
        $isStale = ! $observedAt || $observedAt->lt(now()->subMinutes(config('agrisense.stale_minutes')));

        if ($observedAt && (! $device->last_seen_at?->equalTo($observedAt) || $device->status !== ($isStale ? 'offline' : 'online'))) {
            $device->update([
                'last_seen_at' => $observedAt,
                'status' => $isStale ? 'offline' : 'online',
            ]);
        }

        return response()->json([
            'reading' => $reading,
            'stale' => $isStale,
            'synced_at' => now()->toIso8601String(),
            'crop' => $device->crop?->name,
            'device_online' => ! $isStale,
            'active_devices' => $user->devices()
                ->where('is_active', true)
                ->where('last_seen_at', '>', now()->subMinutes(config('agrisense.stale_minutes')))
                ->count(),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function index(Request $request): View
    {
        $user = $request->user();

        $crops = Crop::query()
            ->when(! $user->isAdmin(), fn ($query) => $query->where('user_id', $user->id))
            ->get()
            ->unique('id')
            ->values();

        $devices = Device::query()
            ->when(
                ! $user->isAdmin(),
                fn ($query) => $query->where('user_id', $user->id)
            )
            ->get();

        return view('monitoring.index', [
            'crops' => $crops,
            'devices' => $devices,
        ]);
    }

    public function data(Request $request, SupabaseSensorReadingService $supabase): JsonResponse
    {
        $user = $request->user();

        $allowedDeviceIds = Device::query()
            ->when(
                ! $user->isAdmin(),
                fn ($query) => $query->where('user_id', $user->id)
                    ->whereHas('crop', fn ($crop) => $crop->where('user_id', $user->id))
            )
            ->pluck('id');

        $allowedCropIds = Crop::query()
            ->when(
                ! $user->isAdmin(),
                fn ($query) => $query->where('user_id', $user->id)
            )
            ->pluck('id');

        $data = $request->validate([
            'crop_id' => [
                'nullable',
                Rule::exists('crops', 'id')
                    ->when(! $user->isAdmin(), fn ($rule) => $rule->whereIn('id', $allowedCropIds)),
            ],
            'device_id' => [
                'nullable',
                Rule::exists('devices', 'id')
                    ->when(! $user->isAdmin(), fn ($rule) => $rule->whereIn('id', $allowedDeviceIds)),
            ],
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

        $end = $range === 'custom'
            ? Carbon::parse($data['to'])->endOfDay()
            : now();

        abort_if(
            $start->diffInDays($end) > 366,
            422,
            'Choose a period of at most one year.'
        );

        $remoteDevice = $request->filled('device_id')
            ? Device::query()
                ->whereKey($data['device_id'])
                ->where('device_id', config('agrisense.supabase_sensor_device_identifier'))
                ->where('is_active', true)
                ->when($request->filled('crop_id'), fn ($query) => $query->where('crop_id', $data['crop_id']))
                ->when(! $user->isAdmin(), fn ($query) => $query->where('user_id', $user->id)
                    ->whereHas('crop', fn ($crop) => $crop->where('user_id', $user->id)))
                ->first()
            : null;

        if ($remoteDevice) {
            try {
                $rows = $supabase->readings($start->toIso8601String(), $end->toIso8601String());
            } catch (\Throwable $exception) {
                report($exception);

                return response()->json(['message' => 'Supabase readings are temporarily unavailable.'], 503)
                    ->header('Cache-Control', 'no-store, private');
            }

            $rows = collect($rows)->sortBy('reading_at')->values();
            $values = $rows->pluck($sensor)->filter(fn ($value) => $value !== null)->map(fn ($value) => (float) $value);
            $latest = collect($rows)->last();

            return response()->json([
                'points' => $rows->map(fn ($reading) => [
                    'time' => Carbon::parse($reading['reading_at'])->toIso8601String(),
                    'value' => $reading[$sensor] === null ? null : (float) $reading[$sensor],
                    'device_id' => $reading['device_id'],
                ]),
                'statistics' => ['minimum' => $values->min(), 'maximum' => $values->max(), 'average' => $values->avg(), 'count' => $values->count()],
                'latest' => $latest,
                'crop' => $remoteDevice->crop?->name,
                'stale' => ! $latest || Carbon::parse($latest['reading_at'])->lt(now()->subMinutes(config('agrisense.stale_minutes'))),
                'synced_at' => now()->toIso8601String(),
                'limited' => count($rows) === 2000,
            ])->header('Cache-Control', 'no-store, private');
        }

        $base = SensorReading::query()
            ->when(
                ! $user->isAdmin(),
                fn ($query) => $query->whereIn('device_id', $allowedDeviceIds)
            )
            ->when(
                $request->filled('crop_id'),
                fn ($query) => $query->where('crop_id', $data['crop_id'])
            )
            ->when(
                $request->filled('device_id'),
                fn ($query) => $query->where('device_id', $data['device_id'])
            );

        $query = (clone $base)->whereBetween('reading_at', [$start, $end]);

        $statistics = (clone $query)
            ->selectRaw(
                "MIN({$sensor}) AS minimum, MAX({$sensor}) AS maximum, AVG({$sensor}) AS average, COUNT({$sensor}) AS count"
            )
            ->first();

        $readings = $query
            ->orderByDesc('reading_at')
            ->limit(2000)
            ->get()
            ->reverse()
            ->values();

        $latest = (clone $base)
            ->with('crop')
            ->latest('reading_at')
            ->first();

        return response()->json([
            'points' => $readings->map(fn ($r) => [
                'time' => $r->reading_at->toIso8601String(),
                'value' => $r->{$sensor} === null ? null : (float) $r->{$sensor},
                'device_id' => $r->device_id,
            ]),
            'statistics' => $statistics,
            'latest' => $latest?->only([
                ...array_keys(config('agrisense.sensors')),
                'reading_at',
                'crop_id',
            ]),
            'crop' => $latest?->crop?->name,
            'stale' => ! $latest
                || $latest->reading_at->lt(now()->subMinutes(config('agrisense.stale_minutes'))),
            'synced_at' => now()->toIso8601String(),
            'limited' => $readings->count() === 2000,
        ])->header('Cache-Control', 'no-store, private');
    }
}
