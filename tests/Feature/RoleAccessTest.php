<?php

namespace Tests\Feature;

use App\Mail\AuthMessage;
use App\Models\Crop;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Http::preventStrayRequests();
    }

    private function crop(User $user): Crop
    {
        return $user->crops()->create(['name' => 'Tomato', 'scientific_name' => 'Solanum lycopersicum', 'variety' => 'Roma', 'planting_date' => '2026-08-01', 'growth_stage' => 'Flowering', 'location' => 'Plot A', 'status' => 'unknown']);
    }

    public function test_guests_must_log_in_and_users_cannot_reach_admin_pages(): void
    {
        $this->get('/admin/dashboard')->assertRedirect('/login');
        $this->actingAs(User::factory()->create());
        foreach (['dashboard', 'users', 'users/create', 'crops', 'devices', 'sensor-data', 'settings', 'audit-logs', 'documents', 'sources'] as $path) {
            $this->get('/admin/'.$path)->assertForbidden();
        }
        $this->post('/admin/users', ['role' => 'admin'])->assertForbidden();
        $this->post('/admin/settings', ['farm_notice' => 'Unwanted change'])->assertForbidden();
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertDatabaseCount('system_settings', 0);
    }

    public function test_public_registration_and_profile_cannot_promote_accounts(): void
    {
        Mail::fake();
        $this->post('/register', ['name' => 'Normal user', 'email' => 'normal@example.test', 'password' => 'long-test-password', 'password_confirmation' => 'long-test-password', 'role' => 'admin'])->assertRedirect('/verify-email');
        $user = User::where('email', 'normal@example.test')->firstOrFail();
        $this->assertSame('user', $user->role);
        $code = Mail::sent(AuthMessage::class)->first()->secret;
        $this->post(route('verification.verify'), ['otp' => $code])->assertRedirect(route('verification.success'));
        $this->post('/profile', ['name' => 'Changed', 'email' => $user->email, 'role' => 'admin', 'user_id' => 999])->assertSessionHasNoErrors();
        $this->assertSame('user', $user->fresh()->role);
        $this->assertSame('user', (new User(['role' => 'admin']))->role);
    }

    public function test_users_can_edit_their_own_crops_but_cannot_manage_devices_or_delete_crops(): void
    {
        $user = User::factory()->create();
        $crop = $this->crop($user);
        $device = $user->devices()->create(['crop_id' => $crop->id, 'device_id' => 'RBAC-1', 'name' => 'Test device', 'status' => 'offline', 'is_active' => true]);
        $this->actingAs($user);
        $this->get('/crops/'.$crop->id)->assertOk()->assertSee('Edit crop')->assertDontSee('Delete crop');
        $this->get('/devices/'.$device->id)->assertOk()->assertDontSee('Edit device')->assertDontSee('Replace device token');
        $this->get('/dashboard')->assertOk()->assertSee('Add a crop')->assertDontSee('ADMIN TOOLS');
        $this->get('/crops/'.$crop->id.'/edit')->assertOk()->assertSee('Edit crop');
        foreach (['/devices/create', '/devices/'.$device->id.'/edit'] as $url) {
            $this->get($url)->assertForbidden();
        }
        foreach (['/devices', '/devices/'.$device->id.'/token', '/crops/'.$crop->id.'/thresholds'] as $url) {
            $this->post($url)->assertForbidden();
        }
        $cropData = [...$crop->only('name', 'scientific_name', 'variety', 'planting_date', 'growth_stage', 'location', 'status'), 'name' => 'Updated tomato'];
        $this->put('/crops/'.$crop->id, $cropData)->assertSessionHasNoErrors()->assertRedirect(route('crops.show', $crop));
        $this->assertSame('Updated tomato', $crop->fresh()->name);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $user->id, 'action' => 'PLANT_UPDATED', 'record_id' => (string) $crop->id]);
        $this->delete('/crops/'.$crop->id)->assertForbidden();
        $this->put('/devices/'.$device->id, ['name' => 'Unauthorized'])->assertForbidden();
        $this->delete('/devices/'.$device->id)->assertForbidden();
        $this->assertTrue((bool) $device->fresh()->is_active);
    }

    public function test_users_cannot_edit_another_users_crop(): void
    {
        $owner = User::factory()->create();
        $crop = $this->crop($owner);
        $otherUser = User::factory()->create();
        $cropData = [...$crop->only('name', 'scientific_name', 'variety', 'planting_date', 'growth_stage', 'location', 'status'), 'name' => 'Unauthorized update'];

        $this->actingAs($otherUser)->get('/crops/'.$crop->id.'/edit')->assertForbidden();
        $this->put('/crops/'.$crop->id, $cropData)->assertForbidden();
        $this->assertSame('Tomato', $crop->fresh()->name);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_admin_can_manage_another_users_crop_and_device_with_audits(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $crop = $this->crop($owner);
        $this->actingAs($admin);
        $this->get('/crops/create')->assertOk()->assertSee('Crop owner');
        $this->get('/devices/create')->assertOk()->assertSee('Device owner');
        $this->get('/admin/ai-audit-logs')->assertOk();
        $this->get('/crops')->assertOk()->assertSee('Tomato');
        $this->put('/crops/'.$crop->id, [...$crop->only('name', 'scientific_name', 'variety', 'planting_date', 'growth_stage', 'location', 'status'), 'name' => 'Updated tomato'])->assertSessionHasNoErrors();
        $this->assertSame('Updated tomato', $crop->fresh()->name);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $admin->id, 'action' => 'PLANT_UPDATED', 'record_id' => (string) $crop->id]);
        $this->post('/devices', ['name' => 'Test sensor', 'device_id' => 'ADMIN-SENSOR', 'user_id' => $owner->id, 'crop_id' => $crop->id])->assertSessionHasNoErrors();
        $device = $owner->devices()->firstOrFail();
        $this->assertDatabaseHas('audit_logs', ['action' => 'SENSOR_CREATED', 'record_id' => (string) $device->id]);
        $this->post('/devices/'.$device->id.'/token')->assertSessionHas('device_token');
        $this->delete('/devices/'.$device->id)->assertRedirect();
        $this->assertNull($device->fresh()->token_hash);
        $this->assertDatabaseHas('audit_logs', ['action' => 'SENSOR_DELETED']);
        $this->get('/admin/audit-logs')->assertOk()->assertSee('PLANT_UPDATED');
    }

    public function test_admin_created_crops_keep_the_selected_owner(): void
    {
        $owner = User::factory()->create();
        $crop = $this->crop($owner);
        $this->actingAs(User::factory()->admin()->create())->post('/crops', [...$crop->only('name', 'scientific_name', 'variety', 'planting_date', 'growth_stage', 'location', 'status'), 'user_id' => $owner->id, 'name' => 'Assigned crop'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('crops', ['user_id' => $owner->id, 'name' => 'Assigned crop']);
    }

    public function test_normal_users_can_add_their_own_crops_with_a_five_mb_photo(): void
    {
        config(['agrisense.supabase_url' => null, 'agrisense.supabase_key' => null]);
        Storage::fake('local');
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->get('/crops/create')->assertRedirect('/login');
        $this->post('/crops')->assertRedirect('/login');
        $this->actingAs($user)->get('/crops/create')->assertOk()->assertDontSee('Crop owner')->assertDontSee($other->email);
        $this->get('/crops')->assertOk()->assertSee('Add crop');
        $photo = UploadedFile::fake()->image('tomato.png')->size(5120);
        $response = $this->post('/crops', [
            'name' => 'Tomato', 'scientific_name' => 'Solanum lycopersicum', 'variety' => 'Roma',
            'planting_date' => '2026-08-01', 'growth_stage' => 'Flowering', 'location' => 'Plot A',
            'status' => 'unknown', 'image' => $photo,
        ])->assertSessionHasNoErrors();
        $crop = $user->crops()->firstOrFail();
        $response->assertRedirect(route('crops.show', $crop));
        $this->assertSame($user->id, $crop->user_id);
        Storage::disk('local')->assertExists($crop->image_url);
        $this->get(route('crops.image', $crop))->assertOk()->assertStreamedContent($photo->getContent());
        $this->assertDatabaseHas('audit_logs', ['action' => 'PLANT_CREATED', 'user_id' => $user->id, 'record_id' => (string) $crop->id]);
        $this->actingAs($other)->get(route('crops.show', $crop))->assertForbidden();
        $this->get(route('crops.image', $crop))->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_normal_users_cannot_assign_new_crops_to_other_accounts(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($user)->post('/crops', [
            'name' => 'Tomato', 'scientific_name' => 'Solanum lycopersicum', 'variety' => 'Roma',
            'planting_date' => '2026-08-01', 'growth_stage' => 'Flowering', 'location' => 'Plot A',
            'status' => 'unknown', 'user_id' => $other->id,
        ])->assertSessionHasErrors('user_id');
        $this->assertDatabaseCount('crops', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_role_changes_are_admin_only_audited_and_effective_immediately(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();
        $this->actingAs($target)->patch('/admin/users/'.$target->id.'/role', ['role' => 'admin'])->assertForbidden();
        $this->actingAs($admin)->patch('/admin/users/'.$target->id.'/role', ['role' => 'owner'])->assertSessionHasErrors('role');
        $this->patch('/admin/users/'.$admin->id.'/role', ['role' => 'user'])->assertStatus(422);
        $this->patch('/admin/users/'.$target->id.'/role', ['role' => 'admin'])->assertSessionHasNoErrors();
        $this->assertSame('admin', $target->fresh()->role);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $admin->id, 'action' => 'USER_ROLE_CHANGED', 'record_id' => (string) $target->id]);
        $this->actingAs($target->fresh())->get('/admin/dashboard')->assertOk();
        $this->actingAs($admin)->patch('/admin/users/'.$target->id.'/role', ['role' => 'user'])->assertSessionHasNoErrors();
        $this->actingAs($target->fresh())->get('/admin/dashboard')->assertForbidden();
    }

    public function test_legacy_role_rows_no_longer_grant_administrator_access(): void
    {
        $user = User::factory()->create();
        $user->roles()->create(['role' => 'admin']);
        $this->assertFalse($user->hasRole('admin'));
        $this->actingAs($user)->get('/admin/dashboard')->assertForbidden();
    }

    public function test_admin_user_management_preserves_owned_farm_history(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/admin/users/create')->assertOk();
        $this->post('/admin/users', ['name' => 'Managed user', 'email' => 'managed@example.test', 'password' => 'long-managed-password', 'password_confirmation' => 'long-managed-password', 'role' => 'user'])->assertSessionHasNoErrors();
        $target = User::where('email', 'managed@example.test')->firstOrFail();
        $this->get('/admin/users/'.$target->id.'/edit')->assertOk();
        $this->put('/admin/users/'.$target->id, ['name' => 'Updated user', 'email' => $target->email, 'role' => 'admin'])->assertSessionHasNoErrors();
        $this->assertSame('user', $target->fresh()->role);
        $this->delete('/admin/users/'.$target->id)->assertRedirect();
        $this->assertDatabaseMissing('users', ['id' => $target->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'USER_DELETED', 'record_id' => (string) $target->id]);
        $owner = User::factory()->create();
        $crop = $this->crop($owner);
        $this->delete('/admin/users/'.$owner->id)->assertStatus(422);
        $this->assertDatabaseHas('crops', ['id' => $crop->id]);
        $this->delete('/admin/users/'.$admin->id)->assertStatus(422);
    }

    public function test_system_changes_are_audited_without_logging_secrets(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post('/admin/settings', ['farm_notice' => 'New notice', 'password' => 'must-not-be-logged'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('system_settings', ['key' => 'farm_notice', 'value' => 'New notice']);
        $audit = DB::table('audit_logs')->where('action', 'SYSTEM_SETTINGS_CHANGED')->first();
        $this->assertSame($admin->id, $audit->user_id);
        $this->assertSame('127.0.0.1', $audit->ip_address);
        $this->assertStringNotContainsString('must-not-be-logged', $audit->description);
    }

    public function test_role_migration_preserves_users_and_legacy_admins_and_can_be_repeated(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->create(['role' => 'admin']);
        $user = User::factory()->create();
        $crop = $this->crop($user);
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('role'));
        $migration = require database_path('migrations/2026_09_17_074940_add_role_to_users_table.php');
        $migration->up();
        $this->assertSame('admin', $admin->fresh()->role);
        $this->assertSame('user', $user->fresh()->role);
        $this->assertDatabaseHas('crops', ['id' => $crop->id, 'user_id' => $user->id]);
        DB::table('users')->where('id', $admin->id)->update(['role' => 'user']);
        $migration->up();
        $this->assertSame('user', $admin->fresh()->role);
        $this->assertDatabaseCount('users', 2);
    }

    public function test_database_rejects_unknown_role_values(): void
    {
        $user = User::factory()->create();
        $this->expectException(QueryException::class);
        DB::table('users')->where('id', $user->id)->update(['role' => 'superadmin']);
    }

    public function test_login_redirects_admins_to_admin_dashboard(): void
    {
        $admin = User::factory()->admin()->create();
        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])->assertRedirect('/admin/dashboard');
    }

    public function test_admin_login_opens_admin_dashboard_even_with_a_saved_user_destination(): void
    {
        $admin = User::factory()->admin()->create();
        $this->withSession(['url.intended' => route('dashboard')])
            ->post('/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'))->assertSessionMissing('url.intended');
        $this->get('/')->assertRedirect(route('admin.dashboard'));
        $this->get('/login')->assertRedirect(route('admin.dashboard'));
        $this->get('/admin/dashboard')->assertOk()->assertSee('data-account-role="admin"', false)->assertSee('Admin Dashboard');
        $this->get('/profile')->assertOk()->assertSee('Account role')->assertSee('data-account-role="admin"', false);
    }

    public function test_normal_user_login_shows_user_role_and_user_dashboard(): void
    {
        $user = User::factory()->create();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('dashboard'));
        $this->get('/')->assertRedirect(route('dashboard'));
        $this->get('/login')->assertRedirect(route('dashboard'));
        $this->get('/dashboard')->assertOk()->assertSee('data-account-role="user"', false)->assertDontSee('ADMIN TOOLS');
        $this->get('/profile')->assertOk()->assertSee('Account role')->assertSee('data-account-role="user"', false);
        $this->get('/admin/dashboard')->assertForbidden();
    }

    public function test_console_promotion_updates_only_the_selected_account_and_is_audited(): void
    {
        $target = User::factory()->create();
        $other = User::factory()->create();
        $this->artisan('agrisense:admin', ['email' => $target->email])->assertSuccessful();
        $this->assertSame('admin', $target->fresh()->role);
        $this->assertSame('user', $other->fresh()->role);
        $this->assertDatabaseHas('audit_logs', ['action' => 'USER_ROLE_CHANGED', 'table_name' => 'users', 'record_id' => (string) $target->id]);
    }

    public function test_administrator_mutation_routes_have_authentication_and_admin_middleware(): void
    {
        foreach (['crops.destroy', 'devices.store', 'devices.update', 'devices.destroy', 'devices.token', 'thresholds.store', 'thresholds.destroy', 'admin.users.store', 'admin.users.update', 'admin.users.destroy', 'admin.users.role', 'admin.sources.store', 'admin.documents.store', 'admin.documents.destroy'] as $name) {
            $middleware = app('router')->getRoutes()->getByName($name)->gatherMiddleware();
            $this->assertContains('auth', $middleware, $name);
            $this->assertContains('admin', $middleware, $name);
        }
    }

    public function test_admin_monitoring_can_read_other_owners_data(): void
    {
        $owner = User::factory()->create();
        $crop = $this->crop($owner);
        $device = $owner->devices()->create(['crop_id' => $crop->id, 'device_id' => 'MONITOR-1', 'name' => 'Test monitor', 'status' => 'offline', 'is_active' => true]);
        $device->readings()->create(['crop_id' => $crop->id, 'reading_at' => now(), 'temperature' => 25]);
        $this->actingAs(User::factory()->admin()->create())->getJson('/monitoring/data?crop_id='.$crop->id)->assertOk()->assertJsonCount(1, 'points');
        $this->actingAs(User::factory()->create())->getJson('/monitoring/data?crop_id='.$crop->id)->assertUnprocessable();
    }

    public function test_admin_can_ask_about_another_owners_crop_and_view_its_alerts(): void
    {
        $owner = User::factory()->create();
        $crop = $this->crop($owner);
        $this->actingAs(User::factory()->admin()->create());
        $this->post('/ai', ['crop_id' => $crop->id, 'question' => 'Explain the growing conditions.'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('ai_conversations', ['crop_id' => $crop->id]);
        $this->get('/alerts?crop_id='.$crop->id)->assertOk();
        Http::assertNothingSent();
    }
}
