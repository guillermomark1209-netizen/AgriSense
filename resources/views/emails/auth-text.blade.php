AgriSense — Smart Farming Starts Here.
Hello {{ $name }},

@if($kind === 'verification')
Welcome to AgriSense! Verify your email using this code: {{ $secret }}
This code expires in 5 minutes. New codes replace earlier ones.
If you did not create an account, ignore this email.
@elseif($kind === 'reset')
Reset Your Password
Reset your password using this link:
{{ route('password.reset', ['token' => $secret, 'email' => $recipient]) }}
This link expires in {{ config('auth.passwords.'.config('auth.defaults.passwords').'.expire') }} minutes.
If you did not request a reset, ignore this email.
@elseif($kind === 'welcome')
You're All Set! Your account has been verified.
Monitor soil moisture and environmental sensors, manage connected devices, and explore agricultural assistance.
Go to your dashboard: {{ route('dashboard') }}
@else
SMTP delivery is working. This is a development test email.
@endif

This is an automated email. Please do not reply.
