<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\LeadController;
use App\Models\AdminUser;
use App\Models\EmailAccount;
use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            'quote_pdf_path' => 'quotes/preventivo.pdf',
            'quote_pdf_filename' => 'preventivo.pdf',
            'payment_link' => 'https://example.test/payment',
            'payment_amount' => 100,
        ]);

        $this->actingAs($owner, 'admin')
            ->get("/leads/{$lead->id}")
            ->assertOk()
            ->assertSee('Invia PDF via email')
            ->assertSee('Invia via email');
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
