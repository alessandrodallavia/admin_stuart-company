<?php

namespace Tests\Unit;

use App\Support\MessageTemplates;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MessageTemplatesTest extends TestCase
{
    public function test_it_selects_the_calculator_initial_reply(): void
    {
        Carbon::setTestNow('2026-09-16 10:00:00');

        $message = MessageTemplates::initialReply(true);

        $this->assertStringStartsWith('Buongiorno!', $message);
        $this->assertStringContainsString('Perfetto, ho ricevuto la sua richiesta.', $message);
        $this->assertStringContainsString("il logo o la grafica da utilizzare", $message);

        Carbon::setTestNow();
    }

    public function test_it_keeps_the_general_reply_for_non_calculator_leads(): void
    {
        Carbon::setTestNow('2026-09-16 18:00:00');

        $message = MessageTemplates::initialReply(false);

        $this->assertStringStartsWith('Buonasera!', $message);
        $this->assertStringContainsString('la quantità indicativa', $message);
        $this->assertStringNotContainsString('ho ricevuto la sua richiesta', $message);

        Carbon::setTestNow();
    }

    public function test_it_selects_the_dedicated_live_mockup_reply(): void
    {
        Carbon::setTestNow('2026-09-16 10:00:00');

        $message = MessageTemplates::initialReply(true, true);

        $this->assertStringStartsWith('Buongiorno!', $message);
        $this->assertStringContainsString('ho ricevuto il suo progetto e le grafiche caricate', $message);
        $this->assertStringContainsString('rimozione di eventuali sfondi', $message);
        $this->assertStringContainsString('Solo dopo la sua approvazione', $message);
        $this->assertStringNotContainsString('mi invii semplicemente', $message);

        Carbon::setTestNow();
    }

    public function test_hoodie_replies_are_separate_in_both_time_periods(): void
    {
        foreach (['10:00:00', '19:00:00'] as $time) {
            Carbon::setTestNow('2026-10-05 '.$time);
            $this->assertStringContainsString('ordine minimo 10 pezzi', MessageTemplates::initialReply(false, false, true));
            $this->assertStringContainsString('ordine minimo 15 pezzi', MessageTemplates::initialReply(false));
            $this->assertStringContainsString('colore della felpa', MessageTemplates::initialReply(true, false, true));
            $this->assertStringContainsString('colore della t-shirt', MessageTemplates::initialReply(true));
            $this->assertStringContainsString('grafiche caricate', MessageTemplates::initialReply(true, true, true));
        }
        Carbon::setTestNow();
    }

    public function test_hoodie_requests_are_identified_by_origin_page_or_email(): void
    {
        foreach ([['cta_origin' => 'felpe_live_mockup'], ['landing_page' => 'https://stuart-company.com/felpe-personalizzate?utm_source=test'], ['message' => 'Richiesta contatto via email dalla landing felpe.']] as $attributes) {
            $this->assertTrue((new \App\Models\Lead($attributes))->isHoodieRequest());
        }
        $this->assertFalse((new \App\Models\Lead(['cta_origin' => 'live_mockup']))->isHoodieRequest());
    }

    public function test_price_communication_uses_the_complete_forty_piece_standard(): void
    {
        $message = collect(MessageTemplates::current())->firstWhere('title', 'Comunicazione del prezzo')['message'];

        $this->assertStringContainsString('I prezzi si riferiscono a 40 pezzi', $message);
        $this->assertStringContainsString('Totale 40 pz: EUR 219,60 + IVA - EUR 267,91 IVA inclusa', $message);
        $this->assertStringContainsString('Totale 40 pz: EUR 275,60 + IVA - EUR 336,23 IVA inclusa', $message);
        $this->assertStringContainsString('Totale 40 pz: EUR 394,00 + IVA - EUR 480,68 IVA inclusa', $message);
        $this->assertStringContainsString('https://stuart-company.com/#calcolatore-prezzo', $message);
        $this->assertStringNotContainsString('100 pezzi', $message);
    }
}
