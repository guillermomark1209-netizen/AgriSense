<?php

return [
    'subtitle' => 'Smart Agriculture Monitoring & AI Assistance',
    'stale_minutes' => 10,
    'supabase_url' => env('SUPABASE_URL'),
    'supabase_key' => env('SUPABASE_SERVICE_ROLE_KEY'),
    'supabase_publishable_key' => env('SUPABASE_PUBLISHABLE_KEY'),
    'supabase_sensor_key' => env('SUPABASE_SENSOR_SECRET_KEY') ?: env('SUPABASE_SERVICE_ROLE_KEY'),
    'supabase_sensor_device_id' => env('SUPABASE_SENSOR_DEVICE_ID'),
    'supabase_sensor_device_identifier' => env('SUPABASE_SENSOR_DEVICE_IDENTIFIER'),
    'supabase_sensor_crop_id' => env('SUPABASE_SENSOR_CROP_ID'),
    'supabase_public_key' => env('SUPABASE_ANON_KEY'),
    'realtime_secret' => env('SUPABASE_JWT_SECRET'),
    'realtime_enabled' => env('SUPABASE_REALTIME_ENABLED', false),
    'sensors' => [
        'soil_temperature' => ['label' => 'Soil temperature', 'unit' => '°C', 'icon' => 'thermometer'],
        'air_temperature' => ['label' => 'Air temperature', 'unit' => '°C', 'icon' => 'thermometer'],
        'air_humidity' => ['label' => 'Air humidity', 'unit' => '%', 'icon' => 'cloud-rain'],
        'light_percent' => ['label' => 'Light', 'unit' => '%', 'icon' => 'sun'],
        'light_intensity' => ['label' => 'Light intensity', 'unit' => 'lux', 'icon' => 'sun'],
        'soil_moisture' => ['label' => 'Soil moisture', 'unit' => '%', 'icon' => 'droplets'],
        'soil_ph' => ['label' => 'Soil pH', 'unit' => 'pH', 'icon' => 'flask-conical'],
    ],
    'reading_fields' => [
        'temperature', 'humidity', 'soil_moisture', 'soil_ph', 'light_intensity',
        'soil_temperature', 'air_temperature', 'air_humidity', 'light_percent',
        'soil_raw', 'ldr_raw', 'ph_raw', 'pump',
    ],
];
