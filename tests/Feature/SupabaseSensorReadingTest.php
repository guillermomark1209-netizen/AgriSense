<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SupabaseSensorReadingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Http::preventStrayRequests();
        config([
            'agrisense.supabase_url' => 'https://example.supabase.co',
            'agrisense.supabase_sensor_key' => 'test-server-secret-key',
            'agrisense.supabase_sensor_device_id' => 3,
            'agrisense.supabase_sensor_device_identifier' => 'agrisense-esp32-001',
            'agrisense.supabase_sensor_crop_id' => 2,
        ]);
    }

    private function assignedDevice(User $user): Device
    {
        $crop = $user->crops()->create([
            'name' => 'Lettuce',
            'scientific_name' => 'Lactuca sativa',
            'variety' => 'Green Leaf',
            'planting_date' => '2026-08-01',
            'growth_stage' => 'Vegetative',
            'location' => 'Plot A',
            'status' => 'unknown',
        ]);

        return $user->devices()->create([
            'crop_id' => $crop->id,
            'device_id' => 'agrisense-esp32-001',
            'name' => 'ESP32',
            'status' => 'offline',
            'is_active' => true,
        ]);
    }

    public function test_owner_can_poll_latest_supabase_reading_without_receiving_server_key(): void
    {
        $device = $this->assignedDevice($user = User::factory()->create());
        Http::fake(['https://example.supabase.co/rest/v1/sensor_readings*' => Http::response([[
            'device_id' => 3,
            'reading_at' => now()->toIso8601String(),
            'soil_moisture' => 41,
            'soil_temperature' => 22.4,
            'air_temperature' => 27.2,
            'air_humidity' => 68,
            'light_intensity' => 720,
            'light_percent' => 53,
            'soil_ph' => 6.4,
            'pump' => false,
        ]])]);

        $response = $this->actingAs($user)->getJson(route('monitoring.latest', ['device_id' => $device->id]));

        $response->assertOk()
            ->assertJsonPath('reading.soil_moisture', 41)
            ->assertJsonPath('reading.soil_temperature', 22.4)
            ->assertJsonPath('reading.air_temperature', 27.2)
            ->assertJsonPath('reading.air_humidity', 68)
            ->assertJsonPath('reading.pump', false)
            ->assertJsonPath('device_online', true)
            ->assertJsonPath('active_devices', 1)
            ->assertJsonMissing(['test-server-secret-key']);
        $this->assertTrue($device->fresh()->online);
        Http::assertSent(fn ($request) => $request->hasHeader('apikey', 'test-server-secret-key')
            && ! $request->hasHeader('Authorization')
            && str_contains($request->url(), 'device_id=eq.3')
            && str_contains($request->url(), 'crop_id=eq.2')
            && str_contains($request->url(), 'order=reading_at.desc'));
    }

    public function test_dashboard_renders_latest_supabase_sensor_values_and_pump_state(): void
    {
        $user = User::factory()->create();
        $this->assignedDevice($user);
        Http::fake(['https://example.supabase.co/rest/v1/sensor_readings*' => Http::response([[
            'device_id' => 3,
            'reading_at' => now()->toIso8601String(),
            'soil_moisture' => 41,
            'soil_temperature' => 22.4,
            'air_temperature' => 27.2,
            'air_humidity' => 68,
            'light_intensity' => 720,
            'light_percent' => 53,
            'soil_ph' => 6.4,
            'pump' => true,
        ]])]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('27.2')
            ->assertSee('68.0')
            ->assertSee('53.0')
            ->assertSee('720.0')
            ->assertSee('Pump: <strong data-pump-status>On</strong>', false);
    }

    public function test_device_list_uses_supabase_observation_time_for_online_status(): void
    {
        $user = User::factory()->create();
        $this->assignedDevice($user);
        Http::fake(['https://example.supabase.co/rest/v1/sensor_readings*' => Http::response([[
            'device_id' => 3,
            'reading_at' => now()->toIso8601String(),
        ]])]);

        $this->actingAs($user)
            ->get('/devices')
            ->assertOk()
            ->assertSee('Online')
            ->assertSee('Last contact:');
    }

    public function test_farmer_cannot_poll_another_owners_device(): void
    {
        $device = $this->assignedDevice(User::factory()->create());
        Http::fake();

        $this->actingAs(User::factory()->create())
            ->getJson(route('monitoring.latest', ['device_id' => $device->id]))
            ->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_historical_supabase_readings_are_sorted_and_use_actual_sensor_values(): void
    {
        $device = $this->assignedDevice($user = User::factory()->create());
        $older = now()->subHours(2)->toIso8601String();
        $newer = now()->subHour()->toIso8601String();
        Http::fake(['https://example.supabase.co/rest/v1/sensor_readings*' => Http::response([
            ['device_id' => 3, 'reading_at' => $newer, 'soil_moisture' => 48],
            ['device_id' => 3, 'reading_at' => $older, 'soil_moisture' => 43],
        ])]);

        $response = $this->actingAs($user)->getJson(route('monitoring.data', [
            'device_id' => $device->id,
            'sensor' => 'soil_moisture',
            'range' => '24h',
        ]));

        $response->assertOk()
            ->assertJsonCount(2, 'points')
            ->assertJsonPath('points.0.value', 43)
            ->assertJsonPath('points.1.value', 48)
            ->assertJsonPath('statistics.average', 45.5);
    }

    public function test_supabase_errors_return_unavailable_state_without_fabricated_readings(): void
    {
        $device = $this->assignedDevice($user = User::factory()->create());
        Http::fake(['https://example.supabase.co/rest/v1/sensor_readings*' => Http::response(['message' => 'denied'], 500)]);

        $this->actingAs($user)
            ->getJson(route('monitoring.latest', ['device_id' => $device->id]))
            ->assertServiceUnavailable()
            ->assertJsonPath('message', 'Supabase rejected the sensor read.')
            ->assertJsonPath('upstream_status', 500);
    }

    public function test_dashboard_explains_when_the_server_side_supabase_key_is_missing(): void
    {
        $device = $this->assignedDevice($user = User::factory()->create());
        config(['agrisense.supabase_sensor_key' => null]);

        $this->actingAs($user)
            ->getJson(route('monitoring.latest', ['device_id' => $device->id]))
            ->assertServiceUnavailable()
            ->assertJsonPath('configured', false)
            ->assertJsonPath('message', 'Add SUPABASE_SENSOR_SECRET_KEY to .env, then clear Laravel config cache.');

        Http::assertNothingSent();
    }

    public function test_empty_supabase_result_is_reported_as_stale_with_no_reading(): void
    {
        $device = $this->assignedDevice($user = User::factory()->create());
        Http::fake(['https://example.supabase.co/rest/v1/sensor_readings*' => Http::response([])]);

        $this->actingAs($user)
            ->getJson(route('monitoring.latest', ['device_id' => $device->id]))
            ->assertOk()
            ->assertJsonPath('reading', null)
            ->assertJsonPath('stale', true);
    }
}
