<?php

namespace App\Http\Controllers;

use App\Models\Crop;
use App\Models\Device;
use App\Models\SensorReading;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MonitoringController extends Controller
{
    public function index(Request $request): View
    {
        return view('monitoring.index', [
            'crops' => Crop::query()->when(! $request->user()->isAdmin(), fn ($query) => $query->where('user_id', $request->user()->id))->get(),
            'devices' => Device::query()->when(! $request->user()->isAdmin(), fn ($query) => $query->where('user_id', $request->user()->id))->get(),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $data = $request->validate([
            'crop_id' => ['nullable', Rule::exists('crops', 'id')->when(! $request->user()->isAdmin(), fn ($rule) => $rule->where('user_id', $request->user()->id))],
            'device_id' => ['nullable', Rule::exists('devices', 'id')->when(! $request->user()->isAdmin(), fn ($rule) => $rule->where('user_id', $request->user()->id))],
            'sensor' => ['sometimes', Rule::in(array_keys(config('agrisense.sensors')))],
            'range' => ['sometimes', Rule::in(['24h', '7d', '30d', 'custom'])],
            'from' => ['required_if:range,custom', 'nullable', 'date'],
            'to' => ['required_if:range,custom', 'nullable', 'date', 'after_or_equal:from'],
        ]);
        $sensor = $data['sensor'] ?? 'temperature';
        $range = $data['range'] ?? '24h';
        $start = match ($range) {
            '7d' => now()->subDays(7),'30d' => now()->subDays(30),'custom' => Carbon::parse($data['from'])->startOfDay(),default => now()->subDay()
        };
        $end = $range === 'custom' ? Carbon::parse($data['to'])->endOfDay() : now();
        abort_if($start->diffInDays($end) > 366, 422, 'Choose a period of at most one year.');
        $base = SensorReading::query()->when(! $request->user()->isAdmin(), fn ($query) => $query->whereHas('device', fn ($q) => $q->where('user_id', $request->user()->id)))
            ->when($request->filled('crop_id'), fn ($q) => $q->where('crop_id', $data['crop_id']))
            ->when($request->filled('device_id'), fn ($q) => $q->where('device_id', $data['device_id']));
        $query = (clone $base)->whereBetween('reading_at', [$start, $end]);
        $statistics = (clone $query)->selectRaw("MIN({$sensor}) AS minimum, MAX({$sensor}) AS maximum, AVG({$sensor}) AS average, COUNT({$sensor}) AS count")->first();
        $readings = $query->orderByDesc('reading_at')->limit(2000)->get()->reverse()->values();
        $latest = (clone $base)->with('crop')->latest('reading_at')->first();

        return response()->json([
            'points' => $readings->map(fn ($r) => ['time' => $r->reading_at->toIso8601String(), 'value' => $r->{$sensor} === null ? null : (float) $r->{$sensor}, 'device_id' => $r->device_id]),
            'statistics' => $statistics, 'latest' => $latest?->only([...array_keys(config('agrisense.sensors')), 'reading_at', 'crop_id']),
            'crop' => $latest?->crop?->name, 'stale' => ! $latest || $latest->reading_at->lt(now()->subMinutes(config('agrisense.stale_minutes'))),
            'synced_at' => now()->toIso8601String(), 'limited' => $readings->count() === 2000,
        ])->header('Cache-Control', 'no-store, private');
    }
}
