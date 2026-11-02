<?php

namespace Tests\Feature;

use App\Models\AdminUser;
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
            ->assertJsonPath('band', '10-24')
            ->assertJsonPath('quotes.0.unit', 1885)
            ->assertJsonPath('quotes.0.subtotal', 37700)
            ->assertJsonPath('quotes.0.vat', 8294)
            ->assertJsonPath('quotes.0.shipping', 0)
            ->assertJsonPath('quotes.0.total', 45994)
            ->assertJsonPath('quotes.1.unit', 2148)
            ->assertJsonPath('quotes.1.total', 52411)
            ->assertJsonFragment(['quality' => 'basic']);
        $this->assertSame(0, WhatsappMessage::count());
        $this->assertSame('manual', $conversation->fresh()->mode);
        Http::assertNothingSent();
    }

    public function test_shipping_is_calculated_for_each_quality_before_adding_shipping(): void
    {
        $result = app(HoodieQuoteCalculator::class)->generate($this->selection(['quantity' => 11, 'personalization' => 'heart_logo']));
        $this->assertSame(990, $result['quotes'][0]['shipping']);
        $this->assertSame(24086, $result['quotes'][0]['total']);
        $this->assertSame(0, $result['quotes'][1]['shipping']);
        $this->assertSame(26625, $result['quotes'][1]['total']);
        $belowThreshold = app(HoodieQuoteCalculator::class)->generate($this->selection(['quantity' => 10, 'personalization' => 'heart_logo', 'qualities' => ['basic']]));
        $this->assertSame(990, $belowThreshold['quotes'][0]['shipping']);
        $this->assertSame(25195, $belowThreshold['quotes'][0]['total']);
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
        $this->assertSame(1336, $quote['quotes'][0]['unit']);
        $this->assertStringContainsString('Retro grande (max 25 x 30 cm)', $quote['message']);
        $this->assertStringContainsString('felpe-personalizzate#calcolatore-prezzo', $quote['message']);
    }

    public function test_max_uses_the_same_prices_as_landing_and_twenty_five_starts_second_band(): void
    {
        $calculator = app(HoodieQuoteCalculator::class);
        $quote = $calculator->generate($this->selection(['quantity' => 100, 'personalization' => 'max_front_print_max_back_print', 'qualities' => ['economic']]));
        $this->assertSame(1664, $quote['quotes'][0]['unit']);
        $this->assertStringContainsString('Stampa formato MAX fronte', $quote['message']);
        $this->assertSame(1885, $calculator->generate($this->selection(['quantity' => 24]))['quotes'][0]['unit']);
        $this->assertSame(1648, $calculator->generate($this->selection(['quantity' => 25]))['quotes'][0]['unit']);
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
            ->assertSee('Prepara preventivo felpe')->assertSee('data-quote-insert', false);
        $conversation->update(['mode' => 'auto']);
        $this->get(route('admin.conversations.show', $conversation))->assertOk()->assertDontSee('Prepara preventivo felpe');
    }

    public function test_missing_shared_catalog_returns_a_clear_error(): void
    {
        config(['landing_quote.path' => '/nonexistent-landing']);
        $this->actingAs($this->owner(), 'admin')->getJson(route('admin.conversations.hoodie-quote.catalog', $this->conversation()))
            ->assertStatus(503)->assertJsonPath('message', 'Listino felpe non disponibile. Verificare il collegamento alla landing.');
    }
}
