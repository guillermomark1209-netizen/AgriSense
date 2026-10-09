<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\SensorReading;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
    }

    private function device(User $owner, string $identifier): Device
    {
        return $owner->devices()->create([
            'device_id' => $identifier,
            'name' => $identifier,
            'status' => 'offline',
            'is_active' => true,
        ]);
    }

    private function grant(User $user, Device $device, bool $selected = false): void
    {
        DB::table('device_user_access')->insert([
            'user_id' => $user->id,
            'device_id' => $device->id,
            'is_selected' => $selected,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_owner_sees_existing_device_without_discovery_or_an_access_record(): void
    {
        $owner = User::factory()->create();
        $device = $this->device($owner, 'DYNAMIC-ESP-1');
        $privateDevice = $this->device(User::factory()->create(), 'PRIVATE-ESP');

        $this->actingAs($owner)->get('/devices')
            ->assertOk()
            ->assertSee('1 devices available to you')
            ->assertSee('DYNAMIC-ESP-1')->assertDontSee('Device Discovery')->assertDontSee('data-open-device-dialog', false)->assertSee('Add Device');

        $this->getJson(route('devices.index'))
            ->assertOk()
            ->assertJsonCount(1, 'devices')
            ->assertJsonPath('devices.0.id', $device->id)
            ->assertJsonPath('devices.0.device_id', 'DYNAMIC-ESP-1')
            ->assertJsonPath('devices.0.status', 'offline')
            ->assertJsonMissing(['device_id' => 'PRIVATE-ESP']);

        $this->post(route('devices.select', $device))->assertRedirect(route('devices.index'));

        $this->assertSame($owner->id, $device->fresh()->user_id);
        $this->assertDatabaseHas('device_user_access', ['user_id' => $owner->id, 'device_id' => $device->id, 'is_selected' => true]);
        $this->assertDatabaseMissing('device_user_access', ['user_id' => $owner->id, 'device_id' => $privateDevice->id]);
        $this->get(route('devices.index'))->assertOk()->assertSee('1 devices available to you')->assertSee('Selected');
    }

    public function test_user_can_switch_to_a_device_that_was_explicitly_shared_with_them(): void
    {
        $owner = User::factory()->create();
        $user = User::factory()->create(['password' => bcrypt('sensor-test-password')]);
        $first = $this->device($owner, 'SHARED-FIRST');
        $second = $this->device($owner, 'SHARED-SECOND');
        $this->grant($user, $first, true);
        $this->grant($user, $second);

        $this->actingAs($user)->post(route('devices.select', $second))->assertRedirect(route('devices.index'));

        $this->assertSame($owner->id, $second->fresh()->user_id);
        $this->assertDatabaseHas('device_user_access', ['device_id' => $first->id, 'user_id' => $user->id, 'is_selected' => false]);
        $this->assertDatabaseHas('device_user_access', ['device_id' => $second->id, 'user_id' => $user->id, 'is_selected' => true]);
        $this->getJson(route('monitoring.latest'))->assertJsonPath('device.device_id', 'SHARED-SECOND');
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->post(route('login'), ['email' => $user->email, 'password' => 'sensor-test-password'])->assertRedirect(route('dashboard'));
        $this->getJson(route('monitoring.latest'))->assertJsonPath('device.device_id', 'SHARED-SECOND');
    }

    public function test_user_cannot_list_or_claim_another_users_private_device(): void
    {
        $user = User::factory()->create();
        $privateDevice = $this->device(User::factory()->create(), 'PRIVATE-ESP');

        $this->actingAs($user)->getJson(route('devices.index'))
            ->assertOk()->assertJsonCount(0, 'devices');
        $this->post(route('devices.select', $privateDevice))->assertNotFound();
        $this->post(route('devices.access', $privateDevice), ['user_id' => $user->id])->assertForbidden();
        $this->assertDatabaseMissing('device_user_access', ['user_id' => $user->id, 'device_id' => $privateDevice->id]);
        $this->getJson(route('monitoring.latest', ['device_id' => $privateDevice->id]))->assertNotFound();
    }

    public function test_admin_can_grant_device_access_without_changing_its_owner(): void
    {
        $owner = User::factory()->create();
        $recipient = User::factory()->create();
        $administrator = User::factory()->admin()->create();
        $device = $this->device($owner, 'ADMIN-SHARED-ESP');

        $this->actingAs($administrator)->post(route('devices.access', $device), ['user_id' => $recipient->id])
            ->assertRedirect()->assertSessionHas('success', 'Device access granted.');

        $this->assertSame($owner->id, $device->fresh()->user_id);
        $this->assertDatabaseHas('device_user_access', ['user_id' => $recipient->id, 'device_id' => $device->id, 'is_selected' => false]);
        $this->actingAs($recipient)->getJson(route('devices.index'))
            ->assertOk()->assertJsonPath('devices.0.device_id', 'ADMIN-SHARED-ESP');
    }

    public function test_latest_reading_and_history_are_limited_to_the_selected_device(): void
    {
        $user = User::factory()->create();
        $first = $this->device($user, 'FIRST-ESP');
        $second = $this->device($user, 'SECOND-ESP');
        $this->grant($user, $first, true);
        $this->grant($user, $second);
        SensorReading::query()->create(['device_id' => $first->id, 'air_temperature' => 23.2, 'reading_at' => now()->subMinutes(4)]);
        SensorReading::query()->create(['device_id' => $first->id, 'air_temperature' => 25.4, 'reading_at' => now()]);
        SensorReading::query()->create(['device_id' => $second->id, 'air_temperature' => 39.8, 'reading_at' => now()]);

        $this->actingAs($user)->getJson(route('monitoring.latest'))
            ->assertOk()->assertJsonPath('reading.air_temperature', 25.4)->assertJsonPath('device.device_id', 'FIRST-ESP')->assertJsonPath('status', 'online');
        $this->getJson(route('monitoring.data', ['device_id' => $second->id]))->assertNotFound();
        $this->get('/history')->assertOk()->assertSee('FIRST-ESP')->assertDontSee('SECOND-ESP');
    }

    public function test_admin_registration_keeps_owner_and_adds_the_registered_device_to_their_access_list(): void
    {
        $owner = User::factory()->create();
        $administrator = User::factory()->admin()->create();

        $this->actingAs($administrator)->post(route('devices.store'), [
            'name' => 'New ESP32',
            'device_id' => 'NEW-ESP-REGISTRATION',
            'user_id' => $owner->id,
        ])->assertSessionHasNoErrors()->assertSessionHas('device_token');

        $device = Device::query()->where('device_id', 'NEW-ESP-REGISTRATION')->firstOrFail();
        $this->assertSame($owner->id, $device->user_id);
        $this->assertDatabaseHas('device_user_access', ['device_id' => $device->id, 'user_id' => $owner->id, 'is_selected' => true]);
    }
}
