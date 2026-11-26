<?php
namespace Tests\Feature;
use App\Models\{AdminUser, Lead, CrmProduct, CrmPrintType};
use App\Services\{LeadSalesSheetService, LeadOrderCheckoutService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
class LeadSalesSetupTest extends TestCase {
 use RefreshDatabase;
 private function catalog(): array {
  $this->actingAs(AdminUser::create(['name' => 'Owner', 'email' => 'owner@example.test', 'password' => 'password', 'role' => 'owner', 'is_active' => true]), 'admin');
  $lead = Lead::create(['uuid' => 'CRM-SETUP', 'name' => 'Cliente', 'email' => 'cliente@example.test', 'status' => 'confirmed', 'privacy_consent' => true]);
  $product = CrmProduct::create(['code' => 'TS1', 'name' => 'Maglietta', 'unit_cost' => 4, 'is_active' => true]);
  $product->priceTiers()->create(['min_quantity' => 1, 'unit_cost' => 4, 'unit_price' => 10]);
  $setup = CrmPrintType::create(['code' => 'SETUP', 'name' => 'Impianto ricamo', 'technique' => 'setup', 'fixed_price' => 35, 'is_active' => true]);
  $setup->priceTiers()->create(['min_quantity' => 1, 'unit_price' => .875, 'unit_cost' => 7]);
  $embroidery = CrmPrintType::create(['code' => 'EMB', 'name' => 'Ricamo', 'technique' => 'embroidery', 'setup_print_type_id' => $setup->id, 'is_active' => true]);
  $embroidery->priceTiers()->create(['min_quantity' => 1, 'unit_price' => 2, 'unit_cost' => 1]);
  $sheet = $lead->salesSheets()->create(['order_number' => 'ORD-SETUP', 'name' => 'Ordine ricamo', 'prices_include_vat' => false, 'shipping_fee' => 0, 'free_shipping_threshold' => 0]);
  $item = $sheet->items()->create(['crm_product_id' => $product->id, 'product_code' => $product->code, 'product_name' => $product->name, 'quantity' => 10, 'product_unit_cost' => 4, 'product_unit_price' => 10]);
  return [$lead,$sheet,$item,$setup,$embroidery];
 }
 public function test_embroidery_setup_is_fixed_and_stays_separate_when_quantity_or_unit_price_changes(): void {
  [$lead,$sheet,$item,$setup,$embroidery] = $this->catalog(); $calculator = app(LeadSalesSheetService::class);
  $calculator->attachPrint($sheet,$item,$embroidery); $calculator->recalculate($sheet);
  $this->assertEquals(12,$item->fresh()->final_unit_price); $this->assertEquals(35,$item->fresh()->setup_revenue_total); $this->assertEquals(155,$sheet->fresh()->revenue_total); $this->assertEquals(57,$item->fresh()->cost_total);
  $item->update(['quantity' => 20]); $calculator->recalculate($sheet);
  $this->assertEquals(35,$item->fresh()->setup_revenue_total); $this->assertEquals(275,$sheet->fresh()->revenue_total);
  $item->update(['final_price_overridden' => true,'final_unit_price' => 11]); $calculator->recalculate($sheet);
  $this->assertEquals(255,$sheet->fresh()->revenue_total); $this->assertEquals(11,$item->fresh()->final_unit_price);
 }
 public function test_each_embroidery_gets_one_setup_and_an_explicit_setup_does_not_duplicate_it(): void {
  [$lead,$sheet,$item,$setup,$embroidery] = $this->catalog(); $calculator = app(LeadSalesSheetService::class);
  $calculator->attachPrint($sheet,$item,$embroidery); $calculator->attachPrint($sheet,$item,$embroidery); $calculator->attachPrint($sheet,$item,$setup); $calculator->recalculate($sheet);
  $this->assertEquals(14,$item->fresh()->final_unit_price); $this->assertEquals(70,$item->fresh()->setup_revenue_total); $this->assertEquals(210,$sheet->fresh()->revenue_total);
  $this->assertSame(2,$item->fresh()->setup_items[0]['quantity']);
 }
 public function test_fixed_setup_without_legacy_tiers_can_be_added_from_livewire(): void {
  [$lead,$sheet,$item,$setup,$embroidery] = $this->catalog(); $setup->priceTiers()->delete();
  Livewire::test(\App\Livewire\Admin\LeadSalesSheet::class,['leadId' => $lead->id,'sheetId' => $sheet->id])->set('printTypeIds.'.$item->id,(string)$setup->id)->call('addPrint',$item->id)->assertHasNoErrors()->assertSee('Impianti una tantum')->assertSee('35,00');
  $this->assertEquals(10,$item->fresh()->final_unit_price); $this->assertEquals(135,$sheet->fresh()->revenue_total);
 }
 public function test_checkout_and_proforma_show_setup_as_a_separate_fixed_line(): void {
  [$lead,$sheet,$item,$setup,$embroidery] = $this->catalog(); $calculator = app(LeadSalesSheetService::class);
  $calculator->attachPrint($sheet,$item,$embroidery); $calculator->recalculate($sheet);
  $checkout = app(LeadOrderCheckoutService::class)->createForOrder($sheet->fresh(),'full');
  $this->assertCount(2,$checkout->items); $this->assertEquals(12,$checkout->items[0]['unit_price']); $this->assertEquals(120,$checkout->items[0]['line_total']);
  $this->assertTrue($checkout->items[1]['is_setup']); $this->assertEquals(1,$checkout->items[1]['quantity']); $this->assertEquals(35,$checkout->items[1]['line_total']); $this->assertEquals(189.10,$checkout->total_amount);
  $this->post(route('admin.checkout-orders.proforma.store',$checkout))->assertSessionHasNoErrors()->assertRedirect();
  $document = $checkout->fresh()->proforma;
  $this->assertEquals(189.10,$document->total); $this->assertEquals(0,$document->rounding_adjustment);
  $this->assertEquals(35,$document->items->firstWhere('item_code','SETUP')->unit_price);
 }
}
