<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\CrmCalculatorSlot;
use App\Models\CrmPrintType;
use App\Models\CrmProduct;
use App\Models\WhatsappConversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CrmPersonalizationSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function loginOwner(): void
    {
        $this->actingAs(AdminUser::create(['name' => 'Owner', 'email' => 'owner@example.test', 'password' => 'password', 'role' => 'owner', 'is_active' => true]), 'admin');
    }

    public function test_fixed_setup_metadata_and_legacy_tiers_are_preserved(): void
    {
        $this->loginOwner();
        $setup = CrmPrintType::create(['code' => 'IMPIANTO_RICAMO', 'name' => 'Impianto ricamo', 'is_active' => true, 'technique' => 'setup']);
        $setup->priceTiers()->create(['min_quantity' => 30, 'max_quantity' => 49.99, 'unit_price' => .875, 'unit_cost' => .875]);
        $this->patch(route('admin.crm-catalog.prints.update', $setup), ['code' => $setup->code, 'name' => $setup->name, 'technique' => 'setup', 'personalization_metadata_present' => true, 'fixed_price' => 35])->assertSessionHasNoErrors();
        $this->assertSame('35.00', $setup->fresh()->fixed_price);
        $this->assertSame('0.8750', $setup->priceTiers->first()->unit_price);
        $this->post(route('admin.crm-catalog.prints.store'), ['code' => 'RICAMO_NUOVO', 'name' => 'Ricamo speciale', 'technique' => 'embroidery', 'preview_layout' => 'HEART', 'allowed_sides' => ['front', 'back'], 'setup_print_type_id' => $setup->id, 'personalization_metadata_present' => true])->assertSessionHasNoErrors();
        $ricamo = CrmPrintType::where('code', 'RICAMO_NUOVO')->firstOrFail();
        $this->assertSame(['front', 'back'], $ricamo->allowed_sides);
        $this->get(route('admin.crm-catalog.index', ['q' => 'RICAMO_NUOVO']))->assertOk()->assertSee('Impianto ricamo');
        $this->delete(route('admin.crm-catalog.prints.destroy', $setup))->assertSessionHasErrors('print_type');
        $this->assertNotNull($setup->fresh());
        $this->post(route('admin.crm-catalog.prints.store'), ['code' => 'RICAMO_NO_SETUP', 'name' => 'Ricamo incompleto', 'technique' => 'embroidery', 'allowed_sides' => ['front'], 'personalization_metadata_present' => true])->assertSessionHasErrors('setup_print_type_id');
    }

    public function test_whatsapp_shows_one_setup_per_selected_embroidery(): void
    {
        $this->loginOwner();
        $product = CrmProduct::create(['code' => 'TS', 'name' => 'Test', 'is_active' => true, 'unit_cost' => 1]);
        $product->priceTiers()->create(['min_quantity' => 15, 'max_quantity' => null, 'unit_cost' => 1, 'unit_price' => 6]);
        $setup = CrmPrintType::create(['code' => 'IMPIANTO_RICAMO', 'name' => 'Impianto ricamo', 'is_active' => true, 'technique' => 'setup', 'fixed_price' => 35]);
        $ricamo = CrmPrintType::create(['code' => 'RICAMO', 'name' => 'Ricamo cuore', 'is_active' => true, 'technique' => 'embroidery', 'preview_layout' => 'HEART', 'setup_print_type_id' => $setup->id]);
        $ricamo->priceTiers()->create(['min_quantity' => 30, 'max_quantity' => null, 'unit_price' => 3, 'unit_cost' => 1]);
        $slot = CrmCalculatorSlot::where('family', 'tshirt')->where('quality', 'basic')->firstOrFail();
        $slot->update(['crm_product_id' => $product->id]);
        $slot->printTypes()->sync([$ricamo->id]);
        foreach (['front', 'back'] as $side) {
            DB::table('crm_product_position_print_type')->insert(['crm_product_id' => $product->id, 'position' => $side, 'crm_print_type_id' => $ricamo->id]);
        }
        $conversation = WhatsappConversation::create(['contact_phone' => '393330001111', 'mode' => 'manual']);
        $selection = ['family' => 'tshirt', 'model' => 'tshirt', 'quantity' => 30, 'qualities' => ['basic'], 'personalization' => 'crm_'.$ricamo->id.'_front'];
        $response = $this->postJson(route('admin.conversations.hoodie-quote.preview', $conversation), $selection)->assertOk()->assertJsonPath('quotes.0.unit', 900)->assertJsonPath('quotes.0.setup_total', 3500)->assertJsonPath('quotes.0.subtotal', 30500)->assertJsonPath('quotes.0.total', 37210);
        $this->assertStringContainsString('spedizione entro 12 giorni lavorativi', $response->json('message'));
        $this->assertStringContainsString('Impianti ricamo: 1 · costo unico totale EUR 35,00 + IVA', $response->json('message'));
        $tooSmall = $this->postJson(route('admin.conversations.hoodie-quote.preview', $conversation), [...$selection, 'quantity' => 20])->assertUnprocessable();
        $this->assertStringContainsString('almeno 30 pezzi', $tooSmall->json('errors.qualities.0'));
        $this->postJson(route('admin.conversations.hoodie-quote.preview', $conversation), [...$selection, 'personalization' => 'crm_'.$ricamo->id.'_front_crm_'.$ricamo->id.'_back'])->assertOk()->assertJsonPath('quotes.0.setup_total', 7000)->assertJsonPath('quotes.0.setup_count', 2);
    }

    public function test_product_positions_are_saved_explicitly_and_independently(): void
    {
        $this->loginOwner();
        $product = CrmProduct::create(['code' => 'POSITION', 'name' => 'Product', 'is_active' => true, 'unit_cost' => 1]);
        $other = CrmProduct::create(['code' => 'OTHER', 'name' => 'Other', 'is_active' => true, 'unit_cost' => 1]);
        $print = CrmPrintType::create(['code' => 'PRINT', 'name' => 'Print', 'is_active' => true, 'technique' => 'print']);
        $url = route('admin.crm-catalog.products.personalizations.update', $product);
        $this->get(route('admin.crm-catalog.products.personalizations.index', $product))->assertOk()->assertSee('Manica');
        $this->patch($url, ['positions' => ['front' => [$print->id], 'back' => [$print->id], 'sleeve' => [$print->id]]])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('crm_product_position_print_type', 3);
        $this->assertDatabaseMissing('crm_product_position_print_type', ['crm_product_id' => $other->id]);
        $this->patch($url, ['positions' => ['front' => [$print->id]]])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('crm_product_position_print_type', 1);
        $setup = CrmPrintType::create(['code' => 'SETUP', 'name' => 'Setup', 'is_active' => true, 'technique' => 'setup', 'fixed_price' => 35]);
        $this->patch($url, ['positions' => ['sleeve' => [$setup->id]]])->assertSessionHasErrors('positions');
        $print->update(['is_active' => false]);
        $this->patch($url, ['positions' => ['sleeve' => [$print->id]]])->assertSessionHasErrors('positions');
        $this->assertDatabaseCount('crm_product_position_print_type', 1);
        $this->patch($url, [])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('crm_product_position_print_type', 0);
    }

    public function test_dimensions_and_product_areas_require_complete_valid_bounds(): void
    {
        $this->loginOwner();
        $print = CrmPrintType::create(['code' => 'STAMPA_CUORE', 'name' => 'Stampa piccola', 'is_active' => true, 'technique' => 'print']);
        $url = route('admin.crm-catalog.prints.update', $print);
        $this->patch($url, ['code' => 'STAMPA_PICCOLA', 'name' => 'Stampa piccola', 'max_width_cm' => 10])->assertSessionHasErrors('max_height_cm');
        $this->patch($url, ['code' => 'STAMPA_PICCOLA', 'name' => 'Stampa piccola', 'max_width_cm' => 10, 'max_height_cm' => 12])->assertSessionHasNoErrors();
        $this->assertEquals(10, $print->fresh()->max_width_cm);
        $product = CrmProduct::create(['code' => 'AREAS', 'name' => 'Area test', 'is_active' => true, 'unit_cost' => 1]);
        $url = route('admin.crm-catalog.products.personalizations.update', $product);
        $area = ['width_cm' => 30, 'height_cm' => 40, 'x_percent' => 25, 'y_percent' => 20, 'width_percent' => 50, 'height_percent' => 60];
        $this->patch($url, ['areas' => ['front' => [...$area, 'x_percent' => 60]]])->assertSessionHasErrors('areas');
        $this->patch($url, ['positions' => ['front' => [$print->id]], 'areas' => ['front' => $area]])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('crm_product_print_areas', ['crm_product_id' => $product->id, 'position' => 'front', 'width_cm' => 30]);
        $this->get(route('admin.crm-catalog.products.personalizations.index', $product))->assertOk()->assertSee('Larghezza reale cm');
        $this->patch($url, ['areas' => ['front' => array_fill_keys(array_keys($area), null)]])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('crm_product_print_areas',0);
    }
}
