<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Services\EmailLeadWelcomeService;
use Illuminate\Console\Command;

class SendEmailLeadWelcomes extends Command
{
    protected $signature = 'email:send-lead-welcomes';

    protected $description = 'Invia la prima email automatica ai lead arrivati dal modulo email';

    public function handle(EmailLeadWelcomeService $welcome): int
    {
        $sent = 0;

        Lead::query()
            ->where('is_training', false)
            ->where('message', 'Richiesta contatto via email dalla homepage.')
            ->whereNotNull('email')
            ->whereNull('email_welcome_sent_at')
            ->orderBy('id')
            ->limit(50)
            ->get()
            ->each(function (Lead $lead) use ($welcome, &$sent) {
                $sent += $welcome->send($lead) ? 1 : 0;
            });

        $this->info("Prime email inviate: {$sent}.");

        return self::SUCCESS;
    }
}
