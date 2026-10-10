<?php

namespace App\Services;

use App\Jobs\SendAuthMail;
use App\Mail\AuthMessage;
use Illuminate\Support\Facades\Log;

class AuthMailService
{
    public function send(AuthMessage $message): void
    {
        try {
            if (config('mail.auth_queue') && config('queue.default') !== 'sync') {
                SendAuthMail::dispatch($message)->afterCommit();
            } else {
                (new SendAuthMail($message))->handle();
            }
        } catch (\Throwable) {
            Log::warning('Authentication email delivery could not be started.', ['type' => $message->kind, 'user_id' => $message->userId]);
            throw new \RuntimeException('Email delivery is temporarily unavailable. Please try again later.');
        }
    }
}
