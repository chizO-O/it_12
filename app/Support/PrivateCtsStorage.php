<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PrivateCtsStorage
{
    private function config(): array
    {
        $settings = config('services.supabase', []);
        $url = rtrim((string) ($settings['url'] ?? ''), '/');
        $key = (string) ($settings['service_role_key'] ?? '');
        $bucket = (string) ($settings['cts_bucket'] ?? 'grovemap-cts');
        if (! str_starts_with($url, 'https://') || $key === '' || ! preg_match('/^[a-z0-9][a-z0-9_-]*$/', $bucket)) {
            throw ValidationException::withMessages(['document' => 'Private CTS document storage is not configured. Contact the administrator.']);
        }

        return [$url, $key, $bucket];
    }

    public function upload(UploadedFile $file, int $ctsId, string $kind): string
    {
        [$url, $key, $bucket] = $this->config();
        if (! in_array($kind, ['draft', 'executed'], true)) {
            throw new \InvalidArgumentException('Invalid document type.');
        }
        $path = 'cts/'.$ctsId.'/'.$kind.'/'.Str::uuid().'.pdf';
        $contents = file_get_contents($file->getRealPath());
        if ($contents === false) {
            throw ValidationException::withMessages(['document' => 'Could not read the uploaded document.']);
        }
        $response = Http::withToken($key)->withHeaders([
            'apikey' => $key, 'x-upsert' => 'false',
        ])->withBody($contents, 'application/pdf')
            ->timeout(30)->post("{$url}/storage/v1/object/{$bucket}/{$path}");
        if (! $response->successful()) {
            // Deliberately never log the service-role key, request headers, signed URL or document bytes.
            Log::warning('CTS private Storage upload failed', [
                'status' => $response->status(),
                'error' => mb_substr((string) ($response->json('error') ?? $response->json('code') ?? ''), 0, 120),
                'message' => mb_substr((string) ($response->json('message') ?? ''), 0, 200),
            ]);
            throw ValidationException::withMessages(['document' => 'Unable to upload the CTS PDF to private storage. Check the application log for the Storage status code.']);
        }

        return $path;
    }

    public function signedUrl(string $path): string
    {
        [$url, $key, $bucket] = $this->config();
        if (! preg_match('~^cts/[0-9]+/(draft|executed)/[a-f0-9-]{36}\.pdf$~i', $path)) {
            abort(404);
        }
        $response = Http::withToken($key)->withHeaders(['apikey' => $key])
            ->timeout(15)->post("{$url}/storage/v1/object/sign/{$bucket}/{$path}", ['expiresIn' => 60]);
        if (! $response->successful() || ! is_string($response->json('signedURL'))) {
            abort(503, 'CTS document is temporarily unavailable.');
        }
        $signed = $response->json('signedURL');
        if (! str_starts_with($signed, '/object/sign/') || ! str_contains($signed, '?token=')) {
            abort(503, 'CTS document link could not be created.');
        }

        return $url.'/storage/v1'.$signed;
    }
}
