<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AuthenticatedNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Http::preventStrayRequests();
    }

    public function test_regular_user_navigation_uses_the_current_host_and_keeps_the_session(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);
        $origin = 'http://127.0.0.1:8000';

        $this->post($origin.'/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect($origin.'/dashboard');

        $this->get($origin.'/dashboard')
            ->assertOk()
            ->assertSee('href="'.$origin.'/crops"', false)
            ->assertSee('href="'.$origin.'/monitoring"', false);

        foreach (['crops', 'monitoring', 'devices', 'alerts', 'history', 'profile', 'settings'] as $path) {
            $this->get($origin.'/'.$path)->assertOk();
        }

        $this->get($origin.'/dashboard')->assertOk();
        $this->get($origin.'/admin/dashboard')->assertForbidden();

        $this->post($origin.'/logout')->assertRedirect($origin.'/login');
        $this->get($origin.'/dashboard')->assertRedirect($origin.'/login');
    }

    public function test_administrator_navigation_uses_the_current_host_and_preserves_role_access(): void
    {
        $administrator = User::factory()->admin()->create(['password' => bcrypt('password')]);
        $origin = 'http://127.0.0.1:8000';

        $this->post($origin.'/login', ['email' => $administrator->email, 'password' => 'password'])
            ->assertRedirect($origin.'/admin/dashboard');

        $this->get($origin.'/admin/dashboard')
            ->assertOk()
            ->assertSee('href="'.$origin.'/admin/sources"', false)
            ->assertSee('href="'.$origin.'/admin/documents"', false);

        foreach (['admin/users', 'admin/crops', 'admin/devices', 'admin/alerts', 'admin/settings', 'dashboard', 'crops', 'monitoring', 'devices', 'alerts', 'history', 'profile'] as $path) {
            $this->get($origin.'/'.$path)->assertOk();
        }

        $this->get($origin.'/admin/dashboard')->assertOk();
    }
}
