<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\EmailAccount;
use App\Models\EmailConversation;
use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminEmailWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_can_open_email_area(): void
    {
        $operator = $this->operator();

        $this->actingAs($operator, 'admin')
            ->get('/email')
            ->assertOk()
            ->assertSee('Posta clienti')
            ->assertSee('Configura la tua casella');
    }

    public function test_owner_can_configure_an_operator_email_account(): void
    {
        $operator = $this->operator();
        $owner = AdminUser::create([
            'name' => 'Owner Test',
            'email' => 'owner@example.test',
            'password' => 'password',
            'role' => 'owner',
            'is_active' => true,
        ]);

        $response = $this->actingAs($owner, 'admin')->put("/settings/users/{$operator->id}/email-account", [
            'email_account' => [
                'email' => 'andrea@stuart-company.com',
                'from_name' => 'Andrea',
                'username' => 'andrea@stuart-company.com',
                'password' => 'secret-password',
                'imap_host' => 'stuart-company.com',
                'imap_port' => 993,
                'imap_encryption' => 'ssl',
                'smtp_host' => 'stuart-company.com',
                'smtp_port' => 465,
                'smtp_encryption' => 'ssl',
                'sync_folder' => 'INBOX',
                'is_active' => true,
            ],
        ]);

        $response->assertSessionHasNoErrors()->assertSessionHas('status');

        $account = EmailAccount::firstOrFail();
        $this->assertSame($operator->id, $account->admin_user_id);
        $this->assertSame('andrea@stuart-company.com', $account->email);
        $this->assertSame('secret-password', $account->password());
    }

    public function test_operator_cannot_configure_email_accounts(): void
    {
        $operator = $this->operator();
        AdminUser::create([
            'name' => 'Owner Test',
            'email' => 'owner@example.test',
            'password' => 'password',
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->actingAs($operator, 'admin')
            ->put("/settings/users/{$operator->id}/email-account", [])
            ->assertForbidden();
    }

    public function test_email_area_shows_the_shared_quick_replies(): void
    {
        $operator = $this->operator();

        $this->actingAs($operator, 'admin')
            ->get('/email')
            ->assertOk()
            ->assertSee('Risposta iniziale')
            ->assertSee('Follow-up 4 dopo link');
    }

    public function test_lead_area_shows_email_actions_for_quote_and_payment(): void
    {
        $owner = AdminUser::create([
            'name' => 'Owner Test',
            'email' => 'owner@example.test',
            'password' => 'password',
            'role' => 'owner',
            'is_active' => true,
        ]);
        $lead = Lead::create([
            'uuid' => 'EMAIL1',
            'status' => 'link_sent',
            'name' => 'Cliente Email',
            'email' => 'cliente@example.test',
            'payment_link' => 'https://example.test/payment',
            'payment_amount' => 100,
        ]);
        $lead->quotePdfs()->create([
            'proposal_number' => 'PROPOSTA-EMAIL-1',
            'amount' => 100,
            'disk' => 'local',
            'path' => 'quotes/proposta.pdf',
            'filename' => 'proposta.pdf',
            'mime_type' => 'application/pdf',
            'uploaded_at' => now(),
        ]);

        $this->actingAs($owner, 'admin')
            ->get("/leads/{$lead->id}")
            ->assertOk()
            ->assertSee('Proposte')
            ->assertSee('Invia via email')
            ->assertSee('lg:grid-cols-2', false)
            ->assertSee('Torna ai lead');
    }

    public function test_operator_can_upload_and_delete_multiple_proposals(): void
    {
        Storage::fake('local');
        Notification::fake();
        $operator = $this->operator();
        $lead = Lead::create([
            'uuid' => 'PDFS01',
            'status' => 'confirmed',
            'name' => 'Cliente PDF',
            'email' => 'pdf@example.test',
        ]);

        $this->actingAs($operator, 'admin')
            ->post("/leads/{$lead->id}/quote-pdfs", [
                'proposal_number' => 'ESTATE-24/A',
                'proposal_amount' => 150,
                'proposal_pdf' => UploadedFile::fake()->create('proposta-a.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->actingAs($operator, 'admin')
            ->post("/leads/{$lead->id}/quote-pdfs", [
                'proposal_number' => 'ESTATE-24/B',
                'proposal_amount' => 220.50,
                'proposal_pdf' => UploadedFile::fake()->create('proposta-b.pdf', 120, 'application/pdf'),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $pdfs = $lead->quotePdfs()->get();

        $this->assertCount(2, $pdfs);
        $this->assertSame(['ESTATE-24/B', 'ESTATE-24/A'], $pdfs->pluck('proposal_number')->all());
        $this->assertSame(['220.50', '150.00'], $pdfs->pluck('amount')->all());
        $this->assertSame('ESTATE-24/B', $lead->fresh()->quote_number);
        $this->assertSame('220.50', $lead->fresh()->quote_amount);
        Storage::disk('local')->assertExists($pdfs[0]->path);
        Storage::disk('local')->assertExists($pdfs[1]->path);

        $response = $this->actingAs($operator, 'admin')->get("/leads/{$lead->id}");
        $response->assertOk()
            ->assertSee('ESTATE-24/A')
            ->assertSee('ESTATE-24/B')
            ->assertSee('proposta-a.pdf')
            ->assertSee('proposta-b.pdf');
        $this->assertLessThan(
            strpos($response->getContent(), 'Pagamento'),
            strpos($response->getContent(), 'Proposte'),
        );

        $this->actingAs($operator, 'admin')
            ->delete("/leads/{$lead->id}/quote-pdfs/{$pdfs[0]->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('lead_quote_pdfs', ['id' => $pdfs[0]->id]);
        Storage::disk('local')->assertMissing($pdfs[0]->path);
        $this->assertDatabaseHas('lead_quote_pdfs', ['id' => $pdfs[1]->id]);
        $this->assertSame('ESTATE-24/A', $lead->fresh()->quote_number);
        $this->assertSame('150.00', $lead->fresh()->quote_amount);
    }

    public function test_email_signature_contains_user_company_fixed_phone_and_no_address(): void
    {
        $html = view('emails.partials.signature', [
            'name' => 'Andrea Dalla Via',
            'company' => config('email_signature.company'),
            'phone' => config('email_signature.phone'),
            'email' => 'andrea@stuart-company.com',
            'website' => config('email_signature.website'),
            'logoUrl' => config('email_signature.logo_url'),
            'disclaimer' => config('email_signature.disclaimer'),
        ])->render();

        $this->assertStringContainsString('Andrea Dalla Via', $html);
        $this->assertStringContainsString('Stuart Company', $html);
        $this->assertStringContainsString('+39 049 73 88 277', $html);
        $this->assertStringContainsString('logo-stuart.png', $html);
        $this->assertStringNotContainsString('Via Santa Lucia', $html);
    }

    public function test_proposal_number_is_required_and_can_be_free_form(): void
    {
        Storage::fake('local');
        Notification::fake();
        $operator = $this->operator();
        $lead = Lead::create([
            'uuid' => 'QUOTE1',
            'status' => 'confirmed',
            'name' => 'Cliente Proposta',
            'email' => 'proposta@example.test',
        ]);

        $this->actingAs($operator, 'admin')
            ->post("/leads/{$lead->id}/quote-pdfs", [
                'proposal_amount' => 100,
                'proposal_pdf' => UploadedFile::fake()->create('proposta.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasErrors('proposal_number');

        $this->actingAs($operator, 'admin')
            ->post("/leads/{$lead->id}/quote-pdfs", [
                'proposal_number' => 'Senza importo',
                'proposal_pdf' => UploadedFile::fake()->create('proposta.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasErrors('proposal_amount');

        $this->actingAs($operator, 'admin')
            ->post("/leads/{$lead->id}/quote-pdfs", [
                'proposal_number' => 'Collezione Estate / versione A',
                'proposal_amount' => 375.25,
                'proposal_pdf' => UploadedFile::fake()->create('proposta.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('lead_quote_pdfs', [
            'lead_id' => $lead->id,
            'proposal_number' => 'Collezione Estate / versione A',
            'amount' => 375.25,
        ]);
        $this->assertSame('Collezione Estate / versione A', $lead->fresh()->quote_number);
        $this->assertSame('375.25', $lead->fresh()->quote_amount);
    }

    public function test_operator_can_save_proposal_without_pdf(): void
    {
        Storage::fake('local');
        Notification::fake();
        $operator = $this->operator();
        $lead = Lead::create([
            'uuid' => 'QUOTE-NO-PDF',
            'status' => 'confirmed',
            'name' => 'Cliente senza PDF',
            'email' => 'nopdf@example.test',
        ]);

        $this->actingAs($operator, 'admin')
            ->post("/leads/{$lead->id}/quote-pdfs", [
                'proposal_number' => 'PROPOSTA-SENZA-PDF',
                'proposal_amount' => 180,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $proposal = $lead->quotePdfs()->firstOrFail();

        $this->assertSame('PROPOSTA-SENZA-PDF', $proposal->proposal_number);
        $this->assertSame('180.00', $proposal->amount);
        $this->assertNull($proposal->path);
        $this->assertNull($proposal->filename);
        $this->assertSame('PROPOSTA-SENZA-PDF', $lead->fresh()->quote_number);
        $this->assertSame('180.00', $lead->fresh()->quote_amount);

        $this->actingAs($operator, 'admin')
            ->get("/leads/{$lead->id}")
            ->assertOk()
            ->assertSee('Nessun PDF allegato');
    }

    public function test_payment_link_email_is_plain_and_mentions_bank_transfer_proforma(): void
    {
        $html = view('emails.lead-payment-link', [
            'amount' => '100,00',
            'paymentLink' => 'https://example.test/payment',
            'quoteNumber' => 'PROPOSTA-0010',
        ])->render();

        $this->assertStringContainsString('Paga ora', $html);
        $this->assertStringContainsString('Importo proposta', $html);
        $this->assertStringContainsString('bonifico bancario', $html);
        $this->assertStringContainsString('proforma con tutti i dettagli per il pagamento', $html);
        $this->assertStringNotContainsString('#f8f8f8', $html);
        $this->assertStringNotContainsString('border:1px solid', $html);
    }

    public function test_operator_can_mark_an_email_conversation_as_unread(): void
    {
        [$operator, $conversation] = $this->emailConversation();
        $message = $conversation->messages()->create([
            'direction' => 'inbound',
            'status' => 'received',
            'from_email' => 'cliente@example.test',
            'subject' => 'Richiesta',
            'body_text' => 'Messaggio cliente',
            'seen_at' => now(),
            'received_at' => now(),
        ]);

        $this->actingAs($operator, 'admin')
            ->patch("/email/conversations/{$conversation->id}/mark-unread")
            ->assertRedirect('/email');

        $this->assertNull($message->fresh()->seen_at);
        $this->assertFalse($conversation->fresh()->is_seen);
    }

    public function test_operator_can_remove_an_email_conversation_from_the_panel(): void
    {
        [$operator, $conversation] = $this->emailConversation();

        $this->actingAs($operator, 'admin')
            ->delete("/email/conversations/{$conversation->id}")
            ->assertRedirect('/email');

        $this->assertSame('deleted', $conversation->fresh()->status);

        $this->actingAs($operator, 'admin')
            ->get('/email')
            ->assertDontSee($conversation->subject);
    }

    private function emailConversation(): array
    {
        $operator = $this->operator();
        $account = EmailAccount::create([
            'admin_user_id' => $operator->id,
            'email' => 'andrea@example.test',
            'from_name' => 'Andrea Test',
            'username' => 'andrea@example.test',
            'password_encrypted' => encrypt('secret-password'),
            'imap_host' => 'stuart-company.com',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'smtp_host' => 'stuart-company.com',
            'smtp_port' => 465,
            'smtp_encryption' => 'ssl',
            'sync_folder' => 'INBOX',
            'is_active' => true,
        ]);
        $conversation = EmailConversation::create([
            'email_account_id' => $account->id,
            'assigned_user_id' => $operator->id,
            'subject' => 'Conversazione da gestire',
            'contact_email' => 'cliente@example.test',
            'status' => 'open',
            'is_seen' => true,
            'last_message_at' => now(),
        ]);

        return [$operator, $conversation];
    }

    private function operator(): AdminUser
    {
        return AdminUser::create([
            'name' => 'Andrea Test',
            'email' => 'andrea@example.test',
            'password' => 'password',
            'role' => 'operator',
            'is_active' => true,
        ]);
    }
}
