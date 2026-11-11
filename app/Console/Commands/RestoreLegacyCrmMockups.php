<?php

namespace App\Console\Commands;

use App\Services\LegacyCrmMockupRestorer;
use Illuminate\Console\Command;

class RestoreLegacyCrmMockups extends Command
{
    protected $signature = 'crm:restore-legacy-mockups {--apply : Salva le associazioni mancanti}';

    protected $description = 'Collega al CRM i mockup precedenti di T-shirt e felpe, conservando le scelte manuali';

    public function handle(LegacyCrmMockupRestorer $restorer): int
    {
        $this->line(json_encode($restorer->restore((bool) $this->option('apply')), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
