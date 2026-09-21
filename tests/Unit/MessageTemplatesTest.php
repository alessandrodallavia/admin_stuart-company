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
}
