<?php

return [
    'subtitle' => 'Smart Agriculture Monitoring & AI Assistance',
    'stale_minutes' => 10,
    'supabase_url' => env('SUPABASE_URL'),
    'supabase_key' => env('SUPABASE_SERVICE_ROLE_KEY'),
    'supabase_public_key' => env('SUPABASE_ANON_KEY'),
    'realtime_secret' => env('SUPABASE_JWT_SECRET'),
    'realtime_enabled' => env('SUPABASE_REALTIME_ENABLED', false),
    'sensors' => [
        'temperature' => ['label' => 'Temperature', 'unit' => '°C', 'icon' => 'thermometer'],
        'humidity' => ['label' => 'Humidity', 'unit' => '%', 'icon' => 'cloud-rain'],
        'soil_moisture' => ['label' => 'Soil moisture', 'unit' => '%', 'icon' => 'droplets'],
        'soil_ph' => ['label' => 'Soil pH', 'unit' => 'pH', 'icon' => 'flask-conical'],
        'light_intensity' => ['label' => 'Light intensity', 'unit' => 'lux', 'icon' => 'sun'],
    ],
];
