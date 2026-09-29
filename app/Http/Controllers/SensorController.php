<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReadingRequest;
use App\Models\Device;
use App\Services\SensorService;
use Illuminate\Http\JsonResponse;

class SensorController extends Controller
{
    public function store(ReadingRequest $request, SensorService $service): JsonResponse
    {
        $device = Device::where('device_id', $request->validated('device_id'))->first();
        abort_unless($device && $device->token_hash && $request->bearerToken() && hash_equals($device->token_hash, hash('sha256', $request->bearerToken())), 401, 'Invalid device credentials.');
        $reading = $service->storeReading($device, $request->validated());

        return response()->json(['id' => $reading->id, 'reading_id' => $reading->reading_id, 'reading_at' => $reading->reading_at, 'duplicate' => ! $reading->wasRecentlyCreated], $reading->wasRecentlyCreated ? 201 : 200);
    }
}
