<?php

namespace App\Support;

class MessageTemplates
{
    public static function forLead(\App\Models\Lead $lead): ?string
    {
        $live = (bool) $lead->live_mockup_used
            && in_array($lead->cta_origin, ['live_mockup', 'felpe_live_mockup'], true);
        $calculator = (bool) $lead->calculator_used
            && ! $lead->calculator_requires_quote
            && in_array($lead->cta_origin, ['calculator', 'felpe_calculator'], true);

        return self::initialReply($calculator, $live, $lead->isHoodieRequest());
    }

    public static function initialReply(bool $calculatorUsed = false, bool $liveMockupUsed = false, bool $hoodie = false): ?string
    {
        $title = match (true) {
            $liveMockupUsed => 'Risposta iniziale anteprima live',
            $calculatorUsed => 'Risposta iniziale calcolatore',
            default => 'Risposta iniziale',
        };

        if ($hoodie) {
            $title .= ' felpe';
        }

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
