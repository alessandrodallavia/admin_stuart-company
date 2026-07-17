<?php

namespace App\Console\Commands;

use App\Services\GoogleAdsReportingService;
use Illuminate\Console\Command;

class SyncGoogleAdsLeadData extends Command
{
    protected $signature = 'crm:sync-google-ads {--days=30 : Numero di giorni da sincronizzare, massimo 90}';

    protected $description = 'Arricchisce i lead con campagna, Ad Group, keyword, località e dispositivo associati al GCLID';

    public function handle(GoogleAdsReportingService $reporting): int
    {
        $days = max(1, min(90, (int) $this->option('days')));

        try {
            $updated = $reporting->enrichLeads(now()->subDays($days - 1), now());
        } catch (\Throwable $exception) {
            report($exception);
            $this->error('Sincronizzazione Google Ads fallita: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Lead aggiornati: {$updated}");

        return self::SUCCESS;
    }
}
