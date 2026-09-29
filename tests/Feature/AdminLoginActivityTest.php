<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminLoginActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_login_is_recorded_and_visible_to_an_administrator(): void
    {
        $administrator = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['password' => bcrypt('password')]);

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('user_sessions', [
            'user_id' => $user->id,
            'status' => 'online',
            'ip_address' => '127.0.0.1',
            'logout_at' => null,
        ]);
        $this->assertNotNull(DB::table('user_sessions')->where('user_id', $user->id)->value('session_id'));

        $this->actingAs($administrator)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Active Sessions')
            ->assertSee($user->name)
            ->assertSee('Login activity')
            ->assertSee($user->email)
            ->assertSee('Signed in');
    }

    public function test_logout_marks_the_existing_user_session_offline(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password']);
        $this->post(route('logout'))->assertRedirect(route('login'));

        $this->assertDatabaseHas('user_sessions', ['user_id' => $user->id, 'status' => 'inactive']);
        $this->assertNotNull(DB::table('user_sessions')->where('user_id', $user->id)->value('logout_at'));
        $this->assertSame(1, DB::table('user_sessions')->where('user_id', $user->id)->count());
    }

    public function test_a_returning_user_reuses_the_existing_session_tracking_row(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password']);
        $this->post(route('logout'));
        $this->post(route('login'), ['email' => $user->email, 'password' => 'password']);

        $this->assertSame(1, DB::table('user_sessions')->where('user_id', $user->id)->count());
        $this->assertDatabaseHas('user_sessions', ['user_id' => $user->id, 'status' => 'online', 'logout_at' => null]);
    }
}
