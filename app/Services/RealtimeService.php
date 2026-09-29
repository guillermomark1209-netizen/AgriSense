<?php

namespace App\Services;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RealtimeService
{
    public function credentials(Request $request): JsonResponse
    {
        abort_unless(config('agrisense.realtime_enabled') && config('agrisense.realtime_secret') && config('agrisense.supabase_public_key'), 404);
        $encode = fn ($data) => rtrim(strtr(base64_encode(json_encode($data, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
        $header = $encode(['alg' => 'HS256', 'typ' => 'JWT']);
        $payload = $encode(['role' => 'authenticated', 'agrisense_user_id' => (string) $request->user()->id, 'aud' => 'authenticated', 'iat' => time(), 'exp' => time() + 300]);
        $signature = rtrim(strtr(base64_encode(hash_hmac('sha256', $header.'.'.$payload, config('agrisense.realtime_secret'), true)), '+/', '-_'), '=');

        return response()->json(['url' => config('agrisense.supabase_url'), 'key' => config('agrisense.supabase_public_key'), 'token' => $header.'.'.$payload.'.'.$signature, 'channel' => 'farm:'.$request->user()->id]);
    }
}
