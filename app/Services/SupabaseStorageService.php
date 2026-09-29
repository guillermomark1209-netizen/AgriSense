<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class SupabaseStorageService
{
    private function client(): PendingRequest
    {
        if (! config('agrisense.supabase_url') || ! config('agrisense.supabase_key')) {
            throw new RuntimeException('Supabase Storage is not configured.');
        }

        return Http::baseUrl(rtrim(config('agrisense.supabase_url'), '/').'/storage/v1')
            ->withToken(config('agrisense.supabase_key'))->withHeaders(['apikey' => config('agrisense.supabase_key')])
            ->connectTimeout(10)->timeout(30);
    }

    public function upload(UploadedFile $file, string $bucket, int $ownerId): string
    {
        abort_unless(in_array($bucket, ['crop-images', 'plant-analysis', 'profile-images', 'knowledge-documents']), 422);
        $path = $ownerId.'/'.Str::uuid().'.'.$file->extension();
        $this->client()->withBody($file->getContent(), $file->getMimeType())->post('/object/'.$bucket.'/'.$path)->throw();

        return $bucket.'/'.$path;
    }

    public function signedUrl(string $path): string
    {
        $result = $this->client()->post('/object/sign/'.$path, ['expiresIn' => 300])->throw()->json('signedURL');
        if (! is_string($result) || ! str_starts_with($result, '/object/sign/')) {
            throw new RuntimeException('Storage returned an invalid signed URL.');
        }

        return rtrim(config('agrisense.supabase_url'), '/').'/storage/v1'.$result;
    }

    public function download(string $path): string
    {
        return $this->client()->get('/object/'.$path)->throw()->body();
    }

    public function delete(string $path): void
    {
        [$bucket,$object] = explode('/', $path, 2);
        $this->client()->delete('/object/'.$bucket, ['prefixes' => [$object]])->throw();
    }
}
