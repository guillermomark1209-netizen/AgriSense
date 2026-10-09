<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardSensorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_owned_esp32_readings_display_without_a_selected_access_record_and_refresh_to_new_data(): void
    {
        $owner = User::factory()->create();
        $device = $owner->devices()->create(['name' => 'ESP32 monitor', 'device_id' => 'ESP32-DASHBOARD', 'is_active' => true]);
        $device->readings()->create(['reading_at' => now()->subMinute(), 'temperature' => 24.5, 'humidity' => 83.3, 'soil_moisture' => 100, 'pump' => false]);
        $this->actingAs($owner)->get(route('dashboard'))->assertOk()->assertSee('24.5')->assertSee('83.3')->assertSee('data-refresh-interval="10000"', false);
        $this->getJson(route('monitoring.latest'))->assertOk()->assertJsonPath('device.id', $device->id)->assertJsonPath('reading.temperature', 24.5)->assertJsonPath('reading.humidity', 83.3)->assertJsonPath('reading.pump', false)->assertJsonPath('active_devices', 1);
        $device->readings()->create(['reading_at' => now(), 'air_temperature' => 27.1, 'air_humidity' => 79.2, 'pump' => true]);
        $this->getJson(route('monitoring.latest'))->assertOk()->assertJsonPath('reading.air_temperature', 27.1)->assertJsonPath('reading.pump', true);
        $this->assertDatabaseCount('device_user_access', 0);
    }

    public function test_dashboard_does_not_expose_another_users_readings_and_empty_dashboard_keeps_polling(): void
    {
        $owner = User::factory()->create();
        $device = $owner->devices()->create(['name' => 'Private ESP32', 'device_id' => 'PRIVATE-DASHBOARD']);
        $device->readings()->create(['reading_at' => now(), 'air_temperature' => 28.7]);
        $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertOk()->assertDontSee('28.7')->assertSee('data-sensor-live', false);
        $this->getJson(route('monitoring.latest'))->assertOk()->assertJsonPath('device', null)->assertJsonPath('reading', null)->assertJsonPath('active_devices', 0);
        $this->getJson(route('monitoring.latest', ['device_id' => $device->id]))->assertNotFound();
    }

    public function test_explicit_selection_takes_priority_over_other_owned_readings(): void
    {
        $owner = User::factory()->create();
        $selected = $owner->devices()->create(['name' => 'Selected', 'device_id' => 'SELECTED-DASHBOARD']);
        $other = $owner->devices()->create(['name' => 'Other', 'device_id' => 'OTHER-DASHBOARD']);
        $selected->readings()->create(['reading_at' => now()->subMinute(), 'air_temperature' => 22.2]);
        $other->readings()->create(['reading_at' => now(), 'air_temperature' => 35.5]);
        DB::table('device_user_access')->insert(['user_id' => $owner->id, 'device_id' => $selected->id, 'is_selected' => true, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($owner)->getJson(route('monitoring.latest'))->assertOk()->assertJsonPath('device.id', $selected->id)->assertJsonPath('reading.air_temperature', 22.2);
    }
}
