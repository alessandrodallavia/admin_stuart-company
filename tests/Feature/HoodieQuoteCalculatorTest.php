<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\CrmCalculatorSlot;
use App\Models\CrmPrintType;
use App\Models\CrmProduct;
use App\Models\Lead;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use App\Services\HoodieQuoteCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HoodieQuoteCalculatorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['hood', 'crewneck', 'zip'] as $model) {
            foreach (['economic' => 10, 'basic' => 20, 'organic' => 30] as $quality => $price) {
                $product = CrmProduct::create(['code' => $model.'-'.$quality, 'name' => $quality, 'unit_cost' => 1, 'is_active' => true]);
                $product->priceTiers()->create(['min_quantity' => 10, 'max_quantity' => 19.99, 'unit_price' => $price, 'unit_cost' => 1]);
                $product->priceTiers()->create(['min_quantity' => 20, 'max_quantity' => null, 'unit_price' => $price - 2, 'unit_cost' => 1]);
                CrmCalculatorSlot::where('model', $model)->where('quality', $quality)->update(['crm_product_id' => $product->id]);
            }
        }
        foreach (['STAMPA_CUORE' => 1, 'STAMPA_FRONT' => 2, 'STAMPA_BACK' => 3, 'STAMPA_MAX' => 4] as $code => $price) {
            $print = CrmPrintType::create(['code' => $code, 'name' => $code, 'is_active' => true]);
            $print->priceTiers()->create(['min_quantity' => 10, 'max_quantity' => null, 'unit_price' => $price, 'unit_cost' => 1]);
            foreach (CrmCalculatorSlot::where('family', 'hoodie')->get() as $slot) {
                $slot->printTypes()->attach($print);
            }
        }
    }

    private function tshirts(): void
    {
        foreach (['economic' => 6, 'basic' => 8, 'premium' => 10] as $quality => $price) {
            $product = CrmProduct::create(['code' => 'TS-'.$quality, 'name' => 'T-shirt '.$quality, 'unit_cost' => 1, 'is_active' => true, 'is_visible_on_site' => false]);
            $product->priceTiers()->create(['min_quantity' => 15, 'max_quantity' => 19.99, 'unit_price' => $price, 'unit_cost' => 1]);
            $product->priceTiers()->create(['min_quantity' => 20, 'max_quantity' => null, 'unit_price' => $price - 1, 'unit_cost' => 1]);
            $slot = CrmCalculatorSlot::where('family', 'tshirt')->where('quality', $quality)->firstOrFail();
            $slot->update(['crm_product_id' => $product->id]);
            $slot->printTypes()->sync(CrmPrintType::pluck('id'));
        }
    }

    public function test_tshirt_comparison_uses_crm_and_does_not_send_or_save_whatsapp_messages(): void
    {
        $this->tshirts();
        Http::preventStrayRequests();
        $conversation = $this->conversation();
        $response = $this->actingAs($this->owner(), 'admin')->postJson(route('admin.conversations.hoodie-quote.preview', $conversation), [
            'family' => 'tshirt', 'model' => 'tshirt', 'quantity' => 15, 'personalization' => 'heart_logo_back_print', 'qualities' => ['economic', 'basic', 'premium'],
        ])->assertOk()->assertJsonPath('family', 'tshirt')->assertJsonPath('band', '15-19')
            ->assertJsonPath('quotes.0.unit', 1000)->assertJsonPath('quotes.0.total', 19290)
            ->assertJsonPath('quotes.1.total', 22950)->assertJsonPath('quotes.2.total', 25620);
        $this->assertStringContainsString('TS-basic', $response->json('message'));
        $this->assertStringContainsString('della T-shirt scelta', $response->json('message'));
        $this->assertStringContainsString('https://stuart-company.com/#calcolatore-prezzo', $response->json('message'));
        $this->assertStringNotContainsString('felpe-personalizzate', $response->json('message'));
        $this->assertSame(0, WhatsappMessage::count());
        Http::assertNothingSent();
    }

    public function test_tshirt_catalog_prefills_lead_and_supports_max_and_twenty_piece_tier(): void
    {
        $this->tshirts();
        $lead = Lead::create(['uuid' => 'TSQUOTE', 'calculator_used' => true, 'calculator_model' => 'Premium', 'calculator_quantity' => 50, 'calculator_personalization' => 'max_front_print_max_back_print']);
        $conversation = $this->conversation(['lead_id' => $lead->id]);
        $this->actingAs($this->owner(), 'admin')->getJson(route('admin.conversations.hoodie-quote.catalog', $conversation))
            ->assertOk()->assertJsonPath('family', 'tshirt')->assertJsonPath('defaults.model', 'tshirt')->assertJsonPath('defaults.qualities', ['premium'])
            ->assertJsonPath('defaults.quantity', 50)->assertJsonPath('products.basic.basic.code', 'TS-basic')->assertJsonPath('minimum_quantity', 15);
        $calculator = app(HoodieQuoteCalculator::class);
        $max = $calculator->generate(['family' => 'tshirt', 'model' => 'tshirt', 'quantity' => 20, 'personalization' => 'max_front_print_max_back_print', 'qualities' => ['basic']]);
        $this->assertSame(1500, $max['quotes'][0]['unit']);
        $this->assertSame(36600, $max['quotes'][0]['total']);
        // A single instance must keep the two family catalogs separate.
        $this->assertSame(1200, $calculator->generate($this->selection())['quotes'][0]['unit']);
    }

    public function test_tshirt_unconfigured_products_and_invalid_selections_are_rejected(): void
    {
        $this->tshirts();
        $conversation = $this->conversation();
        $this->actingAs($this->owner(), 'admin');
        $selection = ['family' => 'tshirt', 'model' => 'tshirt', 'quantity' => 20, 'personalization' => 'heart_logo', 'qualities' => ['basic']];
        foreach ([['family' => 'invalid'], ['model' => 'hood'], ['quantity' => 14], ['quantity' => 3000], ['qualities' => ['organic']]] as $change) {
            $this->postJson(route('admin.conversations.hoodie-quote.preview', $conversation), [...$selection, ...$change])->assertUnprocessable();
        }
        CrmCalculatorSlot::where('family', 'tshirt')->where('quality', 'basic')->update(['crm_product_id' => null]);
        $this->postJson(route('admin.conversations.hoodie-quote.preview', $conversation), $selection)->assertUnprocessable()->assertJsonValidationErrors('qualities');
    }

    private function owner(): AdminUser
    {
        return AdminUser::create(['name' => 'Owner', 'email' => fake()->unique()->safeEmail(), 'password' => 'password', 'role' => 'owner', 'is_active' => true]);
    }

    private function conversation(array $attributes = []): WhatsappConversation
    {
        return WhatsappConversation::create(['contact_phone' => '393330001111', 'mode' => 'manual', ...$attributes]);
    }

    private function selection(array $overrides = []): array
    {
        return ['model' => 'hood', 'quantity' => 20, 'personalization' => 'heart_logo_back_print', 'qualities' => ['economic', 'basic'], ...$overrides];
    }

    public function test_twenty_piece_comparison_matches_landing_and_does_not_send_or_save_messages(): void
    {
        Http::preventStrayRequests();
        $conversation = $this->conversation();
        $this->actingAs($this->owner(), 'admin')->postJson(route('admin.conversations.hoodie-quote.preview', $conversation), $this->selection())
            ->assertOk()
            ->assertJsonPath('band', '20-3000')
            ->assertJsonPath('quotes.0.unit', 1200)
            ->assertJsonPath('quotes.0.subtotal', 24000)
            ->assertJsonPath('quotes.0.vat', 5280)
            ->assertJsonPath('quotes.0.shipping', 0)
            ->assertJsonPath('quotes.0.total', 29280)
            ->assertJsonPath('quotes.1.unit', 2200)
            ->assertJsonPath('quotes.1.total', 53680)
            ->assertJsonFragment(['quality' => 'basic']);
        $this->assertSame(0, WhatsappMessage::count());
        $this->assertSame('manual', $conversation->fresh()->mode);
        Http::assertNothingSent();
    }

    public function test_shipping_is_calculated_for_each_quality_before_adding_shipping(): void
    {
        $result = app(HoodieQuoteCalculator::class)->generate($this->selection(['quantity' => 11, 'personalization' => 'heart_logo']));
        $this->assertSame(990, $result['quotes'][0]['shipping']);
        $this->assertSame(15752, $result['quotes'][0]['total']);
        $this->assertSame(0, $result['quotes'][1]['shipping']);
        $this->assertSame(28182, $result['quotes'][1]['total']);
        $belowThreshold = app(HoodieQuoteCalculator::class)->generate($this->selection(['quantity' => 10, 'personalization' => 'heart_logo', 'qualities' => ['economic']]));
        $this->assertSame(990, $belowThreshold['quotes'][0]['shipping']);
        $this->assertSame(14410, $belowThreshold['quotes'][0]['total']);
        $this->assertStringContainsString('Spedizione: EUR 9,90 IVA inclusa', $result['message']);
        $this->assertStringContainsString('Spedizione: gratuita', $result['message']);
        $this->assertStringContainsString('max 25 x 15 cm', app(HoodieQuoteCalculator::class)->generate($this->selection(['personalization' => 'front_print']))['message']);
    }

    public function test_catalog_prefills_saved_hoodie_request_and_preserves_print_dimensions(): void
    {
        $lead = Lead::create(['uuid' => 'QUOTE001', 'status' => 'confirmed', 'cta_origin' => 'felpe_calculator', 'calculator_model' => 'Girocollo Organic', 'calculator_quantity' => 100, 'calculator_personalization' => 'back_print']);
        $conversation = $this->conversation(['lead_id' => $lead->id]);
        $this->actingAs($this->owner(), 'admin')->getJson(route('admin.conversations.hoodie-quote.catalog', $conversation))
            ->assertOk()->assertJsonPath('defaults.model', 'crewneck')->assertJsonPath('defaults.quantity', 100)
            ->assertJsonPath('defaults.qualities', ['organic'])->assertJsonPath('defaults.personalization', 'back_print');
        $quote = app(HoodieQuoteCalculator::class)->generate($this->selection(['model' => 'crewneck', 'quantity' => 100, 'qualities' => ['organic'], 'personalization' => 'back_print']));
        $this->assertSame(3100, $quote['quotes'][0]['unit']);
        $this->assertStringContainsString('Retro grande (max 25 x 30 cm)', $quote['message']);
        $this->assertStringContainsString('felpe-personalizzate#calcolatore-prezzo', $quote['message']);
    }

    public function test_max_uses_the_same_prices_as_landing_and_twenty_starts_second_band(): void
    {
        $calculator = app(HoodieQuoteCalculator::class);
        $quote = $calculator->generate($this->selection(['quantity' => 100, 'personalization' => 'max_front_print_max_back_print', 'qualities' => ['economic']]));
        $this->assertSame(1600, $quote['quotes'][0]['unit']);
        $this->assertStringContainsString('Stampa formato MAX fronte', $quote['message']);
        $this->assertSame(1400, $calculator->generate($this->selection(['quantity' => 19]))['quotes'][0]['unit']);
        $this->assertSame(1200, $calculator->generate($this->selection(['quantity' => 25]))['quotes'][0]['unit']);
    }

    public function test_invalid_selections_do_not_produce_a_quote(): void
    {
        $conversation = $this->conversation();
        $this->actingAs($this->owner(), 'admin');
        foreach ([['quantity' => 9], ['quantity' => 3001], ['quantity' => 10.5], ['qualities' => []], ['qualities' => ['premium']], ['qualities' => ['basic', 'basic']], ['model' => 'bad'], ['personalization' => 'bad']] as $change) {
            $this->postJson(route('admin.conversations.hoodie-quote.preview', $conversation), $this->selection($change))->assertUnprocessable();
        }
        $this->postJson(route('admin.conversations.hoodie-quote.preview', $conversation), $this->selection(['model' => 'hood', 'personalization' => 'big_front_print']))
            ->assertUnprocessable()->assertJsonValidationErrors('personalization');
    }

    public function test_manage_permission_and_manual_mode_are_required(): void
    {
        $conversation = $this->conversation();
        config(['admin_permissions.role_permissions.operator' => []]);
        $viewer = AdminUser::create(['name' => 'Viewer', 'email' => fake()->unique()->safeEmail(), 'password' => 'password', 'role' => 'operator', 'permissions' => ['whatsapp.view'], 'is_active' => true]);
        $this->actingAs($viewer, 'admin')->getJson(route('admin.conversations.hoodie-quote.catalog', $conversation))->assertForbidden();
        $this->postJson(route('admin.conversations.hoodie-quote.preview', $conversation), $this->selection())->assertForbidden();
        $conversation->update(['mode' => 'auto']);
        $this->actingAs($this->owner(), 'admin')->getJson(route('admin.conversations.hoodie-quote.catalog', $conversation))->assertForbidden();
        $this->postJson(route('admin.conversations.hoodie-quote.preview', $conversation), $this->selection())->assertForbidden();
    }

    public function test_quote_panel_is_shown_only_in_manageable_manual_chats(): void
    {
        $conversation = $this->conversation();
        $this->actingAs($this->owner(), 'admin')->get(route('admin.conversations.show', $conversation))->assertOk()
            ->assertSee('Prepara preventivo prodotti')->assertSee('data-quote-insert', false);
        $conversation->update(['mode' => 'auto']);
        $this->get(route('admin.conversations.show', $conversation))->assertOk()->assertDontSee('Prepara preventivo prodotti');
    }

    public function test_missing_shared_catalog_returns_a_clear_error(): void
    {
        config(['landing_quote.path' => '/nonexistent-landing']);
        $this->actingAs($this->owner(), 'admin')->getJson(route('admin.conversations.hoodie-quote.catalog', $this->conversation()))
            ->assertStatus(503)->assertJsonPath('message', 'Listino felpe non disponibile. Verificare il collegamento alla landing.');
    }
}
