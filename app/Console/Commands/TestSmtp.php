<?php

namespace App\Console\Commands;

use App\Jobs\SendAuthMail;
use App\Mail\AuthMessage;
use Illuminate\Console\Command;

class TestSmtp extends Command
{
    protected $signature = 'mail:test-smtp {email : A development inbox you control}';

    protected $description = 'Send one branded SMTP test email in a local development environment';

    public function handle(): int
    {
        if (! app()->environment('local')) {
            $this->error('This command is only available in the local environment.');

            return self::FAILURE;
        }
        $recipient = (string) $this->argument('email');
        if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $this->error('Enter a valid email address.');

            return self::FAILURE;
        }
        try {
            (new SendAuthMail(new AuthMessage('test', 0, $recipient, 'AgriSense developer')))->handle();
        } catch (\Throwable) {
            $this->error('SMTP delivery failed. Check the SMTP configuration and network connection.');

            return self::FAILURE;
        }
        $this->info('SMTP test email sent. Check your inbox and spam folder.');

        return self::SUCCESS;
    }
}
