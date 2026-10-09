<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class SupabaseSensorReadingService
{
    /** @return array<int, array<string, mixed>> */
    public function readings(string $from, string $to, int $limit = 2000): array
    {
        $url = config('agrisense.supabase_url');
        $key = config('agrisense.supabase_sensor_key');
        $deviceId = config('agrisense.supabase_sensor_device_id');

        if (! $url || ! $key || ! $deviceId) {
            throw new RuntimeException('Supabase sensor URL, server key, or device ID is not configured.');
        }

        $filters = [
            'select' => 'id,device_id,crop_id,reading_at,temperature,humidity,soil_moisture,soil_ph,light_intensity,soil_temperature,air_temperature,air_humidity,light_percent,soil_raw,ldr_raw,ph_raw,pump,reading_id,created_at,updated_at',
            'device_id' => 'eq.'.$deviceId,
            'reading_at' => 'gte.'.$from,
            'and' => '(reading_at.lte.'.$to.')',
            'order' => 'reading_at.desc',
            'limit' => $limit,
        ];

        if (config('agrisense.supabase_sensor_crop_id')) {
            $filters['crop_id'] = 'eq.'.config('agrisense.supabase_sensor_crop_id');
        }

        return Http::baseUrl(rtrim($url, '/').'/rest/v1')
            ->acceptJson()
            ->withHeaders(['apikey' => $key])
            ->connectTimeout(5)
            ->timeout(10)
            ->get('/sensor_readings', $filters)
            ->throw()
            ->json();
    }
}
