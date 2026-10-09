<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\User;
use App\Services\DeviceService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_owner_crops_and_registration_are_authorized_and_validated(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $crop = $owner->crops()->create(['name' => 'Owner tomato', 'scientific_name' => 'Solanum lycopersicum', 'variety' => 'Roma', 'planting_date' => '2026-08-01', 'growth_stage' => 'Flowering', 'location' => 'Plot A', 'status' => 'unknown']);
        $privateCrop = $other->crops()->create(['name' => 'Private tomato', 'scientific_name' => 'Solanum lycopersicum', 'variety' => 'Roma', 'planting_date' => '2026-08-01', 'growth_stage' => 'Flowering', 'location' => 'Plot B', 'status' => 'unknown']);
        $this->actingAs(User::factory()->admin()->create());
        $this->getJson(route('devices.owner-crops', ['user_id' => $owner->id]))
            ->assertOk()->assertJsonCount(1, 'crops')->assertJsonPath('crops.0.id', $crop->id);
        $this->get(route('devices.create'))->assertOk()->assertDontSee('Private tomato')->assertDontSee('Owner tomato');
        $payload = ['name' => 'Sensor', 'device_id' => 'ESP-1'];
        $this->postJson(route('devices.store'), $payload)->assertJsonValidationErrors('user_id');
        $this->postJson(route('devices.store'), [...$payload, 'user_id' => $owner->id, 'crop_id' => $privateCrop->id])->assertJsonValidationErrors('crop_id');
        $this->postJson(route('devices.store'), [...$payload, 'user_id' => $owner->id, 'device_id' => 'invalid ID'])->assertJsonValidationErrors('device_id');
        $this->assertDatabaseCount('devices', 0);
        $this->actingAs($owner)->getJson(route('devices.owner-crops', ['user_id' => $other->id]))->assertForbidden();
        $this->postJson(route('devices.store'), [...$payload, 'user_id' => $other->id])->assertForbidden();
    }

    public function test_registration_saves_owner_token_and_private_access_and_rejects_duplicates(): void
    {
        $owner = User::factory()->create();
        $administrator = User::factory()->admin()->create();
        $payload = ['name' => 'Sensor', 'device_id' => 'ESP-1', 'user_id' => $owner->id];
        $this->actingAs($administrator)->postJson(route('devices.store'), $payload)->assertCreated()->assertSessionHas('device_token');
        $device = Device::firstOrFail();
        $this->assertSame($owner->id, $device->user_id);
        $this->assertNotNull($device->token_hash);
        $this->assertDatabaseHas('device_user_access', ['device_id' => $device->id, 'user_id' => $owner->id, 'is_selected' => true]);
        $this->assertDatabaseMissing('device_user_access', ['device_id' => $device->id, 'user_id' => $administrator->id]);
        $this->postJson(route('devices.store'), $payload)->assertJsonValidationErrors('device_id');
        $this->assertDatabaseCount('devices', 1);
        $this->actingAs($owner)->getJson(route('devices.index'))->assertOk()->assertJsonPath('devices.0.id', $device->id);
        $this->get(route('devices.show', $device))->assertOk();
        $this->actingAs(User::factory()->create())->getJson(route('devices.index'))->assertJsonCount(0, 'devices');
        $this->get(route('devices.show', $device))->assertForbidden();
    }

    public function test_token_database_failure_rolls_back_entire_registration(): void
    {
        $owner = User::factory()->create();
        $this->mock(DeviceService::class, function ($mock): void {
            $mock->shouldReceive('rotateToken')->once()->andThrow(new QueryException('sqlite', 'update devices', [], new \PDOException('Connection failed')));
        });
        $this->actingAs(User::factory()->admin()->create())->postJson(route('devices.store'), [
            'name' => 'Sensor', 'device_id' => 'ESP-ROLLBACK', 'user_id' => $owner->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('registration')->assertSessionMissing('success')->assertSessionMissing('device_token');
        $this->assertDatabaseCount('devices', 0);
        $this->assertDatabaseCount('device_user_access', 0);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'SENSOR_CREATED']);
    }
}
