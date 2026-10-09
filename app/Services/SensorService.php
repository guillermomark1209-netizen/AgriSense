<?php

namespace App\Services;

use App\Models\Device;
use App\Models\SensorReading;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SensorService
{
    public function storeReading(Device $device, array $payload): SensorReading
    {
        $readingAt = Carbon::parse($payload['reading_at'])->utc();

        return DB::transaction(function () use ($device, $payload, $readingAt) {
            $device = Device::whereKey($device->id)->lockForUpdate()->firstOrFail();
            abort_unless($device->is_active && $device->crop && $device->crop->user_id === $device->user_id, 409, 'Assign this active device to one of your crops.');
            $existing = $device->readings()->where('reading_id', $payload['reading_id'])->first();
            if ($existing) {
                foreach (config('agrisense.reading_fields') as $sensor) {
                    $incoming = $payload[$sensor] ?? null;
                    abort_if(($existing->{$sensor} === null) !== ($incoming === null) || ($incoming !== null && round((float) $existing->{$sensor}, 2) !== round((float) $incoming, 2)), 409, 'A reading UUID cannot be reused for different measurements.');
                }
                abort_unless($existing->reading_at->equalTo($payload['reading_at']), 409, 'A reading UUID cannot be reused with a different timestamp.');

                return $existing;
            }
            $reading = $device->readings()->create([
                ...collect($payload)->except('device_id')->all(),
                'crop_id' => $device->crop_id,
                'reading_at' => $readingAt,
            ]);
            $device->update(['status' => 'online', 'last_seen_at' => now()]);
            $isLatest = ! $device->readings()->where('reading_at', '>', $reading->reading_at)->exists();
            if ($isLatest && $reading->reading_at->gte(now()->subMinutes(config('agrisense.stale_minutes')))) {
                app(AlertService::class)->evaluate($reading);
            }

            return $reading;
        });
    }
}
