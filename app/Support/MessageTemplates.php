<?php

namespace App\Support;

class MessageTemplates
{
    public static function initialReply(bool $calculatorUsed = false, bool $liveMockupUsed = false): ?string
    {
        $title = match (true) {
            $liveMockupUsed => 'Risposta iniziale anteprima live',
            $calculatorUsed => 'Risposta iniziale calcolatore',
            default => 'Risposta iniziale',
        };

        $message = collect(self::current())->firstWhere('title', $title)['message'] ?? null;

        return is_string($message) && trim($message) !== '' ? $message : null;
    }

    public static function current(): array
    {
        return config('message_templates.'.self::currentPeriod(), config('message_templates.morning', []));
    }

    public static function currentPeriod(): string
    {
        return now(config('app.display_timezone', 'Europe/Rome'))->hour < 18
            ? 'morning'
            : 'evening';
    }
}
