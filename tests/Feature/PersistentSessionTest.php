<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PersistentSessionTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->databasePath = tempnam(sys_get_temp_dir(), 'agrisense-session-');
        $this->configurePersistentStorage();
        $this->artisan('migrate', ['--force' => true, '--no-interaction' => true])->assertExitCode(0);
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        parent::tearDown();
        if (is_file($this->databasePath)) {
            unlink($this->databasePath);
        }
    }

    private function configurePersistentStorage(): void
    {
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $this->databasePath,
            'database.connections.sqlite.url' => null,
            'session.driver' => 'database',
            'session.connection' => 'sqlite',
            'session.secure' => true,
            'session.domain' => null,
            'session.encrypt' => true,
            'cache.default' => 'database',
            'cache.stores.database.connection' => 'sqlite',
        ]);
        DB::purge('sqlite');
        $this->withoutVite();
        Http::preventStrayRequests();
    }

    private function restartApplication(): void
    {
        DB::disconnect('sqlite');
        $this->refreshApplication();
        $this->configurePersistentStorage();
    }

    private function retainCookies(TestResponse $response): void
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === config('session.cookie')) {
                $this->assertTrue($cookie->isSecure());
                $this->assertTrue($cookie->isHttpOnly());
                $this->withUnencryptedCookie($cookie->getName(), $cookie->getValue());
            }
        }
    }

    public function test_anonymous_csrf_token_survives_new_application_instances(): void
    {
        $origin = 'https://www.agrisense.website';
        $response = $this->get($origin.'/login')->assertOk();
        $token = session()->token();
        $this->retainCookies($response);
        for ($request = 0; $request < 3; $request++) {
            $this->restartApplication();
            $response = $this->get($origin.'/login')->assertOk();
            $this->assertSame($token, session()->token());
            $this->retainCookies($response);
        }
    }

    public function test_login_and_navigation_survive_new_instances_until_explicit_logout(): void
    {
        $origin = 'https://www.agrisense.website';
        $user = User::factory()->create();
        $response = $this->post($origin.'/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect($origin.'/dashboard');
        $this->retainCookies($response);
        $this->assertDatabaseHas('sessions', ['user_id' => $user->id]);
        foreach (['settings', 'profile', 'settings'] as $path) {
            $this->restartApplication();
            $response = $this->get($origin.'/'.$path)->assertOk();
            $this->assertAuthenticatedAs(User::findOrFail($user->id));
            $this->retainCookies($response);
        }
        $this->restartApplication();
        $response = $this->post($origin.'/logout')->assertRedirect($origin.'/login');
        $this->retainCookies($response);
        $this->restartApplication();
        $this->get($origin.'/settings')->assertRedirect($origin.'/login');
        $this->assertGuest();
    }

    public function test_production_configuration_replaces_instance_local_storage(): void
    {
        $values = ['APP_ENV' => 'production', 'SESSION_DRIVER' => 'file', 'CACHE_STORE' => 'file'];
        $previous = [];
        foreach ($values as $key => $value) {
            $previous[$key] = ['env' => $_ENV[$key] ?? null, 'server' => $_SERVER[$key] ?? null];
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
        try {
            $session = require config_path('session.php');
            $cache = require config_path('cache.php');
            $this->assertSame('database', $session['driver']);
            $this->assertSame('database', $cache['default']);
            $_ENV['SESSION_DRIVER'] = $_SERVER['SESSION_DRIVER'] = 'redis';
            $_ENV['CACHE_STORE'] = $_SERVER['CACHE_STORE'] = 'redis';
            $session = require config_path('session.php');
            $cache = require config_path('cache.php');
            $this->assertSame('redis', $session['driver']);
            $this->assertSame('redis', $cache['default']);
            $_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = 'local';
            $_ENV['SESSION_DRIVER'] = $_SERVER['SESSION_DRIVER'] = 'file';
            $_ENV['CACHE_STORE'] = $_SERVER['CACHE_STORE'] = 'file';
            $session = require config_path('session.php');
            $cache = require config_path('cache.php');
            $this->assertSame('file', $session['driver']);
            $this->assertSame('file', $cache['default']);
        } finally {
            foreach ($previous as $key => $value) {
                if ($value['env'] === null) {
                    unset($_ENV[$key]);
                } else {
                    $_ENV[$key] = $value['env'];
                }
                if ($value['server'] === null) {
                    unset($_SERVER[$key]);
                } else {
                    $_SERVER[$key] = $value['server'];
                }
            }
        }
    }
}
