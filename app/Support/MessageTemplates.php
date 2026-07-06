<?php

namespace App\Support;

class MessageTemplates
{
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
