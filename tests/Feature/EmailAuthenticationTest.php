<?php

namespace Tests\Feature;

use App\Jobs\SendAuthMail;
use App\Mail\AuthMessage;
use App\Models\User;
use App\Services\EmailVerificationService;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EmailAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Mail::fake();
    }

    /** @return array<string, string> */
    private function registration(): array
    {
        return ['name' => 'Farm Owner', 'email' => 'farmer@example.test', 'password' => 'secure-farm-password', 'password_confirmation' => 'secure-farm-password'];
    }

    private function issueCode(User $user): string
    {
        app(EmailVerificationService::class)->send($user);

        return Mail::sent(AuthMessage::class)->last()->secret;
    }

    public function test_registration_hashes_password_and_code_and_redirects_to_verification(): void
    {
        $this->post(route('register'), $this->registration())->assertRedirect(route('verification.notice'));
        $user = User::firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->email_verified_at);
        $this->assertNotNull($user->profile);
        $this->assertTrue(Hash::check('secure-farm-password', $user->password));
        Mail::assertSent(AuthMessage::class, fn (AuthMessage $mail): bool => $mail->kind === 'verification' && preg_match('/^[0-9]{6}$/', $mail->secret) === 1);
        Mail::assertSentCount(1);
        $mail = Mail::sent(AuthMessage::class)->first();
        $record = DB::table('email_verification_codes')->first();
        $this->assertNotSame($mail->secret, $record->otp_hash);
        $this->assertTrue(Hash::check($mail->secret, $record->otp_hash));
        $this->assertEquals(now()->addMinutes(5)->timestamp, strtotime($record->expires_at));
        $this->get(route('dashboard'))->assertRedirect(route('verification.notice'));
    }

    public function test_duplicate_email_is_rejected(): void
    {
        User::factory()->create(['email' => 'farmer@example.test']);
        $this->post(route('register'), $this->registration())->assertSessionHasErrors('email');
        Mail::assertNothingSent();
    }

    public function test_valid_code_verifies_once_and_sends_welcome(): void
    {
        $user = User::factory()->unverified()->create();
        $code = $this->issueCode($user);
        $this->actingAs($user)->post(route('verification.verify'), ['otp' => $code])->assertRedirect(route('verification.success'));
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertDatabaseMissing('email_verification_codes', ['user_id' => $user->id]);
        $this->get(route('verification.success'))->assertOk()->assertSee("You're all set!", false);
        $this->post(route('verification.verify'), ['otp' => $code])->assertSessionHasErrors('otp');
        Mail::assertSent(AuthMessage::class, fn (AuthMessage $mail): bool => $mail->kind === 'welcome');
        Mail::assertSentCount(2);
    }

    public function test_wrong_code_is_rejected_and_attempt_is_persisted(): void
    {
        $user = User::factory()->unverified()->create();
        $code = $this->issueCode($user);
        $wrongCode = $code === '111111' ? '222222' : '111111';
        $this->actingAs($user)->post(route('verification.verify'), ['otp' => $wrongCode])->assertSessionHasErrors('otp');
        $this->assertDatabaseHas('email_verification_codes', ['user_id' => $user->id, 'attempts' => 1]);
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_expired_code_cannot_verify(): void
    {
        $user = User::factory()->unverified()->create();
        $code = $this->issueCode($user);
        $this->travel(5)->minutes();
        $this->actingAs($user)->post(route('verification.verify'), ['otp' => $code])->assertSessionHasErrors('otp');
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_resend_cooldown_and_previous_code_invalidation(): void
    {
        $user = User::factory()->unverified()->create();
        $oldCode = $this->issueCode($user);
        $this->actingAs($user)->post(route('verification.send'))->assertSessionHasErrors('otp');
        Mail::assertSentCount(1);
        $oldHash = DB::table('email_verification_codes')->value('otp_hash');
        $this->travel(60)->seconds();
        $this->post(route('verification.send'))->assertSessionHas('success');
        $record = DB::table('email_verification_codes')->first();
        $this->assertNotSame($oldHash, $record->otp_hash);
        $newCode = Mail::sent(AuthMessage::class)->last()->secret;
        if ($newCode !== $oldCode) {
            $this->post(route('verification.verify'), ['otp' => $oldCode])->assertSessionHasErrors('otp');
        }
        $this->post(route('verification.verify'), ['otp' => $newCode])->assertRedirect(route('verification.success'));
    }

    public function test_five_wrong_attempts_lock_code_even_after_rate_limit_expires(): void
    {
        $user = User::factory()->unverified()->create();
        $code = $this->issueCode($user);
        $wrongCode = $code === '111111' ? '222222' : '111111';
        $this->actingAs($user);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('verification.verify'), ['otp' => $wrongCode])->assertSessionHasErrors('otp');
        }
        $this->post(route('verification.verify'), ['otp' => $code])->assertStatus(429);
        $this->travel(61)->seconds();
        $this->post(route('verification.verify'), ['otp' => $code])->assertSessionHasErrors('otp');
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_code_is_bound_to_user_and_current_email(): void
    {
        $user = User::factory()->unverified()->create();
        $code = $this->issueCode($user);
        $other = User::factory()->unverified()->create();
        $this->actingAs($other)->post(route('verification.verify'), ['otp' => $code])->assertSessionHasErrors('otp');
        $user->update(['email' => 'changed@example.test']);
        $this->actingAs($user)->post(route('verification.verify'), ['otp' => $code])->assertSessionHasErrors('otp');
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_unverified_login_goes_to_verification_and_verified_login_preserves_roles(): void
    {
        $user = User::factory()->unverified()->create();
        $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('verification.notice'));
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
        $user->markEmailAsVerified();
        $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('dashboard'));
        $this->post(route('logout'));
        $admin = User::factory()->admin()->create();
        $this->post(route('login'), ['email' => $admin->email, 'password' => 'password'])->assertRedirect(route('admin.dashboard'));
    }

    public function test_invalid_login_and_login_throttling(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('login'), ['email' => 'missing@example.test', 'password' => 'wrong'])->assertSessionHasErrors('email');
        }
        $this->post(route('login'), ['email' => 'missing@example.test', 'password' => 'wrong'])->assertStatus(429);
        $this->assertGuest();
    }

    public function test_protected_routes_and_verification_routes_enforce_access(): void
    {
        $this->get(route('verification.notice'))->assertRedirect(route('login'));
        $user = User::factory()->unverified()->create();
        $this->actingAs($user);
        foreach (['dashboard', 'devices.index', 'crops.index', 'profile.index', 'settings', 'admin.dashboard'] as $route) {
            $this->get(route($route))->assertRedirect(route('verification.notice'));
        }
        $this->getJson(route('monitoring.data'))->assertForbidden();
        $this->get(route('verification.notice'))->assertOk();
    }

    public function test_reset_request_is_generic_and_sends_branded_email(): void
    {
        $user = User::factory()->create();
        $existing = $this->post(route('password.email'), ['email' => $user->email]);
        $message = session('success');
        $existing->assertSessionHas('success');
        $this->post(route('password.email'), ['email' => 'missing@example.test'])->assertSessionHas('success', $message);
        Mail::assertSent(AuthMessage::class, fn (AuthMessage $mail): bool => $mail->kind === 'reset' && $mail->recipient === $user->email);
        Mail::assertSentCount(1);
        $this->assertNotSame(Mail::sent(AuthMessage::class)->first()->secret, DB::table('password_reset_tokens')->value('token'));
    }

    public function test_password_reset_token_is_single_use(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);
        $data = ['token' => $token, 'email' => $user->email, 'password' => 'new-secure-farm-password', 'password_confirmation' => 'new-secure-farm-password'];
        $this->post(route('password.update'), $data)->assertRedirect(route('login'));
        $this->assertTrue(Hash::check($data['password'], $user->fresh()->password));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $this->post(route('password.update'), $data)->assertSessionHasErrors('email');
    }

    public function test_expired_reset_token_is_rejected(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);
        $this->travel(61)->minutes();
        $this->post(route('password.update'), ['token' => $token, 'email' => $user->email, 'password' => 'new-secure-farm-password', 'password_confirmation' => 'new-secure-farm-password'])->assertSessionHasErrors('email');
    }

    public function test_smtp_failure_preserves_account_and_hides_exception(): void
    {
        Mail::shouldReceive('mailer')->andThrow(new \RuntimeException('Sensitive SMTP detail'));
        $this->post(route('register'), $this->registration())->assertRedirect(route('verification.notice'))->assertSessionHasErrors('otp');
        $this->assertDatabaseHas('users', ['email' => 'farmer@example.test', 'email_verified_at' => null]);
        $this->assertStringNotContainsString('Sensitive SMTP detail', session('errors')->first('otp'));
    }

    public function test_reset_failure_returns_same_generic_response(): void
    {
        $user = User::factory()->create();
        Mail::shouldReceive('mailer')->andThrow(new \RuntimeException('Sensitive SMTP detail'));
        $this->post(route('password.email'), ['email' => $user->email])->assertSessionHas('success', 'If an account exists for that email, a password reset link has been sent.');
    }

    public function test_welcome_failure_does_not_break_verification(): void
    {
        $user = User::factory()->unverified()->create();
        $code = $this->issueCode($user);
        Mail::shouldReceive('mailer')->andThrow(new \RuntimeException('Sensitive SMTP detail'));
        $this->actingAs($user)->post(route('verification.verify'), ['otp' => $code])->assertRedirect(route('verification.success'));
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_queue_jobs_are_encrypted_and_skip_stale_codes(): void
    {
        $queueManager = app('queue');
        Queue::fake();
        config(['mail.auth_queue' => true, 'queue.default' => 'database']);
        $user = User::factory()->unverified()->create();
        app(EmailVerificationService::class)->send($user);
        Queue::assertPushed(SendAuthMail::class, fn (SendAuthMail $job): bool => $job instanceof ShouldBeEncrypted);
        Mail::assertNothingSent();
        $job = Queue::pushed(SendAuthMail::class)->first();
        $job->beforeCommit();
        $queueManager->connection('database')->push($job);
        $payload = DB::table('jobs')->value('payload');
        $this->assertStringNotContainsString($job->message->secret, $payload);
        $this->assertStringNotContainsString('farmer@example.test', $payload);
        $this->travel(5)->minutes();
        $job->handle();
        Mail::assertNothingSent();
    }

    public function test_resend_failure_leaves_account_unverified(): void
    {
        $user = User::factory()->unverified()->create();
        Mail::shouldReceive('mailer')->andThrow(new \RuntimeException('Sensitive SMTP detail'));
        $this->actingAs($user)->post(route('verification.send'))->assertSessionHasErrors('otp');
        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertDatabaseHas('email_verification_codes', ['user_id' => $user->id]);
    }

    public function test_resends_are_rate_limited_across_cooldown_windows(): void
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user);
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->post(route('verification.send'))->assertSessionHas('success');
            $this->travel(61)->seconds();
        }
        $this->post(route('verification.send'))->assertStatus(429);
        Mail::assertSentCount(10);
    }

    public function test_profile_email_change_requires_new_verification(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('profile.update'), ['name' => $user->name, 'email' => 'new@example.test'])->assertRedirect(route('verification.notice'));
        $this->assertNull($user->fresh()->email_verified_at);
        Mail::assertSent(AuthMessage::class, fn (AuthMessage $mail): bool => $mail->recipient === 'new@example.test' && $mail->kind === 'verification');
        $this->get(route('dashboard'))->assertRedirect(route('verification.notice'));
    }

    public function test_auth_pages_and_email_templates_render(): void
    {
        foreach (['login', 'register', 'password.request'] as $route) {
            $this->get(route($route))->assertOk();
        }
        $this->get(route('password.reset', ['token' => 'test-token', 'email' => 'farmer@example.test']))->assertOk();
        foreach (['verification', 'reset', 'welcome', 'test'] as $kind) {
            $message = new AuthMessage($kind, 0, 'farmer@example.test', 'Farmer', '123456');
            $html = $message->render();
            $this->assertStringContainsString('AgriSense', $html);
            $this->assertStringContainsString('Smart Farming Starts Here', $html);
        }
    }
}
