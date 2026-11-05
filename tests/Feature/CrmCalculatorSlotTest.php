<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\CrmCalculatorSlot;
use App\Models\CrmPrintType;
use App\Models\CrmProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmCalculatorSlotTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): AdminUser
    {
        return AdminUser::create(['name' => 'Owner', 'email' => fake()->unique()->safeEmail(), 'password' => 'password', 'role' => 'owner', 'is_active' => true]);
    }

    private function product(): CrmProduct
    {
        $product = CrmProduct::create(['code' => 'TEST', 'name' => 'Test product', 'unit_cost' => 4, 'is_active' => true, 'is_visible_on_site' => true]);
        $product->priceTiers()->create(['min_quantity' => 10, 'max_quantity' => null, 'unit_cost' => 4, 'unit_price' => 8]);

        return $product;
    }

    public function test_page_shows_twelve_slots_and_undecided_tshirts(): void
    {
        $this->assertSame(12, CrmCalculatorSlot::count());
        $this->assertSame(0, CrmCalculatorSlot::where('is_enabled_on_site', true)->count());
        $this->assertSame(3, CrmCalculatorSlot::where('family', 'tshirt')->whereNull('crm_product_id')->count());
        $this->actingAs($this->owner(), 'admin')->get(route('admin.crm-catalog.calculator.index'))
            ->assertOk()->assertSee('Prodotti per calcolatore')->assertSee('Da scegliere')->assertSee('calculator-');
    }

    public function test_migration_associates_existing_hoodie_codes_without_enabling_them(): void
    {
        $product = $this->product();
        $product->update(['code' => 'BS332']);
        $migration = require database_path('migrations/2026_11_05_000002_create_crm_calculator_slots.php');
        $migration->down();
        $migration->up();
        $slot = CrmCalculatorSlot::where('model', 'hood')->where('quality', 'basic')->firstOrFail();
        $this->assertSame($product->id, $slot->crm_product_id);
        $this->assertFalse($slot->is_enabled_on_site);
    }

    public function test_saving_association_preserves_product_prices_and_can_clear_it_later(): void
    {
        $product = $this->product();
        $before = $product->fresh()->toArray();
        $print = CrmPrintType::create(['code' => 'TEST_PRINT', 'name' => 'Test print', 'is_active' => true]);
        $slot = CrmCalculatorSlot::where('family', 'tshirt')->firstOrFail();
        $this->actingAs($this->owner(), 'admin')->patch(route('admin.crm-catalog.calculator.update', $slot), [
            'crm_product_id' => $product->id, 'is_enabled_on_site' => 0, 'print_type_ids' => [$print->id],
            'family' => 'invalid', 'quality' => 'invalid',
        ])->assertRedirect(route('admin.crm-catalog.calculator.index'))->assertSessionHasNoErrors();
        $this->assertSame($product->id, $slot->fresh()->crm_product_id);
        $this->assertSame('tshirt', $slot->fresh()->family);
        $this->assertSame([$print->id], $slot->fresh()->printTypes->modelKeys());
        $this->assertSame($before, $product->fresh()->toArray());
        $this->assertSame('8.0000', $product->priceTiers->first()->unit_price);
        $this->patch(route('admin.crm-catalog.calculator.update', $slot), ['crm_product_id' => null, 'is_enabled_on_site' => 0])->assertSessionHasNoErrors();
        $this->assertNull($slot->fresh()->crm_product_id);
        $this->assertCount(0, $slot->fresh()->printTypes);
    }

    public function test_site_enable_requires_ready_product_and_priced_prints(): void
    {
        $slot = CrmCalculatorSlot::firstOrFail();
        $product = $this->product();
        $print = CrmPrintType::create(['code' => 'TEST_PRINT', 'name' => 'Test print', 'is_active' => true]);
        $url = route('admin.crm-catalog.calculator.update', $slot);
        $data = ['crm_product_id' => $product->id, 'is_enabled_on_site' => 1, 'print_type_ids' => [$print->id]];
        $this->actingAs($this->owner(), 'admin')->patch($url, $data)->assertSessionHasErrors('print_type_ids');
        $this->assertFalse($slot->fresh()->is_enabled_on_site);
        $print->priceTiers()->create(['min_quantity' => 10, 'max_quantity' => null, 'unit_cost' => 1, 'unit_price' => 2]);
        $product->update(['is_visible_on_site' => false]);
        $this->patch($url, $data)->assertSessionHasErrors('crm_product_id');
        $product->update(['is_visible_on_site' => true]);
        $this->patch($url, $data)->assertSessionHasNoErrors();
        $this->assertTrue($slot->fresh()->is_enabled_on_site);
        $print->update(['is_active' => false]);
        $this->patch($url, $data)->assertSessionHasErrors('print_type_ids');
    }

    public function test_unprivileged_user_cannot_change_associations(): void
    {
        $user = $this->owner();
        $user->update(['role' => 'operator']);
        $slot = CrmCalculatorSlot::firstOrFail();
        $this->actingAs($user, 'admin')->patch(route('admin.crm-catalog.calculator.update', $slot), ['is_enabled_on_site' => 0])->assertForbidden();
    }
}
