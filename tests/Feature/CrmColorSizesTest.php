<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\CrmProduct;
use App\Models\CrmSupplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CrmColorSizesTest extends TestCase
{
    use RefreshDatabase;

    private function data(): array
    {
        return ['code' => 'BS332', 'name' => 'Outsider', 'unit_cost' => 7.68];
    }

    private function signIn(): void
    {
        $this->actingAs(AdminUser::create(['name' => 'Catalogo', 'email' => fake()->unique()->safeEmail(), 'password' => 'password', 'role' => 'owner', 'permissions' => [], 'is_active' => true]), 'admin');
    }

    public function test_explicit_and_inherited_sizes_survive_edits_and_preserve_prices(): void
    {
        $this->signIn();
        $this->post(route('admin.crm-catalog.products.store'), [
            ...$this->data(), 'variants_present' => 1, 'sizes' => ['M', '4XL', '5XL'],
            'colors' => [
                ['code' => 'BK', 'name' => 'Black', 'sizes_restricted' => 0],
                ['code' => 'BU', 'name' => 'Burgundy', 'sizes_restricted' => 1, 'sizes' => ['M', '4XL']],
                ['code' => 'WH', 'name' => 'White', 'sizes_restricted' => 1],
            ],
        ])->assertSessionHasNoErrors();
        $product = CrmProduct::firstOrFail();
        $black = $product->colors()->where('code', 'BK')->firstOrFail();
        $burgundy = $product->colors()->where('code', 'BU')->firstOrFail();
        $white = $product->colors()->where('code', 'WH')->firstOrFail();
        $this->assertSame(['M', '4XL', '5XL'], $black->sizes->pluck('name')->all());
        $this->assertSame(['M', '4XL'], $burgundy->sizes->pluck('name')->all());
        $this->assertTrue($white->sizes_restricted);
        $this->assertCount(0, $white->sizes);
        $product->priceTiers()->create(['min_quantity' => 10, 'unit_cost' => 4, 'unit_price' => 9]);
        $this->patch(route('admin.crm-catalog.products.update', $product), [
            ...$this->data(), 'sizes' => ['M', '5XL', '6XL'],
        ])->assertSessionHasNoErrors();
        $this->assertSame(['M', '5XL', '6XL'], $black->fresh()->sizes->pluck('name')->all());
        $this->assertSame(['M'], $burgundy->fresh()->sizes->pluck('name')->all());
        $this->assertCount(0, $white->fresh()->sizes);
        $this->assertSame('9.0000', $product->priceTiers()->first()->unit_price);
        $this->patch(route('admin.crm-catalog.products.update', $product), [
            ...$this->data(), 'colors_text' => "BK | Black\nBU | Burgundy\nWH | White",
        ])->assertSessionHasNoErrors();
        $this->assertTrue($burgundy->fresh()->sizes_restricted);
        $this->assertSame(['M'], $burgundy->fresh()->sizes->pluck('name')->all());
        $this->get(route('admin.crm-catalog.index'))->assertOk()->assertSee('Taglie specifiche')->assertSee('sizes_restricted');
    }

    public function test_same_size_is_allowed_for_multiple_colors_and_form_round_trip_preserves_ids(): void
    {
        $this->signIn();
        $payload = [...$this->data(), 'variants_present' => 1, 'sizes' => ['M', '5XL'], 'colors' => [
            ['code' => 'BK', 'name' => 'Black', 'sizes_restricted' => 1, 'sizes' => ['M', '5XL']],
            ['code' => 'BU', 'name' => 'Burgundy', 'sizes_restricted' => 1, 'sizes' => ['M']],
        ]];
        $this->post(route('admin.crm-catalog.products.store'), $payload)->assertSessionHasNoErrors();
        $product = CrmProduct::firstOrFail();
        $ids = $product->colors->modelKeys();
        $this->patch(route('admin.crm-catalog.products.update', $product), $payload)->assertSessionHasNoErrors();
        $this->assertSame($ids, $product->fresh()->colors->modelKeys());
        $this->assertDatabaseCount('crm_product_color_sizes', 3);
        $payload['colors'][1]['sizes'] = ['M', 'm'];
        $this->patch(route('admin.crm-catalog.products.update', $product), $payload)->assertSessionHasErrors('colors.1.sizes');
        $this->assertDatabaseCount('crm_product_color_sizes', 3);
        $payload['colors'][1] = ['code' => 'BU', 'name' => 'Burgundy', 'sizes_restricted' => 0];
        $this->patch(route('admin.crm-catalog.products.update', $product), $payload)->assertSessionHasNoErrors();
        $this->assertSame(['M', '5XL'], $product->colors()->where('code', 'BU')->firstOrFail()->sizes->pluck('name')->all());
    }

    public function test_invalid_color_sizes_roll_back_the_whole_update(): void
    {
        $this->signIn();
        $product = CrmProduct::create($this->data());
        $this->patch(route('admin.crm-catalog.products.update', $product), [
            ...$this->data(), 'name' => 'Non salvare', 'sizes' => ['M'],
            'colors' => [['code' => 'BK', 'name' => 'Black', 'sizes_restricted' => 1, 'sizes' => ['5XL']]],
        ])->assertSessionHasErrors('colors.0.sizes');
        $this->assertSame('Outsider', $product->fresh()->name);
        $this->assertDatabaseCount('crm_product_colors', 0);
        $this->assertDatabaseCount('crm_product_sizes', 0);
    }

    public function test_migration_adopts_existing_import_and_displays_supplier_pdf(): void
    {
        $product = CrmProduct::create($this->data());
        $supplier = CrmSupplier::create(['name' => 'Camac']);
        $black = $product->colors()->create(['code' => 'BK', 'name' => 'Black']);
        $burgundy = $product->colors()->create(['code' => 'BU', 'name' => 'Burgundy']);
        $legacy = $product->colors()->create(['code' => 'WH', 'name' => 'White']);
        $m = $product->sizes()->create(['name' => 'M', 'sort_order' => 0]);
        $xl5 = $product->sizes()->create(['name' => '5XL', 'sort_order' => 1]);
        $black->sizes()->sync([$m->id, $xl5->id]);
        $burgundy->sizes()->sync([$m->id]);
        DB::table('crm_product_supplier_details')->insert([
            'crm_product_id' => $product->id, 'crm_supplier_id' => $supplier->id,
            'supplier_product_name' => 'Outsider', 'composition' => '70% cotone, 30% poliestere',
            'weight_gsm' => 280, 'details' => 'Felpa con cappuccio.',
            'datasheet_url' => 'https://action-wear.com/scheda.pdf', 'source_url' => 'https://action-wear.com/bs332',
            'source_checked_at' => '2026-11-05',
        ]);
        Schema::table('crm_product_colors', fn ($table) => $table->dropColumn('sizes_restricted'));
        $migration = require database_path('migrations/2026_11_05_000001_connect_crm_supplier_details_and_color_sizes.php');
        $migration->up();
        $this->assertFalse($black->fresh()->sizes_restricted);
        $this->assertTrue($burgundy->fresh()->sizes_restricted);
        $this->assertSame(['M'], $burgundy->fresh()->sizes->pluck('name')->all());
        $this->assertSame(['M', '5XL'], $legacy->fresh()->sizes->pluck('name')->all());
        $migration->up();
        $this->assertDatabaseCount('crm_product_supplier_details', 1);
        $this->assertDatabaseCount('crm_product_color_sizes', 5);
        $this->signIn();
        $this->get(route('admin.crm-catalog.index'))->assertOk()->assertSee('Apri scheda tecnica PDF')->assertSee('https://action-wear.com/scheda.pdf', false)->assertSee('280 g/m²');
        $this->patch(route('admin.crm-catalog.products.update', $product), $this->data())->assertSessionHasNoErrors();
        $this->assertDatabaseCount('crm_product_supplier_details', 1);
        $this->assertSame(['M'], $burgundy->fresh()->sizes->pluck('name')->all());
        $reader = AdminUser::create(['name' => 'Lettore', 'email' => fake()->unique()->safeEmail(), 'password' => 'password', 'role' => 'operator', 'permissions' => ['crm_catalog.view'], 'is_active' => true]);
        $this->actingAs($reader, 'admin');
        $this->get(route('admin.crm-catalog.index'))->assertOk()->assertSee('Apri scheda tecnica PDF')->assertSee('Burgundy — M')->assertDontSee('Taglie specifiche');
        $this->patch(route('admin.crm-catalog.products.update', $product), $this->data())->assertForbidden();
        $this->signIn();
        $this->delete(route('admin.crm-catalog.products.destroy', $product))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('crm_product_supplier_details', 0);
        $this->assertDatabaseCount('crm_product_color_sizes', 0);
    }
}
