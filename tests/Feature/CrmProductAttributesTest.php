<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\CrmProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmProductAttributesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(AdminUser::create(['name' => 'Catalogo', 'email' => 'attributi@example.test', 'password' => 'password', 'role' => 'owner', 'is_active' => true]), 'admin');
    }

    public function test_admin_can_populate_filter_attributes_and_legacy_edits_preserve_them(): void
    {
        $data = ['code' => 'F01', 'name' => 'Felpa', 'unit_cost' => 5];
        $this->post(route('admin.crm-catalog.products.store'), $data + [
            'catalog_fit' => ' Regular ', 'catalog_model' => 'Cappuccio', 'catalog_gender' => 'Unisex', 'catalog_range' => 'Basic',
            'catalog_weight_gsm' => 280, 'catalog_composition' => 'Cotone', 'catalog_dye' => 'Classica',
            'catalog_usage_text' => "Azienda, Sport\nEventi, Sport",
        ])->assertSessionHasNoErrors();
        $product = CrmProduct::sole();
        $this->assertSame('Regular', $product->catalog_fit);
        $this->assertSame(['Azienda', 'Sport', 'Eventi'], $product->catalog_usage);
        $this->assertSame(280, $product->catalog_weight_gsm);
        $tier = $product->priceTiers()->create(['min_quantity' => 10, 'unit_cost' => 5, 'unit_price' => 12]);
        $this->patch(route('admin.crm-catalog.products.update', $product), $data)->assertSessionHasNoErrors();
        $this->assertSame('Regular', $product->fresh()->catalog_fit);
        $this->assertSame('12.0000', $tier->fresh()->unit_price);
        $this->get(route('admin.crm-catalog.index'))->assertOk()->assertSee('Vestibilità')->assertSee('catalog_fit', false)->assertSee('Azienda, Sport, Eventi');
        $this->patch(route('admin.crm-catalog.products.update', $product), $data + ['catalog_fit' => '', 'catalog_usage_text' => ''])->assertSessionHasNoErrors();
        $this->assertNull($product->fresh()->catalog_fit);
        $this->assertSame([], $product->fresh()->catalog_usage);
    }

    public function test_invalid_filter_attributes_do_not_create_partial_products(): void
    {
        $data = ['code' => 'F01', 'name' => 'Felpa', 'unit_cost' => 5];
        $this->post(route('admin.crm-catalog.products.store'), $data + ['catalog_weight_gsm' => -1])->assertSessionHasErrors('catalog_weight_gsm');
        $this->post(route('admin.crm-catalog.products.store'), $data + ['catalog_usage_text' => implode(',', range(1, 21))])->assertSessionHasErrors('catalog_usage_text');
        $this->post(route('admin.crm-catalog.products.store'), $data + ['catalog_fit' => ['Regular']])->assertSessionHasErrors('catalog_fit');
        $this->assertDatabaseCount('crm_products', 0);
    }
}
