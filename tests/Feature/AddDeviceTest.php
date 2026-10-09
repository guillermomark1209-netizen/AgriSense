<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\User;
use App\Services\DeviceService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddDeviceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    /** @return array{name: string, device_id: string, device_type: string, location: string, description: string} */
    private function payload(): array
    {
        return ['name' => 'Greenhouse monitor', 'device_id' => 'ESP-GREENHOUSE', 'device_type' => 'ESP32', 'location' => 'Greenhouse A', 'description' => 'North bench'];
    }

    public function test_user_can_register_their_device_and_see_it_after_signing_in_again(): void
    {
        $user = User::factory()->create(['password' => bcrypt('test-password-123')]);
        $response = $this->actingAs($user)->postJson(route('devices.register'), $this->payload())->assertCreated();
        $device = Device::findOrFail($response->json('id'));
        $this->assertSame($user->id, $device->user_id);
        $this->assertSame('offline', $device->status);
        $this->assertNull($device->last_seen_at);
        $this->assertSame(hash('sha256', $response->json('device_token')), $device->token_hash);
        $this->assertDatabaseHas('devices', [...$this->payload(), 'user_id' => $user->id]);
        $this->assertDatabaseHas('device_user_access', ['device_id' => $device->id, 'user_id' => $user->id, 'is_selected' => true]);
        $this->getJson(route('devices.index'))->assertOk()->assertJsonPath('total', 1)->assertJsonPath('devices.0.device_type', 'ESP32')->assertJsonPath('devices.0.location', 'Greenhouse A')->assertJsonPath('devices.0.status', 'offline');
        $this->post(route('logout'))->assertRedirect();
        $this->post(route('login'), ['email' => $user->email, 'password' => 'test-password-123'])->assertRedirect();
        $this->get(route('devices.index'))->assertOk()->assertSee('Greenhouse monitor')->assertSee('Greenhouse A')->assertSee('ESP32')->assertDontSee('Refresh devices');
        $this->actingAs(User::factory()->create())->getJson(route('devices.index'))->assertOk()->assertJsonPath('total', 0);
        $this->assertDatabaseCount('sensor_readings', 0);
    }

    public function test_registration_rejects_duplicates_invalid_fields_and_forged_ownership(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($user);
        $this->postJson(route('devices.register'), [])->assertJsonValidationErrors(['name', 'device_id', 'device_type', 'location']);
        $this->postJson(route('devices.register'), [...$this->payload(), 'device_type' => 'Unknown', 'device_id' => 'invalid id'])->assertJsonValidationErrors(['device_type', 'device_id']);
        $this->postJson(route('devices.register'), [...$this->payload(), 'user_id' => $other->id, 'status' => 'online', 'auth_user_id' => '00000000-0000-4000-8000-000000000001'])->assertJsonValidationErrors(['user_id', 'status', 'auth_user_id']);
        $this->assertDatabaseCount('devices', 0);
        $this->postJson(route('devices.register'), $this->payload())->assertCreated();
        $this->postJson(route('devices.register'), $this->payload())->assertJsonValidationErrors('device_id');
        $this->assertDatabaseCount('devices', 1);
    }

    public function test_admin_modal_also_assigns_the_device_to_the_authenticated_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->postJson(route('devices.register'), $this->payload())->assertCreated();
        $this->assertSame($admin->id, Device::firstOrFail()->user_id);
    }

    public function test_database_failure_rolls_back_device_and_access_and_reports_failure(): void
    {
        $this->mock(DeviceService::class, function ($mock): void {
            $mock->shouldReceive('rotateToken')->once()->andThrow(new QueryException('sqlite', 'update devices', [], new \PDOException('Connection failed')));
        });
        $this->actingAs(User::factory()->create())->postJson(route('devices.register'), $this->payload())->assertUnprocessable()->assertJsonValidationErrors('registration');
        $this->assertDatabaseCount('devices', 0);
        $this->assertDatabaseCount('device_user_access', 0);
    }

    public function test_guest_cannot_register_a_device(): void
    {
        $this->postJson(route('devices.register'), $this->payload())->assertUnauthorized();
        $this->assertDatabaseCount('devices', 0);
    }
}
