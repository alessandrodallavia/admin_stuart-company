<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCrmDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_is_the_admin_home_and_shows_crm_metrics(): void
    {
        $admin = $this->owner();
        $quoted = $this->lead([
            'name' => 'Cliente Pagato',
            'status' => 'order_completed',
            'quote_amount' => 240,
            'payment_amount' => 240,
            'margin_amount' => 120,
            'category' => 'Calcio',
            'product' => 'Kit calcio',
            'quantity' => 12,
            'lead_quality' => 'Alta',
        ]);
        $quoted->quotePdfs()->create([
            'proposal_number' => 'P-001',
            'amount' => 240,
            'uploaded_at' => now(),
        ]);
        $this->lead(['name' => 'Cliente Nuovo']);

        $this->actingAs($admin, 'admin')
            ->get('/')
            ->assertOk()
            ->assertSee('CRM Dashboard')
            ->assertSee('Cliente Pagato')
            ->assertSee('Kit calcio')
            ->assertSee('€ 240,00')
            ->assertSee('50,0%');
    }

    public function test_whatsapp_has_its_own_route(): void
    {
        $this->actingAs($this->owner(), 'admin')
            ->get('/whatsapp')
            ->assertOk()
            ->assertSee('Inbox');
    }

    public function test_crm_fields_can_be_updated_from_the_lead_page(): void
    {
        $lead = $this->lead();

        $this->actingAs($this->owner(), 'admin')
            ->patch("/leads/{$lead->id}", [
                'name' => $lead->name,
                'email' => $lead->email,
                'phone' => $lead->phone,
                'status' => 'confirmed',
                'category' => 'Basket',
                'product' => 'Divisa gara',
                'quantity' => 18,
                'lead_quality' => 'Alta',
                'loss_reason' => null,
                'crm_notes' => 'Consegna urgente',
                'margin_amount' => 350.50,
                'utm_campaign' => 'Kit Estate',
                'ad_group' => 'Calcio Veneto',
                'utm_term' => 'kit calcio',
                'search_term' => 'divise calcio personalizzate',
                'acquisition_country' => 'IT',
                'acquisition_region' => 'Veneto',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'category' => 'Basket',
            'product' => 'Divisa gara',
            'lead_quality' => 'Alta',
            'crm_notes' => 'Consegna urgente',
            'ad_group' => 'Calcio Veneto',
            'search_term' => 'divise calcio personalizzate',
            'acquisition_country' => 'IT',
            'acquisition_region' => 'Veneto',
        ]);
    }

    private function owner(): AdminUser
    {
        return AdminUser::create([
            'name' => 'Owner CRM',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'role' => 'owner',
            'is_active' => true,
        ]);
    }

    private function lead(array $attributes = []): Lead
    {
        return Lead::create([
            'uuid' => fake()->unique()->bothify('CRM####'),
            'status' => 'pre',
            'name' => 'Lead CRM',
            'email' => fake()->unique()->safeEmail(),
            'privacy_consent' => true,
            ...$attributes,
        ]);
    }
}
