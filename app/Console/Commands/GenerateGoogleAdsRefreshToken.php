<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class GenerateGoogleAdsRefreshToken extends Command
{
    protected $signature = 'google-ads:refresh-token
        {--code= : Authorization code returned by Google}
        {--redirect-url= : Full localhost redirect URL returned by Google}';

    protected $description = 'Generate a Google Ads OAuth refresh token.';

    private const REDIRECT_URI = 'http://127.0.0.1';
    private const SCOPE = 'https://www.googleapis.com/auth/adwords';

    public function handle(): int
    {
        $clientId = (string) config('services.google_ads.client_id');
        $clientSecret = (string) config('services.google_ads.client_secret');

        if (! $clientId || ! $clientSecret) {
            $this->error('GOOGLE_ADS_CLIENT_ID e GOOGLE_ADS_CLIENT_SECRET sono obbligatori.');

            return self::FAILURE;
        }

        $code = $this->option('code') ?: $this->codeFromRedirectUrl($this->option('redirect-url'));

        if (! $code) {
            $this->info('Apri questo URL, autorizza con l’account Google Ads, poi copia l’URL finale 127.0.0.1:');
            $this->newLine();
            $this->line($this->authorizationUrl($clientId));
            $this->newLine();
            $this->line('Poi rilancia:');
            $this->line('php artisan google-ads:refresh-token --redirect-url="URL_FINALE_127.0.0.1"');

            return self::SUCCESS;
        }

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => self::REDIRECT_URI,
        ]);

        if (! $response->successful()) {
            $this->error('Google OAuth ha risposto con HTTP '.$response->status().':');
            $this->line($response->body());

            return self::FAILURE;
        }

        $refreshToken = $response->json('refresh_token');

        if (! $refreshToken) {
            $this->error('Google non ha restituito un refresh_token. Riprova usando prompt=consent o revoca l’accesso precedente dall’account Google.');
            $this->line($response->body());

            return self::FAILURE;
        }

        $this->info('Refresh token generato. Aggiorna .env admin con:');
        $this->newLine();
        $this->line('GOOGLE_ADS_REFRESH_TOKEN='.$refreshToken);

        return self::SUCCESS;
    }

    private function authorizationUrl(string $clientId): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => self::REDIRECT_URI,
            'response_type' => 'code',
            'scope' => self::SCOPE,
            'access_type' => 'offline',
            'prompt' => 'consent',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    private function codeFromRedirectUrl(?string $redirectUrl): ?string
    {
        if (! $redirectUrl) {
            return null;
        }

        $query = parse_url($redirectUrl, PHP_URL_QUERY);

        if (! $query) {
            return null;
        }

        parse_str($query, $params);

        return $params['code'] ?? null;
    }
}
