<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DropboxManager
{
    public static function getAccessToken(): ?string
    {
        $response = Http::asForm()->post('https://api.dropboxapi.com/oauth2/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => config('services.dropbox.refresh_token'),
            'client_id' => config('services.dropbox.client_id'),
            'client_secret' => config('services.dropbox.client_secret'),
        ]);

        if ($response->successful()) {
            return $response->json()['access_token'];
        }

        Log::error('[Dropbox] Errore ottenendo access token', ['response' => $response->body()]);

        return null;
    }

    public static function uploadZpl(string $filename, string $content): string
    {
        $token = self::getAccessToken();

        if (! $token) {
            throw new Exception('Dropbox access token non ottenuto');
        }

        $path = '/StampaZebra/attesa/'.$filename;

        $response = Http::withToken($token)
            ->withHeaders([
                'Dropbox-API-Arg' => json_encode([
                    'path' => $path,
                    'mode' => 'add',
                    'autorename' => true,
                    'mute' => false,
                    'strict_conflict' => false,
                ], JSON_UNESCAPED_UNICODE),
                'Content-Type' => 'application/octet-stream',
            ])
            ->withBody($content, 'application/octet-stream')
            ->post('https://content.dropboxapi.com/2/files/upload');

        if (! $response->successful()) {
            Log::error('[Dropbox] Upload fallito', ['response' => $response->body()]);

            throw new Exception('Errore nel salvataggio su Dropbox');
        }

        return $response->json('path_display') ?: $path;
    }
}
