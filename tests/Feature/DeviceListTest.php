<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DeviceListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_list_uses_existing_ownership_and_sharing_without_requiring_selection(): void
    {
        $owner = User::factory()->create(['id' => 303]);
        $other = User::factory()->create(['id' => 101]);
        $owned = $owner->devices()->create(['device_id' => 'OWNED', 'name' => 'Owned monitor']);
        $shared = $other->devices()->create(['device_id' => 'SHARED', 'name' => 'Shared monitor']);
        $private = $other->devices()->create(['device_id' => 'PRIVATE', 'name' => 'Private monitor']);
        DB::table('device_user_access')->insert([
            ['user_id' => $owner->id, 'device_id' => $shared->id, 'is_selected' => false, 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $owner->id, 'device_id' => $owned->id, 'is_selected' => false, 'created_at' => now(), 'updated_at' => now()],
        ]);
        $this->actingAs($owner)->get(route('devices.index'))->assertOk()->assertSee('Owned monitor')->assertSee('Shared monitor')->assertDontSee('Private monitor')->assertSee('Add Device')->assertDontSee('data-device-discovery', false);
        $this->getJson(route('devices.index', ['user_id' => $other->id, 'auth_user_id' => '00000000-0000-4000-8000-000000000001']))
            ->assertOk()->assertJsonPath('total', 2)->assertJsonCount(2, 'devices')->assertJsonMissing(['id' => $private->id]);
        $this->assertDatabaseCount('devices', 3);
        $this->assertDatabaseCount('device_user_access', 2);
        $this->actingAs(User::factory()->admin()->create())->getJson(route('devices.index'))->assertOk()->assertJsonPath('total', 3);
    }

    public function test_status_uses_authenticated_contact_and_lists_disabled_and_never_seen_devices(): void
    {
        $this->freezeTime();
        $owner = User::factory()->create();
        $crop = $owner->crops()->create(['name' => 'Tomato', 'scientific_name' => 'Solanum lycopersicum', 'variety' => 'Roma', 'planting_date' => '2026-08-01', 'growth_stage' => 'Flowering', 'location' => 'Plot A', 'status' => 'unknown']);
        $online = $owner->devices()->create(['device_id' => 'ONLINE', 'name' => 'Online monitor', 'crop_id' => $crop->id, 'last_seen_at' => now(), 'status' => 'offline']);
        $online->readings()->create(['reading_at' => now()->subDay(), 'temperature' => 25]);
        $owner->devices()->create(['device_id' => 'STALE', 'name' => 'Stale monitor', 'last_seen_at' => now()->subMinutes(config('agrisense.stale_minutes') + 1), 'status' => 'online']);
        $owner->devices()->create(['device_id' => 'NEVER-SEEN', 'name' => 'Never seen monitor', 'status' => 'online']);
        $owner->devices()->create(['device_id' => 'DISABLED', 'name' => 'Disabled monitor', 'is_active' => false, 'last_seen_at' => now(), 'status' => 'online']);
        $response = $this->actingAs($owner)->getJson(route('devices.index'))->assertOk()->assertJsonCount(4, 'devices');
        $devices = collect($response->json('devices'))->keyBy('device_id');
        $this->assertSame('online', $devices['ONLINE']['status']);
        $this->assertSame(now()->toIso8601String(), $devices['ONLINE']['last_seen_at']);
        $this->assertSame('Tomato', $devices['ONLINE']['crop']);
        foreach (['STALE', 'NEVER-SEEN', 'DISABLED'] as $identifier) {
            $this->assertSame('offline', $devices[$identifier]['status']);
        }
        $this->assertDatabaseCount('sensor_readings', 1);
        $this->assertDatabaseCount('device_user_access', 0);
    }

    public function test_genuine_empty_list_is_distinct_from_database_permission_errors(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->get(route('devices.index'))->assertOk()->assertSee('No devices added yet.');
        $this->getJson(route('devices.index'))->assertOk()->assertJsonPath('total', 0)->assertJsonCount(0, 'devices');
        DB::listen(function (QueryExecuted $query): void {
            if (str_contains($query->sql, '"devices"')) {
                throw new QueryException('sqlite', $query->sql, $query->bindings, new \PDOException('Permission denied', 42501));
            }
        });
        $this->get(route('devices.index'))->assertStatus(503)->assertSee('The database denied access')->assertDontSee('No devices added yet.')->assertDontSee('0 devices available');
        $this->getJson(route('devices.index'))->assertStatus(503)->assertJsonPath('message', 'The database denied access to your devices. Contact the administrator.')->assertJsonMissingPath('devices')->assertJsonMissingPath('total');
    }

    public function test_pagination_includes_existing_records_with_null_timestamps(): void
    {
        $owner = User::factory()->create();
        for ($index = 1; $index <= 13; $index++) {
            $device = $owner->devices()->create(['device_id' => 'EXISTING-'.$index, 'name' => 'Existing '.$index]);
            $device->timestamps = false;
            $device->forceFill(['created_at' => null, 'updated_at' => null])->save();
        }
        $this->actingAs($owner)->getJson(route('devices.index'))->assertOk()->assertJsonPath('total', 13)->assertJsonCount(12, 'devices');
        $this->getJson(route('devices.index', ['page' => 2]))->assertOk()->assertJsonCount(1, 'devices')->assertJsonPath('devices.0.device_id', 'EXISTING-1');
        $this->assertDatabaseCount('devices', 13);
    }

    public function test_connection_failure_shows_an_error_instead_of_an_empty_list(): void
    {
        $owner = User::factory()->create();
        DB::listen(function (QueryExecuted $query): void {
            if (str_contains($query->sql, '"devices"')) {
                throw new QueryException('sqlite', $query->sql, $query->bindings, new \PDOException('Connection lost'));
            }
        });
        $this->actingAs($owner)->getJson(route('devices.index'))->assertStatus(503)
            ->assertJsonPath('message', 'Your devices could not be loaded. Check the database connection and try again.')
            ->assertJsonMissingPath('devices');
        $this->get(route('devices.index'))->assertStatus(503)->assertSee('Devices could not be loaded')->assertDontSee('No devices added yet.');
    }
}
