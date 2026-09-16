<?php

namespace App\Support;

class MessageTemplates
{
    public static function initialReply(bool $calculatorUsed = false): ?string
    {
        $title = $calculatorUsed ? 'Risposta iniziale calcolatore' : 'Risposta iniziale';

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
