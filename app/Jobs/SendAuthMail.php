<?php

namespace App\Jobs;

use App\Mail\AuthMessage;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;

class SendAuthMail implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(public AuthMessage $message) {}

    public function handle(): void
    {
        try {
            if (! $this->isCurrent()) {
                return;
            }
            $mailer = config('mail.auth_mailer');
            $transport = config('mail.mailers.'.$mailer.'.transport');
            if (! in_array($transport, ['smtp', 'ses', 'postmark', 'resend', 'mailgun'], true) && ! app()->runningUnitTests()) {
                throw new \RuntimeException('Authentication emails require a delivery transport.');
            }
            Mail::mailer($mailer)->to($this->message->recipient)->send($this->message);
        } catch (\Throwable) {
            throw new \RuntimeException('Authentication email delivery failed.');
        }
    }

    private function isCurrent(): bool
    {
        if ($this->message->kind === 'test') {
            return true;
        }
        $user = User::find($this->message->userId);
        if (! $user || $user->email !== $this->message->recipient) {
            return false;
        }
        if ($this->message->kind === 'verification') {
            $record = DB::table('email_verification_codes')->where('user_id', $user->id)->first();

            return ! $user->hasVerifiedEmail() && $record && $record->email === $user->email
             && Carbon::parse($record->expires_at)->isFuture() && $record->attempts < 5
             && Hash::check($this->message->secret, $record->otp_hash);
        }
        if ($this->message->kind === 'reset') {
            return Password::tokenExists($user, $this->message->secret);
        }

        return $user->hasVerifiedEmail();
    }

    public function failed(?\Throwable $exception): void
    {
        Log::warning('Authentication email delivery failed after retries.', ['type' => $this->message->kind, 'user_id' => $this->message->userId]);
    }
}
