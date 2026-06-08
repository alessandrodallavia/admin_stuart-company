<?php

namespace App\Console\Commands;

use App\Models\EmailMessage;
use App\Services\EmailContentService;
use Illuminate\Console\Command;

class CleanEmailContent extends Command
{
    protected $signature = 'email:clean-content';

    protected $description = 'Rimuove dalle email ricevute le risposte precedenti citate';

    public function handle(EmailContentService $content): int
    {
        $updated = 0;

        EmailMessage::query()
            ->where('direction', 'inbound')
            ->orderBy('id')
            ->chunkById(100, function ($messages) use ($content, &$updated) {
                foreach ($messages as $message) {
                    $bodyText = $content->cleanText($message->body_text);
                    $bodyHtml = $content->cleanHtml($message->body_html);

                    if ($bodyText !== $message->body_text || $bodyHtml !== $message->body_html) {
                        $message->forceFill([
                            'body_text' => $bodyText,
                            'body_html' => $bodyHtml,
                        ])->save();
                        $updated++;
                    }
                }
            });

        $this->info("Email ripulite: {$updated}.");

        return self::SUCCESS;
    }
}
