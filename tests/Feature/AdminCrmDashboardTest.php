<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Lead;
use App\Models\LeadCategory;
use App\Models\CrmProduct;
use App\Models\CrmPrintType;
use App\Livewire\Admin\LeadSalesSheet as LeadSalesSheetComponent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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
        $category = LeadCategory::create(['name' => 'Basket', 'sort_order' => 1, 'is_active' => true]);

        $this->actingAs($this->owner(), 'admin')
            ->patch("/leads/{$lead->id}", [
                'name' => $lead->name,
                'email' => $lead->email,
                'phone' => $lead->phone,
                'status' => 'confirmed',
                'lead_category_id' => $category->id,
                'lead_quality' => 'Alta',
                'loss_reason' => null,
                'crm_notes' => 'Consegna urgente',
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
            'lead_category_id' => $category->id,
            'lead_quality' => 'Alta',
            'crm_notes' => 'Consegna urgente',
        ]);
        $this->assertNull($lead->fresh()->ad_group);
        $this->assertNull($lead->fresh()->search_term);
        $this->assertNull($lead->fresh()->acquisition_country);
        $this->assertNull($lead->fresh()->acquisition_region);
    }

    public function test_sales_sheet_calculates_product_print_and_margin(): void
    {
        $admin = $this->owner();
        $lead = $this->lead();
        $product = CrmProduct::create(['code'=>'TS01','name'=>'T-shirt','unit_cost'=>5,'is_active'=>true]);
        $product->priceTiers()->create(['min_quantity'=>10,'max_quantity'=>19,'unit_price'=>12]);
        $print = CrmPrintType::create(['code'=>'CUORE1','name'=>'Lato cuore 1 colore','is_active'=>true]);
        $print->priceTiers()->create(['min_quantity'=>10,'max_quantity'=>19,'unit_cost'=>1,'unit_price'=>3]);

        $this->actingAs($admin,'admin')->post("/leads/{$lead->id}/sales-sheet/items",['product_id'=>$product->id,'quantity'=>12])->assertSessionHasNoErrors();
        $item=$lead->fresh()->salesSheet->items()->firstOrFail();
        $this->actingAs($admin,'admin')->post("/leads/{$lead->id}/sales-sheet/items/{$item->id}/prints",['print_type_id'=>$print->id])->assertSessionHasNoErrors();

        $sheet=$lead->fresh()->salesSheet;
        $this->assertSame('180.00',$sheet->revenue_total);
        $this->assertSame('72.00',$sheet->cost_total);
        $this->assertSame('108.00',$sheet->margin_total);
        $this->assertSame('108.00',$lead->fresh()->margin_amount);
    }

    public function test_sales_sheet_can_be_updated_with_livewire_without_reloading_the_lead_page(): void
    {
        $this->actingAs($this->owner(), 'admin');
        $lead = $this->lead();
        $product = CrmProduct::create(['code' => 'POLO01', 'name' => 'Polo', 'unit_cost' => 8, 'is_active' => true]);
        $product->priceTiers()->create(['min_quantity' => 1, 'max_quantity' => 20, 'unit_price' => 18]);

        Livewire::test(LeadSalesSheetComponent::class, ['leadId' => $lead->id])
            ->set('productId', (string) $product->id)
            ->set('configurationName', 'Polo staff evento')
            ->set('quantity', '5')
            ->call('addProduct')
            ->assertHasNoErrors()
            ->assertSee('Prodotto aggiunto alla scheda vendita.')
            ->assertSee('90,00');

        $this->assertDatabaseHas('lead_sales_items', [
            'product_code' => 'POLO01',
            'configuration_name' => 'Polo staff evento',
            'quantity' => 5,
            'revenue_total' => 90,
        ]);
        $this->assertSame('Polo staff evento', $lead->fresh()->product);
    }

    public function test_categories_can_be_disabled_and_only_unused_categories_can_be_deleted(): void
    {
        $admin = $this->owner();
        $used = LeadCategory::create(['name' => 'Categoria usata', 'sort_order' => 10, 'is_active' => true]);
        $unused = LeadCategory::create(['name' => 'Categoria libera', 'sort_order' => 20, 'is_active' => true]);
        $this->lead(['lead_category_id' => $used->id, 'category' => $used->name]);

        $this->actingAs($admin, 'admin')
            ->patch("/settings/crm-catalog/categories/{$used->id}/toggle")
            ->assertSessionHasNoErrors();
        $this->assertFalse($used->fresh()->is_active);

        $this->actingAs($admin, 'admin')
            ->delete("/settings/crm-catalog/categories/{$used->id}")
            ->assertSessionHasErrors('category');
        $this->assertDatabaseHas('lead_categories', ['id' => $used->id]);

        $this->actingAs($admin, 'admin')
            ->delete("/settings/crm-catalog/categories/{$unused->id}")
            ->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('lead_categories', ['id' => $unused->id]);
    }

    public function test_operator_can_be_given_read_only_access_to_the_crm_catalog(): void
    {
        $operator = $this->operator(['crm_catalog.view']);

        $this->actingAs($operator, 'admin')
            ->get('/settings/crm-catalog')
            ->assertOk()
            ->assertSee('Catalogo CRM')
            ->assertDontSee('Aggiungi categoria');

        $this->actingAs($operator, 'admin')
            ->post('/settings/crm-catalog/categories', ['name' => 'Non autorizzata'])
            ->assertForbidden();
    }

    public function test_operator_can_be_given_management_access_to_the_crm_catalog(): void
    {
        $operator = $this->operator(['crm_catalog.view', 'crm_catalog.manage']);

        $this->actingAs($operator, 'admin')
            ->get('/settings/crm-catalog')
            ->assertOk()
            ->assertSee('Aggiungi categoria');

        $this->actingAs($operator, 'admin')
            ->post('/settings/crm-catalog/categories', ['name' => 'Merchandising', 'sort_order' => 5])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('lead_categories', ['name' => 'Merchandising']);
    }

    public function test_operator_without_catalog_permissions_cannot_access_it(): void
    {
        $this->actingAs($this->operator(), 'admin')
            ->get('/settings/crm-catalog')
            ->assertForbidden();
    }

    public function test_catalog_permissions_can_be_assigned_from_the_admin_user_form(): void
    {
        $this->actingAs($this->owner(), 'admin')
            ->post('/settings/users', [
                'name' => 'Operatore Catalogo',
                'email' => 'catalogo@example.com',
                'password' => 'password-sicura',
                'password_confirmation' => 'password-sicura',
                'role' => 'operator',
                'permissions' => ['crm_catalog.view', 'crm_catalog.manage'],
                'is_active' => '1',
            ])
            ->assertSessionHasNoErrors();

        $operator = AdminUser::where('email', 'catalogo@example.com')->firstOrFail();

        $this->assertSame(['crm_catalog.view', 'crm_catalog.manage'], $operator->permissions);
    }

    public function test_obsolete_permissions_do_not_block_updating_an_operator(): void
    {
        $operator = $this->operator(['legacy.permission']);

        $this->actingAs($this->owner(), 'admin')
            ->patch("/settings/users/{$operator->id}", [
                'name' => $operator->name,
                'email' => $operator->email,
                'role' => 'operator',
                'permissions' => ['legacy.permission', 'crm_catalog.view', 'crm_catalog.manage'],
                'is_active' => '1',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            ['crm_catalog.view', 'crm_catalog.manage'],
            $operator->fresh()->permissions,
        );
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

    private function operator(array $permissions = []): AdminUser
    {
        return AdminUser::create([
            'name' => 'Operatore CRM',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'role' => 'operator',
            'permissions' => $permissions,
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
