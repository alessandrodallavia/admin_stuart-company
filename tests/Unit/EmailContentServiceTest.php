<?php

namespace Tests\Unit;

use App\Services\EmailContentService;
use PHPUnit\Framework\TestCase;

class EmailContentServiceTest extends TestCase
{
    public function test_it_removes_outlook_quoted_text(): void
    {
        $content = new EmailContentService;
        $text = "Risposta di prova a pannello, test numero 1\n\nDa: Alessandro <alessandro@stuart-company.com>\nData: lunedì, 8 giugno 2026 alle ore 14:58\nA: Alessandro Dalla Via <ale@example.com>\nOggetto: Prova da pannello nr 1\n\nE-mail precedente";

        $this->assertSame('Risposta di prova a pannello, test numero 1', $content->cleanText($text));
    }

    public function test_it_removes_outlook_reply_block_but_preserves_new_html(): void
    {
        $content = new EmailContentService;
        $html = '<div style="color:red">Nuova risposta</div><div id="divRplyFwdMsg"><hr><div>Da: Cliente</div><div>Messaggio precedente</div></div>';
        $cleaned = $content->cleanHtml($html);

        $this->assertStringContainsString('Nuova risposta', $cleaned);
        $this->assertStringNotContainsString('Messaggio precedente', $cleaned);
    }

    public function test_it_preserves_a_normal_html_template(): void
    {
        $content = new EmailContentService;
        $html = '<table><tr><td style="color:blue">Contenuto template</td></tr></table>';

        $this->assertStringContainsString('Contenuto template', $content->cleanHtml($html));
        $this->assertStringContainsString('table', $content->cleanHtml($html));
    }
}
