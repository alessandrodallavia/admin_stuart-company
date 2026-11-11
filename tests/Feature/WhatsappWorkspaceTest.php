<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsappWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_keeps_inbox_filters_chat_forms_and_quote_dialog_accessible(): void
    {
        $owner = AdminUser::create(['name' => 'Demo', 'email' => 'demo@example.test', 'password' => 'password', 'role' => 'owner', 'is_active' => true]);
        $this->actingAs($owner, 'admin');
        $chat = WhatsappConversation::create(['contact_phone' => '393330001111', 'mode' => 'manual']);
        WhatsappConversation::create(['contact_phone' => '393330002222', 'mode' => 'auto', 'needs_human' => true]);
        WhatsappMessage::create(['whatsapp_conversation_id' => $chat->id, 'direction' => 'inbound', 'type' => 'text', 'body' => 'Buongiorno, vorrei un preventivo per 20 capi personalizzati. Grazie!', 'status' => 'received', 'source' => 'customer', 'received_at' => now()]);
        WhatsappMessage::create(['whatsapp_conversation_id' => $chat->id, 'direction' => 'outbound', 'type' => 'text', 'body' => "Buongiorno! Le preparo un confronto dei prodotti.\nPreferisce una stampa sul fronte oppure fronte e retro?", 'status' => 'sent', 'source' => 'admin', 'sent_at' => now()]);
        $index = $this->get(route('admin.whatsapp.index'))->assertOk()->assertSee('conversation-filter', false)->assertSee('Follow-up da fare')->getContent();
        $response = $this->get(route('admin.conversations.show', $chat))->assertOk()
            ->assertSee('Risposte rapide')->assertSee('wa-message-out')->assertSee('wa-message-in')->assertSee('data-quote-open', false);
        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertSame(1, $xpath->query('//dialog[@data-hoodie-quote]')->length);
        $this->assertSame(0, $xpath->query('//dialog[@data-hoodie-quote]//button[not(@type) or @type="submit"]')->length);
        $this->assertSame(1, $xpath->query('//textarea[@id="message-composer"]')->length);
        $this->assertSame(1, $xpath->query('//aside[@hidden][contains(@class,"wa-inbox")]')->length);
        $this->assertSame(1, $xpath->query('//section[not(@hidden)][contains(@class,"wa-chat")]')->length);
        $this->assertSame(1, $xpath->query('//section[@hidden][contains(@class,"admin-stats-grid")]')->length);
        $this->assertSame(0, $xpath->query('//form//form')->length);
        $before = WhatsappMessage::count();
        $poll = $this->getJson(route('admin.conversations.poll', $chat))->assertOk();
        $this->assertContains($chat->id, array_column($poll->json('conversations'), 'id'));
        $this->assertSame($before, WhatsappMessage::count());
        // Optional standalone previews contain synthetic test data only.
        if ($directory = getenv('STUART_WHATSAPP_PREVIEW_DIR')) {
            @mkdir($directory, 0777, true);
            file_put_contents($directory.'/inbox.html', $index);
            file_put_contents($directory.'/chat.html', $response->getContent());
        }
    }
}
