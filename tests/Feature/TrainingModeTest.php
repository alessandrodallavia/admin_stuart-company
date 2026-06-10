<?php

namespace Tests\Feature;

use App\Jobs\ProcessWhatsappWebhookJob;
use App\Models\AdminUser;
use App\Models\EmailAccount;
use App\Models\EmailConversation;
use App\Models\Lead;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TrainingModeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    public function test_training_mode_isolates_real_and_training_leads(): void
    {
        $operator = $this->operator();
        $realLead = $this->lead(['uuid' => 'REAL01']);
        $trainingLead = $this->lead([
            'uuid' => 'TRAIN1',
            'is_training' => true,
            'training_owner_id' => $operator->id,
        ]);
        $operator->forceFill(['training_mode_active' => false])->save();

        $this->actingAs($operator, 'admin');

        $this->assertSame([$realLead->id], Lead::pluck('id')->all());

        $operator->forceFill(['training_mode_active' => true])->save();
        $this->assertSame([$trainingLead->id], Lead::pluck('id')->all());
    }

    public function test_training_page_requests_real_contacts_for_channel_scenarios(): void
    {
        $operator = $this->operator();

        $this->actingAs($operator, 'admin')
            ->get('/training')
            ->assertOk()
            ->assertDontSee('Percorso completo')
            ->assertSee('Numero a cui scrivere')
            ->assertSee('+'.ltrim((string) config('services.whatsapp.phone_api'), '+'))
            ->assertSee('ID richiesta: &lt;ID&gt;', false)
            ->assertSee('Email reale')
            ->assertSee('senza nome e telefono');
    }

    public function test_whatsapp_training_scenario_creates_pending_lead_without_personal_data(): void
    {
        $operator = $this->operator();

        $this->actingAs($operator, 'admin')
            ->post('/training/scenarios', ['scenario' => 'whatsapp'])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/training');

        $lead = Lead::withoutGlobalScope('training')->latest()->firstOrFail();

        $this->assertNull($lead->name);
        $this->assertNull($lead->email);
        $this->assertNull($lead->phone);
        $this->assertNotNull($lead->uuid);
        $this->assertDatabaseMissing('whatsapp_conversations', ['lead_id' => $lead->id]);
        $this->assertDatabaseMissing('email_conversations', ['lead_id' => $lead->id]);
    }

    public function test_exiting_training_deletes_training_data_and_request_id(): void
    {
        $operator = $this->operator();
        $lead = $this->lead([
            'uuid' => 'RESET1',
            'is_training' => true,
            'training_owner_id' => $operator->id,
            'training_scenario' => 'whatsapp',
        ]);
        $conversation = WhatsappConversation::withoutGlobalScope('training')->create([
            'lead_id' => $lead->id,
            'contact_phone' => '393331234569',
            'mode' => 'manual',
            'status' => 'open',
            'is_training' => true,
            'training_owner_id' => $operator->id,
        ]);

        $this->actingAs($operator, 'admin')
            ->post('/training/toggle')
            ->assertRedirect('/');

        $this->assertFalse($operator->fresh()->training_mode_active);
        $this->assertDatabaseMissing('leads', ['uuid' => 'RESET1']);
        $this->assertDatabaseMissing('whatsapp_conversations', ['id' => $conversation->id]);
    }

    public function test_entering_training_does_not_delete_existing_training_data(): void
    {
        $operator = $this->operator();
        $operator->forceFill(['training_mode_active' => false])->save();
        $lead = $this->lead([
            'uuid' => 'KEEP01',
            'is_training' => true,
            'training_owner_id' => $operator->id,
        ]);

        $this->actingAs($operator, 'admin')
            ->post('/training/toggle')
            ->assertRedirect('/training');

        $this->assertTrue($operator->fresh()->training_mode_active);
        $this->assertDatabaseHas('leads', ['id' => $lead->id, 'uuid' => 'KEEP01']);
    }

    public function test_real_whatsapp_message_with_request_id_connects_to_training_lead(): void
    {
        Http::fake([
            'https://graph.facebook.com/*' => Http::response([
                'messages' => [['id' => 'training-auto-response']],
            ]),
        ]);
        $operator = $this->operator();
        $lead = $this->lead([
            'uuid' => 'TRAIN7',
            'name' => null,
            'email' => null,
            'phone' => null,
            'is_training' => true,
            'training_owner_id' => $operator->id,
            'training_scenario' => 'whatsapp',
        ]);
        $realLead = $this->lead([
            'uuid' => 'REAL07',
            'phone' => '393331234567',
        ]);
        WhatsappConversation::withoutGlobalScope('training')->create([
            'lead_id' => $realLead->id,
            'contact_phone' => $realLead->phone,
            'mode' => 'manual',
            'status' => 'open',
            'is_training' => false,
        ]);

        app()->call([new ProcessWhatsappWebhookJob($this->whatsappWebhook(
            '393331234567',
            'Ciao, questa è una prova. ID richiesta: TRAIN7 Grazie',
        )), 'handle']);

        $conversation = WhatsappConversation::withoutGlobalScope('training')->where('is_training', true)->firstOrFail();

        $this->assertTrue($conversation->is_training);
        $this->assertSame($operator->id, $conversation->training_owner_id);
        $this->assertSame($lead->id, $conversation->lead_id);
        $this->assertSame('manual', $conversation->mode);
        $this->assertTrue($conversation->needs_human);
        $this->assertSame('393331234567', $lead->fresh()->phone);
        $this->assertSame('completed', $lead->fresh()->status);
        $this->assertCount(2, WhatsappConversation::withoutGlobalScope('training')->get());
        $this->assertDatabaseHas('whatsapp_messages', [
            'whatsapp_conversation_id' => $conversation->id,
            'body' => 'Ciao, questa è una prova. ID richiesta: TRAIN7 Grazie',
            'source' => 'webhook',
        ]);
        $this->assertDatabaseHas('whatsapp_messages', [
            'whatsapp_conversation_id' => $conversation->id,
            'provider_message_id' => 'training-auto-response',
            'direction' => 'outbound',
            'source' => 'automation',
            'status' => 'sent',
        ]);
        Http::assertSentCount(1);
    }

    public function test_request_id_without_label_does_not_connect_to_training_lead(): void
    {
        Http::fake();
        $operator = $this->operator();
        $lead = $this->lead([
            'uuid' => 'TRAIN8',
            'name' => null,
            'email' => null,
            'phone' => null,
            'is_training' => true,
            'training_owner_id' => $operator->id,
            'training_scenario' => 'whatsapp',
        ]);

        app()->call([new ProcessWhatsappWebhookJob($this->whatsappWebhook('393331234568', 'TRAIN8')), 'handle']);

        $conversation = WhatsappConversation::withoutGlobalScope('training')->firstOrFail();

        $this->assertFalse($conversation->is_training);
        $this->assertNull($conversation->lead_id);
        $this->assertNull($lead->fresh()->phone);
    }

    public function test_existing_training_chat_recovers_when_automatic_reply_never_started(): void
    {
        Http::fake([
            'https://graph.facebook.com/*' => Http::response([
                'messages' => [['id' => 'training-recovered-response']],
            ]),
        ]);
        $operator = $this->operator();
        $lead = $this->lead([
            'uuid' => 'RECOV1',
            'status' => 'confirmed',
            'phone' => '393331234570',
            'is_training' => true,
            'training_owner_id' => $operator->id,
            'training_scenario' => 'whatsapp',
        ]);
        $conversation = WhatsappConversation::withoutGlobalScope('training')->create([
            'lead_id' => $lead->id,
            'contact_phone' => $lead->phone,
            'mode' => 'manual',
            'status' => 'open',
            'needs_human' => true,
            'is_training' => true,
            'training_owner_id' => $operator->id,
        ]);

        app()->call([new ProcessWhatsappWebhookJob($this->whatsappWebhook(
            $lead->phone,
            'Secondo tentativo. ID richiesta: RECOV1',
        )), 'handle']);

        $this->assertSame('completed', $lead->fresh()->status);
        $this->assertSame('manual', $conversation->fresh()->mode);
        $this->assertDatabaseHas('whatsapp_messages', [
            'whatsapp_conversation_id' => $conversation->id,
            'provider_message_id' => 'training-recovered-response',
            'source' => 'automation',
            'status' => 'sent',
        ]);
        Http::assertSentCount(1);
    }

    public function test_email_training_scenario_uses_real_email_and_leaves_name_and_phone_unknown(): void
    {
        $operator = $this->operator();
        $this->emailAccount($operator);

        $this->actingAs($operator, 'admin')
            ->post('/training/scenarios', [
                'scenario' => 'email',
                'contact_email' => 'mia-email@example.com',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $lead = Lead::withoutGlobalScope('training')->latest()->firstOrFail();
        $conversation = EmailConversation::withoutGlobalScope('training')->firstOrFail();
        $message = $conversation->messages()->firstOrFail();

        $this->assertNull($lead->name);
        $this->assertNull($lead->phone);
        $this->assertSame('mia-email@example.com', $lead->email);
        $this->assertSame($lead->email, $conversation->contact_email);
        $this->assertNull($conversation->contact_name);
        $this->assertNull($message->from_name);
        $this->assertStringNotContainsString('Giulia', $message->body_text);
        $this->assertDatabaseMissing('whatsapp_conversations', ['lead_id' => $lead->id]);
    }

    public function test_channel_training_scenarios_require_the_corresponding_real_contact(): void
    {
        $operator = $this->operator();
        $this->emailAccount($operator);

        $this->actingAs($operator, 'admin')
            ->post('/training/scenarios', ['scenario' => 'email'])
            ->assertSessionHasErrors('contact_email');

        $this->actingAs($operator, 'admin')
            ->post('/training/scenarios', ['scenario' => 'complete'])
            ->assertSessionHasErrors('scenario');
    }

    public function test_training_whatsapp_reply_never_calls_meta_api(): void
    {
        Http::fake();
        $operator = $this->operator();
        $lead = $this->lead([
            'uuid' => 'TRAIN2',
            'is_training' => true,
            'training_owner_id' => $operator->id,
        ]);
        $conversation = WhatsappConversation::withoutGlobalScope('training')->create([
            'lead_id' => $lead->id,
            'contact_phone' => $lead->phone,
            'mode' => 'manual',
            'status' => 'open',
            'is_training' => true,
            'training_owner_id' => $operator->id,
        ]);

        $this->actingAs($operator, 'admin')
            ->post("/conversations/{$conversation->id}/messages", ['message' => 'Risposta formativa'])
            ->assertRedirect();

        Http::assertNothingSent();
        $this->assertDatabaseHas('whatsapp_messages', [
            'whatsapp_conversation_id' => $conversation->id,
            'body' => 'Risposta formativa',
            'status' => 'sent',
        ]);
    }

    public function test_real_document_areas_are_blocked_during_training(): void
    {
        $operator = $this->operator();
        $operator->forceFill(['permissions' => ['documents.view']])->save();

        $this->actingAs($operator, 'admin')
            ->get('/documents')
            ->assertRedirect('/training')
            ->assertSessionHas('error');
    }

    public function test_training_payment_completion_does_not_send_external_events(): void
    {
        Http::fake();
        $operator = $this->operator();
        $lead = $this->lead([
            'uuid' => 'TRAIN3',
            'is_training' => true,
            'training_owner_id' => $operator->id,
            'payment_amount' => 350,
        ]);

        $this->actingAs($operator, 'admin')
            ->post("/training/leads/{$lead->id}/complete-payment")
            ->assertRedirect();

        Http::assertNothingSent();
        $this->assertSame('order_completed', $lead->fresh()->status);
    }

    public function test_training_email_reply_never_uses_smtp(): void
    {
        Http::fake();
        $operator = $this->operator();
        $account = $this->emailAccount($operator);
        $lead = $this->lead([
            'uuid' => 'TRAIN4',
            'is_training' => true,
            'training_owner_id' => $operator->id,
        ]);
        $conversation = EmailConversation::withoutGlobalScope('training')->create([
            'email_account_id' => $account->id,
            'lead_id' => $lead->id,
            'assigned_user_id' => $operator->id,
            'subject' => 'Email formativa',
            'contact_email' => $lead->email,
            'status' => 'open',
            'is_training' => true,
            'training_owner_id' => $operator->id,
        ]);

        $this->actingAs($operator, 'admin')
            ->post("/email/conversations/{$conversation->id}/messages", ['body' => 'Risposta email formativa'])
            ->assertRedirect();

        Http::assertNothingSent();
        $this->assertDatabaseHas('email_messages', [
            'email_conversation_id' => $conversation->id,
            'body_text' => 'Risposta email formativa',
            'status' => 'sent',
        ]);
    }

    public function test_training_quote_whatsapp_has_no_automatic_text(): void
    {
        Storage::fake('local');
        $operator = $this->operator();
        $lead = $this->lead([
            'uuid' => 'TRAIN6',
            'is_training' => true,
            'training_owner_id' => $operator->id,
        ]);
        $quotePdf = $lead->quotePdfs()->create([
            'proposal_number' => 'PROPOSTA-TRAINING-1',
            'disk' => 'local',
            'path' => 'quotes/proposta.pdf',
            'filename' => 'proposta.pdf',
            'mime_type' => 'application/pdf',
            'uploaded_at' => now(),
        ]);
        Storage::disk('local')->put($quotePdf->path, 'PDF');

        $this->actingAs($operator, 'admin')
            ->post("/leads/{$lead->id}/quote-pdfs/{$quotePdf->id}/whatsapp")
            ->assertRedirect();

        $message = WhatsappMessage::withoutGlobalScopes()->latest()->firstOrFail();

        $this->assertNull($message->body);
        $this->assertSame('document', $message->type);
    }

    public function test_training_stripe_link_is_simulated(): void
    {
        Http::fake();
        $operator = $this->operator();
        $lead = $this->lead([
            'uuid' => 'TRAIN5',
            'is_training' => true,
            'training_owner_id' => $operator->id,
        ]);
        $lead->quotePdfs()->create([
            'proposal_number' => 'TRAINING-ESTATE/A',
            'disk' => 'local',
            'path' => 'quotes/proposta-stripe.pdf',
            'filename' => 'proposta-stripe.pdf',
            'mime_type' => 'application/pdf',
            'uploaded_at' => now(),
        ]);

        $this->actingAs($operator, 'admin')
            ->post("/leads/{$lead->id}/stripe-payment-link", ['payment_amount' => 420])
            ->assertRedirect();

        Http::assertNothingSent();
        $lead->refresh();
        $this->assertSame('link_sent', $lead->status);
        $this->assertSame('TRAINING-ESTATE/A', $lead->quote_number);
        $this->assertStringStartsWith('https://checkout.stripe.test/', $lead->payment_link);
    }

    public function test_training_stripe_link_requires_a_proposal(): void
    {
        Http::fake();
        $operator = $this->operator();
        $lead = $this->lead([
            'uuid' => 'TRAIN7',
            'is_training' => true,
            'training_owner_id' => $operator->id,
        ]);

        $this->actingAs($operator, 'admin')
            ->post("/leads/{$lead->id}/stripe-payment-link", ['payment_amount' => 420])
            ->assertSessionHasErrors('proposal_pdf');

        Http::assertNothingSent();
        $this->assertNull($lead->fresh()->payment_link);
    }

    private function operator(): AdminUser
    {
        return AdminUser::create([
            'name' => 'Operatore Formazione',
            'email' => 'operatore@example.test',
            'password' => 'password-password',
            'role' => 'operator',
            'is_active' => true,
            'training_mode_enabled' => true,
            'training_mode_active' => true,
        ]);
    }

    private function lead(array $attributes = []): Lead
    {
        return Lead::withoutGlobalScope('training')->create([
            'uuid' => 'LEAD01',
            'status' => 'confirmed',
            'name' => 'Cliente Test',
            'email' => 'cliente@example.test',
            'phone' => '393330000001',
            ...$attributes,
        ]);
    }

    private function emailAccount(AdminUser $operator): EmailAccount
    {
        return EmailAccount::create([
            'admin_user_id' => $operator->id,
            'email' => 'operatore@stuart-company.test',
            'from_name' => $operator->name,
            'username' => 'operatore@stuart-company.test',
            'imap_host' => 'stuart-company.test',
            'smtp_host' => 'stuart-company.test',
            'is_active' => true,
        ]);
    }

    private function whatsappWebhook(string $from, string $body): array
    {
        return [
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'messages' => [[
                            'id' => 'wamid.'.fake()->uuid(),
                            'from' => $from,
                            'timestamp' => (string) now()->timestamp,
                            'type' => 'text',
                            'text' => ['body' => $body],
                        ]],
                    ],
                ]],
            ]],
        ];
    }
}
