<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\CrmProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CrmProductSlugTest extends TestCase
{
    use RefreshDatabase;

    public function test_slugs_are_generated_without_ids_and_remain_stable_when_renaming_products(): void
    {
        $first = CrmProduct::create(['code' => 'SKU A', 'name' => 'Felpa Caffè', 'unit_cost' => 5]);
        $second = CrmProduct::create(['code' => 'SKU-A', 'name' => 'Felpa Caffè', 'unit_cost' => 5]);
        $this->assertSame('felpa-caffe-sku-a', $first->slug);
        $this->assertSame('felpa-caffe-sku-a-2', $second->slug);
        $first->update(['name' => 'Nuovo nome', 'code' => 'ALTRO']);
        $this->assertSame('felpa-caffe-sku-a', $first->fresh()->slug);
    }

    public function test_admin_can_edit_the_slug_and_cannot_use_duplicate_or_unsafe_urls(): void
    {
        $this->actingAs(AdminUser::create(['name' => 'Catalogo', 'email' => 'slugs@example.test', 'password' => 'password', 'role' => 'owner', 'is_active' => true]), 'admin');
        $product = CrmProduct::create(['code' => 'F01', 'name' => 'Felpa', 'unit_cost' => 5]);
        CrmProduct::create(['code' => 'F02', 'name' => 'Altra', 'unit_cost' => 5, 'slug' => 'occupato']);
        $data = ['code' => 'F01', 'name' => 'Felpa', 'unit_cost' => 5];
        $this->patch(route('admin.crm-catalog.products.update', $product), $data + ['slug' => 'felpa-personalizzata'])->assertSessionHasNoErrors();
        foreach (['occupato', '../felpa', 'FELPA', '123'] as $slug) {
            $this->patch(route('admin.crm-catalog.products.update', $product), $data + ['slug' => $slug])->assertSessionHasErrors('slug');
        }
        $this->patch(route('admin.crm-catalog.products.update', $product), $data + ['slug' => ''])->assertSessionHasNoErrors();
        $this->assertSame('felpa-personalizzata', $product->fresh()->slug);
        $this->get(route('admin.crm-catalog.index'))->assertOk()->assertSee('Slug URL prodotto')->assertSee('felpa-personalizzata');
    }

    public function test_migration_fills_existing_products_without_changing_prices_and_handles_collisions(): void
    {
        $migration = require database_path('migrations/2026_11_24_000002_add_slug_to_crm_products.php');
        $migration->down();
        $ids = [];
        foreach (['SKU A', 'SKU-A'] as $code) {
            $ids[] = DB::table('crm_products')->insertGetId(['code' => $code, 'name' => 'Felpa Caffè', 'unit_cost' => 5]);
        }
        DB::table('crm_product_price_tiers')->insert(['crm_product_id' => $ids[0], 'min_quantity' => 10, 'unit_cost' => 5, 'unit_price' => 12]);
        $migration->up();
        $this->assertSame(['felpa-caffe-sku-a', 'felpa-caffe-sku-a-2'], DB::table('crm_products')->orderBy('id')->pluck('slug')->all());
        $this->assertEquals(12, DB::table('crm_product_price_tiers')->value('unit_price'));
    }
}
