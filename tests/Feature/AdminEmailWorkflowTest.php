<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\LeadController;
use App\Models\AdminUser;
use App\Models\EmailAccount;
use App\Models\EmailConversation;
use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use ReflectionMethod;
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
            ->assertSee('Follow-up pagamento');
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
            'disk' => 'local',
            'path' => 'quotes/preventivo.pdf',
            'filename' => 'preventivo.pdf',
            'mime_type' => 'application/pdf',
            'uploaded_at' => now(),
        ]);

        $this->actingAs($owner, 'admin')
            ->get("/leads/{$lead->id}")
            ->assertOk()
            ->assertSee('Preventivi PDF')
            ->assertSee('Invia via email')
            ->assertSee('min-[1280px]:grid-cols-[minmax(0,1fr)_420px]', false);
    }

    public function test_operator_can_upload_and_delete_multiple_quote_pdfs(): void
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
                'quote_pdfs' => [
                    UploadedFile::fake()->create('preventivo-a.pdf', 100, 'application/pdf'),
                    UploadedFile::fake()->create('preventivo-b.pdf', 120, 'application/pdf'),
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $pdfs = $lead->quotePdfs()->get();

        $this->assertCount(2, $pdfs);
        Storage::disk('local')->assertExists($pdfs[0]->path);
        Storage::disk('local')->assertExists($pdfs[1]->path);

        $response = $this->actingAs($operator, 'admin')->get("/leads/{$lead->id}");
        $response->assertOk()->assertSee('preventivo-a.pdf')->assertSee('preventivo-b.pdf');
        $this->assertLessThan(
            strpos($response->getContent(), 'Pagamento'),
            strpos($response->getContent(), 'Preventivi PDF'),
        );

        $this->actingAs($operator, 'admin')
            ->delete("/leads/{$lead->id}/quote-pdfs/{$pdfs[0]->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('lead_quote_pdfs', ['id' => $pdfs[0]->id]);
        Storage::disk('local')->assertMissing($pdfs[0]->path);
        $this->assertDatabaseHas('lead_quote_pdfs', ['id' => $pdfs[1]->id]);
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

    public function test_lead_quote_number_uses_customer_facing_format(): void
    {
        $lead = Lead::create([
            'uuid' => 'QUOTE1',
            'status' => 'confirmed',
            'name' => 'Cliente Preventivo',
            'email' => 'preventivo@example.test',
        ]);

        $method = new ReflectionMethod(LeadController::class, 'ensureQuoteNumber');
        $quoteNumber = $method->invoke(new LeadController, $lead);

        $this->assertSame(sprintf('Preventivo nr. %04d', $lead->id), $quoteNumber);
        $this->assertSame($quoteNumber, $lead->fresh()->quote_number);
    }

    public function test_payment_link_email_is_plain_and_mentions_bank_transfer_proforma(): void
    {
        $html = view('emails.lead-payment-link', [
            'amount' => '100,00',
            'paymentLink' => 'https://example.test/payment',
            'quoteNumber' => 'Preventivo nr. 0010',
        ])->render();

        $this->assertStringContainsString('Paga ora', $html);
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
