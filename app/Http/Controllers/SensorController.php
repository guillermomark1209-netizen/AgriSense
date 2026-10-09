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
        $token = $request->bearerToken();
        abort_unless($token, 401, 'Invalid device credentials.');

        $device = Device::where('token_hash', hash('sha256', $token))
            ->where('device_id', $request->validated('device_id'))
            ->where('is_active', true)
            ->first();

        abort_unless($device, 401, 'Invalid device credentials.');
        $reading = $service->storeReading($device, $request->validated());

        return response()->json(['id' => $reading->id, 'reading_id' => $reading->reading_id, 'reading_at' => $reading->reading_at, 'duplicate' => ! $reading->wasRecentlyCreated], $reading->wasRecentlyCreated ? 201 : 200);
    }
}
