@php
    $resetUrl = $kind === 'reset' ? route('password.reset', ['token' => $secret, 'email' => $recipient]) : '';
    $dashboardUrl = route('dashboard');
@endphp
<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>AgriSense</title></head>
<body style="margin:0;background:#F1F8E9;color:#263238;font-family:Arial,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F1F8E9;"><tr><td align="center" style="padding:32px 12px;">
<table role="presentation" width="560" cellpadding="0" cellspacing="0" style="width:100%;max-width:560px;background:#FFFFFF;border-radius:20px;overflow:hidden;">
<tr><td style="padding:28px 32px;background:#1B4332;color:#FFFFFF;font-size:26px;font-weight:bold;"><img src="{{ asset('icons/icon-192.png') }}" width="40" height="40" alt="" style="vertical-align:middle;margin-right:10px;">AgriSense</td></tr>
<tr><td style="padding:32px;">
<p style="color:#64748B;">Hello {{ $name }},</p>
@if($kind === 'verification')
<h1 style="font-size:26px;color:#1B4332;">Welcome to AgriSense!</h1>
<p style="line-height:1.7;">Thank you for joining AgriSense — your smart agricultural monitoring companion. To complete your registration and secure your account, verify your email address using the code below.</p>
<table role="presentation" width="100%"><tr><td align="center" style="background:#F1F8E9;border:1px solid #66BB6A;border-radius:12px;padding:24px;font-size:32px;letter-spacing:8px;font-weight:bold;color:#1B5E20;">{{ $secret }}</td></tr></table>
<p style="line-height:1.7;">This verification code will expire in <strong>5 minutes</strong>. Each new code replaces the previous one.</p>
<p style="color:#64748B;line-height:1.7;">If you did not create an AgriSense account, you can safely ignore this email.</p>
@elseif($kind === 'reset')
<h1 style="font-size:26px;color:#1B4332;">Reset Your Password</h1>
<p style="line-height:1.7;">We received a request to reset the password for your AgriSense account. Click the button below to securely create a new password.</p>
<table role="presentation"><tr><td style="background:#2E7D32;border-radius:8px;"><a href="{{ $resetUrl }}" style="display:inline-block;padding:16px 24px;color:white;text-decoration:none;font-weight:bold;">Reset Password</a></td></tr></table>
<p style="line-height:1.7;">This link expires in {{ config('auth.passwords.'.config('auth.defaults.passwords').'.expire') }} minutes. If you did not request this reset, you can safely ignore this email.</p>
<p style="color:#64748B;">If the button does not work, copy this URL into your browser:</p><p style="word-break:break-all;font-size:13px;"><a href="{{ $resetUrl }}" style="color:#2E7D32;">{{ $resetUrl }}</a></p>
@elseif($kind === 'welcome')
<h1 style="font-size:26px;color:#1B4332;">You're All Set!</h1>
<p style="line-height:1.7;">Your AgriSense account has been successfully verified. You can now access your agricultural monitoring dashboard, manage connected devices, and monitor your farm's environmental conditions.</p>
<table role="presentation"><tr><td style="background:#2E7D32;border-radius:8px;"><a href="{{ $dashboardUrl }}" style="display:inline-block;padding:16px 24px;color:white;text-decoration:none;font-weight:bold;">Go to Dashboard</a></td></tr></table>
<ul style="line-height:2;"><li>Soil moisture monitoring</li><li>Environmental sensor tracking</li><li>Connected device management</li><li>Evidence-based agricultural assistance</li></ul>
@else
<h1 style="font-size:26px;color:#1B4332;">SMTP delivery is working</h1><p>This development test confirms that AgriSense can deliver branded email through your configured SMTP provider.</p>
@endif
</td></tr>
<tr><td style="padding:24px 32px;border-top:1px solid #E5EDE5;color:#64748B;font-size:12px;line-height:1.7;">AgriSense — Smart Farming Starts Here.<br>This is an automated email. Please do not reply.</td></tr>
</table></td></tr></table></body></html>
