<?php

namespace App\Services;

use App\Mail\AuthMessage;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class EmailVerificationService
{
    public function __construct(private AuthMailService $mail) {}

    public function send(User $user): void
    {
        $message = DB::transaction(function () use ($user): ?AuthMessage {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            if ($user->hasVerifiedEmail()) {
                return null;
            }
            $record = DB::table('email_verification_codes')->where('user_id', $user->id)->first();
            if ($record && $record->email === $user->email && Carbon::parse($record->updated_at)->addSeconds(60)->isFuture()) {
                throw ValidationException::withMessages(['otp' => 'Please wait 60 seconds before requesting another code.']);
            }
            do {
                $code = (string) random_int(100000, 999999);
            } while ($record && Hash::check($code, $record->otp_hash));
            DB::table('email_verification_codes')->updateOrInsert(['user_id' => $user->id], [
                'email' => $user->email, 'otp_hash' => Hash::make($code), 'expires_at' => now()->addMinutes(5),
                'attempts' => 0, 'created_at' => now(), 'updated_at' => now(),
            ]);

            return new AuthMessage('verification', $user->id, $user->email, $user->name, $code);
        });
        if ($message) {
            $this->mail->send($message);
        }
    }

    public function verify(User $user, #[\SensitiveParameter] string $code): bool
    {
        $verified = DB::transaction(function () use ($user, $code): bool {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            $record = DB::table('email_verification_codes')->where('user_id', $user->id)->lockForUpdate()->first();
            if ($user->hasVerifiedEmail() || ! $record || $record->email !== $user->email || Carbon::parse($record->expires_at)->lessThanOrEqualTo(now()) || $record->attempts >= 5) {
                return false;
            }
            DB::table('email_verification_codes')->where('id', $record->id)->increment('attempts');
            if (! Hash::check($code, $record->otp_hash)) {
                return false;
            }
            $user->markEmailAsVerified();
            DB::table('email_verification_codes')->where('id', $record->id)->delete();

            return true;
        });
        if ($verified) {
            $user->refresh();
            event(new Verified($user));
            try {
                $this->mail->send(new AuthMessage('welcome', $user->id, $user->email, $user->name));
            } catch (\RuntimeException) {
                return true;
            }
        }

        return $verified;
    }

    public function cooldown(User $user): int
    {
        $record = DB::table('email_verification_codes')->where('user_id', $user->id)->where('email', $user->email)->first();

        return $record ? max(0, Carbon::parse($record->updated_at)->addSeconds(60)->timestamp - now()->timestamp) : 0;
    }
}
