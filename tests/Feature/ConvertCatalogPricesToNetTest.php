<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ConvertCatalogPricesToNetTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_previews_then_converts_catalog_prices_and_costs_only_once(): void
    {
        $now = now();
        $productId = DB::table('crm_products')->insertGetId([
            'code' => 'TEST', 'name' => 'Test', 'unit_cost' => 4, 'is_active' => true,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $printTypeId = DB::table('crm_print_types')->insertGetId([
            'code' => 'PRINT', 'name' => 'Stampa', 'is_active' => true,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('crm_product_price_tiers')->insert([
            'crm_product_id' => $productId, 'min_quantity' => 1, 'unit_cost' => 4,
            'unit_price' => 9.50, 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('crm_print_price_tiers')->insert([
            'crm_print_type_id' => $printTypeId, 'min_quantity' => 1, 'unit_cost' => 1,
            'unit_price' => 1.035, 'created_at' => $now, 'updated_at' => $now,
        ]);

        $this->artisan('catalog:prices-to-net')->assertSuccessful();
        $this->assertEqualsWithDelta(9.50, (float) DB::table('crm_product_price_tiers')->value('unit_price'), 0.0001);
        $this->assertDatabaseCount('catalog_price_conversions', 0);

        $this->artisan('catalog:prices-to-net --apply')->assertSuccessful();
        $this->assertEqualsWithDelta(7.79, (float) DB::table('crm_product_price_tiers')->value('unit_price'), 0.0001);
        $this->assertEqualsWithDelta(0.85, (float) DB::table('crm_print_price_tiers')->value('unit_price'), 0.0001);
        $this->assertEqualsWithDelta(3.28, (float) DB::table('crm_products')->value('unit_cost'), 0.0001);
        $this->assertEqualsWithDelta(3.28, (float) DB::table('crm_product_price_tiers')->value('unit_cost'), 0.0001);
        $this->assertEqualsWithDelta(0.82, (float) DB::table('crm_print_price_tiers')->value('unit_cost'), 0.0001);
        $this->assertDatabaseCount('catalog_price_conversions', 1);
        $this->assertDatabaseHas('catalog_price_conversions', [
            'product_prices_converted' => 1,
            'print_prices_converted' => 1,
            'product_costs_converted' => 2,
            'print_costs_converted' => 1,
        ]);

        $this->artisan('catalog:prices-to-net --apply')->assertFailed();
        $this->assertEqualsWithDelta(7.79, (float) DB::table('crm_product_price_tiers')->value('unit_price'), 0.0001);
    }
}
