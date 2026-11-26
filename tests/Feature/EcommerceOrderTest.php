<?php
namespace Tests\Feature;
use App\Models\{AdminUser, EcommerceOrder};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;
class EcommerceOrderTest extends TestCase {
    use RefreshDatabase;
    private function login(array $permissions): void {
        $this->actingAs(AdminUser::create(['name' => 'Ordini', 'email' => fake()->unique()->safeEmail(), 'password' => 'password', 'role' => 'custom', 'permissions' => $permissions, 'is_active' => true]), 'admin');
    }
    private function order(): EcommerceOrder {
        $key = (string) Str::uuid();
        Storage::disk('ecommerce_orders')->put('test/logo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aZ9sAAAAASUVORK5CYII='));
        return EcommerceOrder::create(['uuid' => Str::uuid(), 'submission_key' => Str::random(64), 'customer_name' => 'Mario Rossi', 'email' => 'mario@example.test', 'customer' => ['billing_type' => 'private', 'billing_name' => 'Mario Rossi', 'billing_tax_code' => 'RSSMRA80A01H501U', 'phone' => '3331234567', 'billing_address' => 'Via Roma 1', 'billing_city' => 'Milano', 'billing_province' => 'MI', 'billing_postal_code' => '20100', 'shipping_name' => 'Mario Rossi', 'shipping_city' => 'Milano', 'shipping_province' => 'MI', 'shipping_postal_code' => '20100', 'name' => 'Mario Rossi', 'shipping_address' => 'Via Roma 1', 'notes' => '<script>alert(1)</script>'], 'items' => [$key => ['name' => 'Maglietta', 'code' => 'TS1', 'mode' => 'personalized', 'quantity' => 10, 'rows' => [['color' => 'Nero', 'size' => 'M', 'quantity' => 10]], 'print_labels' => ['Fronte: Stampa piccola'], 'artworks' => ['front' => ['path' => 'test/logo.png', 'name' => 'logo.png']], 'previews' => [['color' => 'Nero', 'position' => 'front', 'mockup' => '/assets/images/landing/live-mockup/black.png', 'overlay' => ['left' => 30, 'top' => 25, 'width' => 20, 'height' => 10]]], 'artwork_composition' => [], 'notes' => '', 'net' => 150, 'gross' => 183]], 'net' => 150, 'vat' => 33, 'gross' => 183]);
    }
    public function test_unpaid_orders_can_be_cancelled_with_an_audit_trail_but_paid_orders_cannot(): void {
        Storage::fake('ecommerce_orders');
        $order = $this->order(); $this->login(['*']);
        $this->get(route('admin.ecommerce-orders.show', $order))->assertOk()->assertSee('Conferma annullamento');
        $this->patch(route('admin.ecommerce-orders.cancel', $order), ['reason' => 'Sessione scaduta'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->cancelled_at);
        $this->assertSame('Sessione scaduta', $order->fresh()->cancellation_reason);
        $this->assertSame((int) auth('admin')->id(), (int) $order->fresh()->cancelled_by);
        $this->get(route('admin.ecommerce-orders.index'))->assertOk()->assertSee('Annullato');
        $this->get(route('admin.ecommerce-orders.show', $order))->assertOk()->assertDontSee('Conferma annullamento');
        Storage::disk('ecommerce_orders')->assertExists('test/logo.png');
        $paid = $this->order(); $paid->update(['status' => 'paid', 'paid_at' => now()]);
        $this->patch(route('admin.ecommerce-orders.cancel', $paid))->assertSessionHasErrors('cancellation');
        $this->assertSame('paid', $paid->fresh()->status);
    }
    public function test_cancellation_closes_the_stripe_intent_and_is_idempotent(): void {
        Storage::fake('ecommerce_orders');
        $order = $this->order(); $order->update(['status' => 'payment_pending', 'stripe_payment_intent_id' => 'pi_cancel']);
        $this->login(['*']); config(['services.stripe.secret_key' => 'sk_test_fake', 'services.stripe.ecommerce_secret_key' => 'sk_test_ecommerce']);
        \Illuminate\Support\Facades\Http::preventStrayRequests();
        $intent = ['id' => 'pi_cancel', 'status' => 'requires_payment_method', 'metadata' => ['ecommerce_order_id' => (string) $order->id, 'ecommerce_order_uuid' => (string) $order->uuid]];
        \Illuminate\Support\Facades\Http::fake(['api.stripe.com/v1/payment_intents/pi_cancel' => \Illuminate\Support\Facades\Http::response($intent), 'api.stripe.com/v1/payment_intents/pi_cancel/cancel' => \Illuminate\Support\Facades\Http::response([...$intent, 'status' => 'canceled'])]);
        $this->patch(route('admin.ecommerce-orders.cancel', $order))->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame('canceled', $order->fresh()->stripe_payment_status);
        $this->patch(route('admin.ecommerce-orders.cancel', $order))->assertSessionHasNoErrors();
        \Illuminate\Support\Facades\Http::assertSentCount(2);
        \Illuminate\Support\Facades\Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Basic '.base64_encode('sk_test_ecommerce:')));
    }
    public function test_processing_stripe_payments_and_missing_permissions_block_cancellation(): void {
        Storage::fake('ecommerce_orders');
        $order = $this->order(); $order->update(['status' => 'payment_pending', 'stripe_payment_intent_id' => 'pi_processing']);
        $this->login(['leads.view']);
        $this->patch(route('admin.ecommerce-orders.cancel', $order))->assertForbidden();
        $this->login(['*']); config(['services.stripe.secret_key' => 'sk_test_fake']);
        \Illuminate\Support\Facades\Http::fake(['api.stripe.com/*' => \Illuminate\Support\Facades\Http::response(['id' => 'pi_processing', 'status' => 'processing', 'metadata' => ['ecommerce_order_id' => (string) $order->id, 'ecommerce_order_uuid' => (string) $order->uuid]])]);
        $this->patch(route('admin.ecommerce-orders.cancel', $order))->assertSessionHasErrors('cancellation');
        $this->assertSame('payment_pending', $order->fresh()->status);
        \Illuminate\Support\Facades\Http::assertSentCount(1);
    }
    public function test_stripe_failure_leaves_the_order_open_and_bank_cancellation_closes_the_proforma(): void {
        Storage::fake('ecommerce_orders');
        $order = $this->order(); $order->update(['stripe_payment_intent_id' => 'pi_offline']);
        $this->login(['*']); config(['services.stripe.secret_key' => 'sk_test_fake']);
        \Illuminate\Support\Facades\Http::fake(['api.stripe.com/*' => \Illuminate\Support\Facades\Http::response([], 500)]);
        $this->patch(route('admin.ecommerce-orders.cancel', $order))->assertSessionHasErrors('cancellation');
        $this->assertSame('received', $order->fresh()->status);
        $bank = $this->order(); $bank->update(['payment_method' => 'bank', 'status' => 'proforma_pending']);
        $document = app(\App\Services\EcommerceOrderProforma::class)->create($bank);
        $this->patch(route('admin.ecommerce-orders.cancel', $bank))->assertRedirect();
        $this->assertSame('cancelled', $bank->fresh()->status);
        $this->assertSame('cancelled', $document->fresh()->status);
        $this->patch(route('admin.ecommerce-orders.payment', $bank))->assertUnprocessable();
    }
    public function test_missing_stripe_resource_explains_the_configuration_issue_and_displays_one_alert(): void {
        Storage::fake('ecommerce_orders');
        $order = $this->order(); $order->update(['stripe_payment_intent_id' => 'pi_missing']);
        $this->login(['*']); config(['services.stripe.secret_key' => 'sk_test_fake']);
        \Illuminate\Support\Facades\Http::fake(['api.stripe.com/*' => \Illuminate\Support\Facades\Http::response(['error' => ['code' => 'resource_missing']], 404)]);
        $this->patch(route('admin.ecommerce-orders.cancel', $order))->assertSessionHasErrors('cancellation');
        $message = session('errors')->first('cancellation');
        $this->assertStringContainsString('stesso account Stripe', $message);
        $this->assertSame('received', $order->fresh()->status);
        $html = view('admin.ecommerce-orders.show', ['order' => $order->fresh(), 'errors' => (new \Illuminate\Support\ViewErrorBag)->put('default', new \Illuminate\Support\MessageBag(['cancellation' => $message]))])->render();
        $this->assertSame(1, substr_count($html, e($message)));

    }
    public function test_operational_states_shipping_and_notifications_do_not_change_the_payment(): void {
        Storage::fake('ecommerce_orders'); $order = $this->order();
        $order->update(['status' => 'paid', 'paid_at' => now()]); $this->login(['*']);
        $this->mock(\App\Services\BrevoEmailService::class, function ($mock) use ($order) {
            $mock->shouldReceive('sendTransactionalEmail')->once()->withArgs(fn ($to,$subject,$html,$text,$tags) => $subject === 'Ordine nr. '.$order->reference && $text === \App\Support\OrderCustomerMessage::forEvent('in_production', $order->reference)['body'] && $tags === ['ecommerce-order-update'])->andReturn(['messageId' => 'production']);
            $mock->shouldReceive('sendTransactionalEmail')->once()->withArgs(fn ($to,$subject,$html,$text,$tags) => $subject === 'Ordine nr. '.$order->reference.' spedito' && str_starts_with($text, "Gentile Cliente,\n\n") && str_contains($text,'spedito') && str_contains($text,'gls-group.com') && str_contains($text,'TRACK123'))->andReturn(['messageId' => 'shipping']);
            $mock->shouldReceive('sendTransactionalEmail')->once()->withArgs(fn ($to,$subject,$html,$text,$tags) => str_contains($text,'consegnato'))->andReturn(['messageId' => 'delivered']);
        });
        $this->patch(route('admin.ecommerce-orders.status',$order), ['order_status' => 'in_production'])->assertSessionHasNoErrors();
        $this->patch(route('admin.ecommerce-orders.status',$order), ['order_status' => 'in_production'])->assertSessionHasNoErrors();
        $this->assertSame(1, $order->events()->count());
        $this->assertSame('paid',$order->fresh()->status);
        $this->assertSame('in_production',$order->fresh()->order_status);
        $this->assertSame('sent', $order->events()->first()->notification_status);
        $this->artisan('email:send-ecommerce-updates')->assertSuccessful();
        $this->artisan('email:send-ecommerce-updates')->assertSuccessful();
        $shipping = ['fulfillment_method' => 'shipping', 'fulfillment_status' => 'shipped', 'fulfillment_carrier' => 'GLS', 'fulfillment_tracking' => 'TRACK123'];
        $this->patch(route('admin.ecommerce-orders.fulfillment',$order), $shipping)->assertSessionHasNoErrors();
        $this->patch(route('admin.ecommerce-orders.fulfillment',$order), $shipping)->assertSessionHasNoErrors();
        $this->assertSame(2,$order->events()->count());
        $this->artisan('email:send-ecommerce-updates')->assertSuccessful();
        $this->assertSame(2,$order->events()->where('notification_status','sent')->count());
        $this->assertSame('shipped',$order->fresh()->order_status);
        $this->assertEquals(183,$order->fresh()->gross);
        $this->patch(route('admin.ecommerce-orders.fulfillment',$order), [...$shipping, 'fulfillment_status' => 'delivered'])->assertSessionHasNoErrors();
        $this->assertNotNull($order->fresh()->fulfilled_at);
        $this->get(route('admin.ecommerce-orders.show',$order))->assertOk()->assertSee('Storico gestione')->assertSee('TRACK123')->assertSee('Gestione ordine');
    }
    public function test_management_permissions_cancelled_orders_and_incompatible_delivery_states_are_checked(): void {
        Storage::fake('ecommerce_orders'); $order = $this->order(); $this->login(['leads.view']);
        $this->patch(route('admin.ecommerce-orders.status',$order), ['order_status' => 'ready'])->assertForbidden();
        $this->patch(route('admin.ecommerce-orders.notes',$order), ['internal_notes' => 'Privata'])->assertForbidden();
        $this->login(['*']);
        $this->patch(route('admin.ecommerce-orders.fulfillment',$order), ['fulfillment_method' => 'pickup', 'fulfillment_status' => 'shipped'])->assertSessionHasErrors('fulfillment_status');
        $this->patch(route('admin.ecommerce-orders.notes',$order), ['internal_notes' => 'Nota interna di prova'])->assertSessionHasNoErrors();
        $this->assertSame('Nota interna di prova',$order->fresh()->internal_notes);
        $order->update(['status' => 'cancelled']);
        $this->patch(route('admin.ecommerce-orders.status',$order), ['order_status' => 'in_production'])->assertSessionHasErrors('order');
        $this->assertSame('new',$order->fresh()->order_status);
    }
    public function test_offline_order_is_linked_reused_and_can_be_regenerated_after_deletion(): void {
        Storage::fake('ecommerce_orders'); $order = $this->order(); $this->login(['*']);
        $url = route('admin.ecommerce-orders.documents', $order);
        $this->post($url, ['type' => 'offline_order'])->assertSessionHasNoErrors()->assertRedirect();
        $document = $order->fresh()->offlineDocument;
        $this->assertSame('offline_order', $document->type);
        $this->assertEquals(183, $document->total);
        $this->assertEquals(10, $document->items->first()->quantity);
        $this->assertStringContainsString('col. Nero tg. M/10', $document->items->first()->description);
        $this->post($url, ['type' => 'offline_order'])->assertRedirect(route('admin.documents.show', $document));
        $this->assertSame(1, \App\Models\AdminDocument::where('type', 'offline_order')->count());
        $this->post($url, ['type' => 'invoice'])->assertSessionHasNoErrors();
        $this->assertSame(1, \App\Models\DocumentRelation::count());
        $document->delete();
        $this->assertNull($order->fresh()->offline_document_id);
        $this->post($url, ['type' => 'offline_order'])->assertSessionHasNoErrors();
        $this->assertNotEquals($document->id, $order->fresh()->offline_document_id);
        $order->update(['status' => 'cancelled']);
        $this->post($url, ['type' => 'offline_order'])->assertSessionHasErrors('documents');
        $this->login(['leads.view']);
        $this->post($url, ['type' => 'offline_order'])->assertForbidden();
    }

    public function test_invoices_and_delivery_notes_reuse_documents_and_keep_payment_and_quantities(): void {
        Storage::fake('ecommerce_orders'); $order = $this->order(); $order->update(['status' => 'paid','paid_at' => now()]); $this->login(['*']);
        $this->post(route('admin.ecommerce-orders.documents',$order),['type' => 'invoice'])->assertSessionHasNoErrors()->assertRedirect();
        $invoice = $order->fresh()->invoice;
        $this->assertSame('invoice',$invoice->type); $this->assertSame('paid',$invoice->payment_status); $this->assertEquals(183,$invoice->total);
        $this->post(route('admin.ecommerce-orders.documents',$order),['type' => 'invoice'])->assertRedirect(route('admin.documents.show',$invoice));
        $this->assertSame(1,\App\Models\AdminDocument::where('type','invoice')->count());
        $this->post(route('admin.ecommerce-orders.documents',$order),['type' => 'delivery_note'])->assertSessionHasNoErrors()->assertRedirect();
        $ddt = $order->fresh()->deliveryDocument;
        $this->assertSame('delivery_note',$ddt->type); $this->assertEquals(0,$ddt->total); $this->assertEquals(10,$ddt->items->sum('quantity'));
        $this->assertSame(1,\App\Models\DocumentRelation::count());
    }
    public function test_variants_keep_quantities_prices_and_mockups_consistent_and_stop_after_documents(): void {
        Storage::fake('ecommerce_orders'); $order = $this->order(); $this->login(['*']);
        $product = \App\Models\CrmProduct::create(['name' => 'Maglietta','code' => 'TS1','is_active' => true]);
        $black = $product->colors()->create(['name' => 'Nero','code' => 'BK','sizes_restricted' => false]);
        $white = $product->colors()->create(['name' => 'Bianco','code' => 'WH','sizes_restricted' => true]);
        $m = $product->sizes()->create(['name' => 'M']); $small = $product->sizes()->create(['name' => 'S']); $white->sizes()->attach($small);
        $black->forceFill(['mockup_front_path' => 'black.png'])->save(); $white->forceFill(['mockup_front_path' => 'white.png'])->save();
        $items = $order->items; $key = array_key_first($items); $items[$key]['product_id'] = $product->id; $items[$key]['prints'] = ['front' => 1]; $items[$key]['rows'][0]['color_id'] = $black->id; $order->update(['items' => $items]);
        $url = route('admin.ecommerce-orders.variants',[$order,$key]);
        $this->patch($url,['variants' => [$black->id => [$m->id => 9]]])->assertSessionHasErrors('variants');
        $this->patch($url,['variants' => [$white->id => [$m->id => 10]]])->assertSessionHasErrors('variants');
        $this->patch($url,['variants' => [$black->id => [$m->id => 5], $white->id => [$small->id => 5]]])->assertSessionHasNoErrors();
        $item = $order->fresh()->items[$key]; $this->assertSame(10,array_sum(array_column($item['rows'],'quantity'))); $this->assertEquals(183,$order->fresh()->gross);
        $this->assertSame(['Nero','Bianco'],array_column($item['previews'],'color'));
        $this->assertStringContainsString('white.png',$item['previews'][1]['mockup']);
        $this->post(route('admin.ecommerce-orders.documents',$order),['type' => 'invoice'])->assertSessionHasNoErrors();
        $this->patch($url,['variants' => [$black->id => [$m->id => 10]]])->assertSessionHasErrors('variants');
    }
    public function test_payment_confirmation_is_synchronized_to_a_previously_generated_invoice(): void {
        Storage::fake('ecommerce_orders'); $order = $this->order(); $this->login(['*']);
        $this->post(route('admin.ecommerce-orders.documents',$order),['type' => 'invoice'])->assertSessionHasNoErrors();
        $invoice = $order->fresh()->invoice; $this->assertSame('unpaid',$invoice->payment_status);
        $order->update(['status' => 'paid','paid_at' => now()]);
        $this->artisan('orders:sync-ecommerce-payments')->assertSuccessful();
        $this->assertSame('paid',$invoice->fresh()->payment_status);
        $this->artisan('orders:sync-ecommerce-payments')->assertSuccessful();
        $this->assertEquals(183,$invoice->paymentSchedules()->sum('paid_amount'));
    }
    public function test_failed_updates_are_retried_and_cancelled_order_notifications_are_skipped(): void {
        Storage::fake('ecommerce_orders'); $order = $this->order(); $this->login(['*']);
        $this->mock(\App\Services\BrevoEmailService::class, fn ($mock) => $mock->shouldReceive('sendTransactionalEmail')->once()->andThrow(new \RuntimeException('Offline')));
        $this->patch(route('admin.ecommerce-orders.status',$order),['order_status' => 'in_production'])->assertSessionHasNoErrors()->assertSessionHas('notification_warning');
        $event = $order->events()->first(); $this->assertSame('failed',$event->fresh()->notification_status);
        $this->assertSame('in_production', $order->fresh()->order_status);
        $this->mock(\App\Services\BrevoEmailService::class, fn ($mock) => $mock->shouldReceive('sendTransactionalEmail')->once()->andReturn(['messageId' => 'ok']));
        $this->artisan('email:send-ecommerce-updates')->assertSuccessful();
        $this->assertSame('sent',$event->fresh()->notification_status);
        $pending = \App\Models\EcommerceOrderEvent::create(['ecommerce_order_id' => $order->id, 'kind' => 'collected', 'details' => [], 'notification_status' => 'pending']);
        $order->update(['status' => 'cancelled']);
        $this->artisan('email:send-ecommerce-updates')->assertSuccessful();
        $this->assertSame('skipped',$pending->fresh()->notification_status);
    }
    public function test_shipping_and_tracking_updates_send_immediately_and_retries_do_not_duplicate(): void {
        Storage::fake('ecommerce_orders'); $order = $this->order(); $this->login(['*']);
        $this->mock(\App\Services\BrevoEmailService::class, function ($mock) {
            $mock->shouldReceive('sendTransactionalEmail')->once()->withArgs(fn ($to, $subject, $html, $text) => str_contains($text, 'OLD123'))->andReturn(['messageId' => 'shipped']);
            $mock->shouldReceive('sendTransactionalEmail')->once()->withArgs(fn ($to, $subject, $html, $text) => str_contains($text, 'NEW456'))->andReturn(['messageId' => 'tracking']);
        });
        $data = ['fulfillment_method' => 'shipping', 'fulfillment_status' => 'shipped', 'fulfillment_carrier' => 'GLS', 'fulfillment_tracking' => 'OLD123', 'notify_customer' => 1];
        $url = route('admin.ecommerce-orders.fulfillment', $order);
        $this->patch($url, $data)->assertSessionHasNoErrors()->assertSessionHas('status', 'Consegna aggiornata. Comunicazione inviata al cliente.');
        $this->assertSame('sent', $order->events()->where('kind', 'shipped')->firstOrFail()->notification_status);
        $this->patch($url, [...$data, 'fulfillment_tracking' => 'NEW456'])->assertSessionHasNoErrors();
        $this->assertSame('sent', $order->events()->where('kind', 'tracking_updated')->firstOrFail()->notification_status);
        $this->patch($url, [...$data, 'fulfillment_tracking' => 'NEW456'])->assertSessionHasNoErrors();
        $this->artisan('email:send-ecommerce-updates')->assertSuccessful();
        $this->assertSame(2, $order->events()->count());
    }

    public function test_operator_can_suppress_each_ecommerce_status_and_shipping_notification(): void {
        Storage::fake('ecommerce_orders'); $order = $this->order(); $this->login(['*']);
        $this->mock(\App\Services\BrevoEmailService::class, fn ($mock) => $mock->shouldNotReceive('sendTransactionalEmail'));
        $this->patch(route('admin.ecommerce-orders.status',$order),['order_status' => 'in_production','notify_customer' => '0'])->assertSessionHasNoErrors();
        $this->assertSame('in_production',$order->fresh()->order_status);
        $event = $order->events()->first(); $this->assertSame('suppressed',$event->notification_status); $this->assertFalse($event->details['notify_customer']);
        $this->patch(route('admin.ecommerce-orders.fulfillment',$order),['fulfillment_method' => 'shipping','fulfillment_status' => 'shipped','fulfillment_carrier' => 'GLS','fulfillment_tracking' => '123','notify_customer' => '0'])->assertSessionHasNoErrors();
        $this->assertSame('shipped',$order->fresh()->order_status);
        $this->assertSame(2,$order->events()->where('notification_status','suppressed')->count());
        $this->artisan('email:send-ecommerce-updates')->assertSuccessful();
        $this->get(route('admin.ecommerce-orders.show',$order))->assertOk()->assertSee('non inviata per scelta dell’operatore')->assertSee('data-confirm-order-update',false)->assertSee('Invia comunicazione al cliente');
    }
    public function test_ecommerce_proforma_formats_articles_like_checkout_and_preserves_fixed_costs(): void {
        Storage::fake('ecommerce_orders');
        $order = $this->order();
        $items = $order->items; $key = array_key_first($items);
        $items[$key] = array_replace($items[$key], ['quantity' => 20, 'rows' => [
            ['color' => 'BLACK', 'size' => 'm', 'quantity' => 5],
            ['color' => 'BLACK', 'size' => 'l', 'quantity' => 10],
            ['color' => 'WHITE', 'size' => 'xl', 'quantity' => 5],
        ], 'unit' => 5, 'setup' => 30, 'minimum_order_adjustment' => 20]);
        $order->update(['items' => $items, 'payment_method' => 'bank', 'status' => 'proforma_pending', 'shipping_gross' => 9.90, 'net' => 158.12, 'vat' => 34.78, 'gross' => 192.90]);
        $document = app(\App\Services\EcommerceOrderProforma::class)->create($order);
        $line = $document->items->first();
        $this->assertSame("MAGLIETTA\ncol. Black tg. M/5, L/10\ncol. White tg. XL/5\nFronte: Stampa piccola", $line->description);
        $this->assertEquals(20, $line->quantity);
        $this->assertEquals(5, $line->unit_price);
        $this->assertEquals(100, $line->line_subtotal);
        $this->assertCount(4, $document->items);
        $this->assertEquals(8.12, $document->items->last()->unit_price);
        $this->assertEquals(30, $document->items[1]->line_subtotal);
        $this->assertEquals(20, $document->items[2]->line_subtotal);
        $this->assertEquals(192.90, $document->total);
        $this->assertEquals(192.90, $document->paymentSchedules->first()->amount);
    }

    public function test_old_ecommerce_snapshots_preserve_totals_when_unit_price_requires_rounding(): void {
        Storage::fake('ecommerce_orders');
        $order = $this->order(); $items = $order->items; $key = array_key_first($items);
        $items[$key]['quantity'] = 7; $items[$key]['rows'][0]['quantity'] = 7;
        $order->update(['items' => $items, 'payment_method' => 'bank', 'status' => 'proforma_pending']);
        $document = app(\App\Services\EcommerceOrderProforma::class)->create($order);
        $this->assertEquals(7, $document->items->first()->quantity);
        $this->assertEquals(21.43, $document->items->first()->unit_price);
        $this->assertEquals(-0.01, $document->items->last()->unit_price);
        $this->assertEquals(183, $document->total);
    }

    public function test_sending_ecommerce_proforma_numbers_it_before_rendering_the_attachment(): void {
        Storage::fake('ecommerce_orders');
        $order = $this->order(); $order->update(['payment_method' => 'bank', 'status' => 'proforma_pending']);
        $document = app(\App\Services\EcommerceOrderProforma::class)->create($order);
        $this->assertSame('BOZZA', $document->display_code);
        $this->login(['*']);
        $this->mock(\App\Services\AdminDocumentPdfService::class, function ($mock) {
            $mock->shouldReceive('output')->twice()->withArgs(fn ($doc) => $doc->status === 'sent' && $doc->number > 0 && $doc->display_code !== 'BOZZA')->andReturn('numbered-pdf');
            $mock->shouldReceive('filename')->twice()->withArgs(fn ($doc) => !str_contains($doc->display_code, 'BOZZA'))->andReturn('proforma.pdf');
        });
        $this->mock(\App\Services\BrevoEmailService::class, function ($mock) {
            $mock->shouldReceive('sendTransactionalEmail')->once()->withArgs(fn ($to, $subject, $html, $text, $tags, $files) => $files[0]['content'] === 'numbered-pdf')->andReturn(['messageId' => 'test']);
        });
        $this->post(route('admin.ecommerce-orders.proforma.send', $order))->assertSessionHasNoErrors();
        $document->refresh(); $number = $document->number;
        $this->assertSame('sent', $document->status);
        $this->assertNotNull($number);
        $this->assertNotSame('BOZZA', $document->display_code);
        $this->post(route('admin.ecommerce-orders.proforma.send', $order))->assertSessionHasNoErrors();
        $this->assertSame($number, $document->fresh()->number);
    }

    public function test_failed_proforma_delivery_does_not_leave_a_draft_marked_sent(): void {
        Storage::fake('ecommerce_orders');
        $order = $this->order(); $order->update(['payment_method' => 'bank', 'status' => 'proforma_pending']);
        $document = app(\App\Services\EcommerceOrderProforma::class)->create($order);
        $this->login(['*']);
        $this->mock(\App\Services\AdminDocumentPdfService::class, function ($mock) {
            $mock->shouldReceive('output')->once()->andReturn('pdf');
            $mock->shouldReceive('filename')->once()->andReturn('proforma.pdf');
        });
        $this->mock(\App\Services\BrevoEmailService::class, fn ($mock) => $mock->shouldReceive('sendTransactionalEmail')->once()->andThrow(new \RuntimeException('Unavailable')));
        $this->post(route('admin.ecommerce-orders.proforma.send', $order))->assertSessionHasErrors('proforma');
        $this->assertSame('draft', $document->fresh()->status);
        $this->assertNull($document->fresh()->number);
        $this->assertNull($order->fresh()->proforma_sent_at);
    }

    public function test_deleting_proforma_unlinks_unpaid_order_and_allows_a_new_document(): void {
        Storage::fake('ecommerce_orders');
        foreach (['draft', 'sent'] as $status) {
            $order = $this->order();
            $order->update(['payment_method' => 'bank', 'status' => 'proforma_pending']);
            $service = app(\App\Services\EcommerceOrderProforma::class);
            $document = $service->create($order);
            if ($status === 'sent') {
                app(\App\Services\AdminDocumentService::class)->markAsSent($document);
                $order->update(['status' => 'bank_transfer_pending', 'proforma_sent_at' => now()]);
            }
            $document->delete();
            $order->refresh();
            $this->assertNull($order->admin_document_id);
            $this->assertNull($order->proforma_sent_at);
            $this->assertSame('proforma_pending', $order->status);
            $replacement = $service->create($order);
            $this->assertNotEquals($document->id, $replacement->id);
            $this->assertEquals(183, $replacement->total);
        }
    }

    public function test_deleting_proforma_does_not_reopen_paid_or_cancelled_orders(): void {
        Storage::fake('ecommerce_orders');
        foreach (['paid', 'cancelled'] as $status) {
            $order = $this->order();
            $order->update(['payment_method' => 'bank', 'status' => 'proforma_pending']);
            $document = app(\App\Services\EcommerceOrderProforma::class)->create($order);
            $order->update(['status' => $status, 'paid_at' => $status === 'paid' ? now() : null]);
            $document->delete();
            $this->assertNull($order->fresh()->admin_document_id);
            $this->assertSame($status, $order->fresh()->status);
        }
    }

    public function test_ecommerce_proforma_edit_requires_confirmation_and_warns_about_email(): void {
        Storage::fake('ecommerce_orders');
        $order = $this->order(); $order->update(['payment_method' => 'bank', 'status' => 'proforma_pending']);
        $document = app(\App\Services\EcommerceOrderProforma::class)->create($order);
        $this->login(['*']);
        $this->get(route('admin.documents.edit', $document))->assertOk()
            ->assertSee('data-ecommerce-proforma="1"', false)
            ->assertSee('La proforma verrà inviata automaticamente al cliente tramite e-mail.', false);
    }

    public function test_bank_order_status_does_not_inherit_cancelled_online_payment_color(): void {
        Storage::fake('ecommerce_orders');
        $order = $this->order();
        $order->update(['payment_method' => 'bank', 'status' => 'proforma_pending', 'stripe_payment_status' => 'canceled', 'stripe_last_error' => 'Errore del precedente pagamento']);
        $this->login(['*']);
        foreach (['proforma_pending' => 'Proforma da generare', 'bank_transfer_pending' => 'In attesa di bonifico'] as $status => $label) {
            $order->update(['status' => $status]);
            $html = $this->get(route('admin.ecommerce-orders.index'))->assertOk()->getContent();
            $this->assertSame(1, preg_match('/class="catalog-badge ([^"]+)">'.preg_quote($label, '/').'<\/span>/', $html, $matches));
            $this->assertStringContainsString('bg-amber-50!', $matches[1]);
            $this->assertStringNotContainsString('bg-red-50!', $matches[1]);
        }
        $this->get(route('admin.ecommerce-orders.show', $order))->assertOk()->assertDontSee('Errore del precedente pagamento');
    }

    public function test_registering_bank_payment_allows_optional_notification_and_does_not_duplicate_it(): void {
        Storage::fake('ecommerce_orders');
        $this->login(['*']);
        $this->mock(\App\Services\BrevoEmailService::class, fn ($mock) => $mock->shouldReceive('sendTransactionalEmail')->once()->withArgs(fn ($to, $subject, $html, $text) => str_contains($text, 'Abbiamo ricevuto il pagamento tramite bonifico bancario.'))->andReturn(['messageId' => 'test']));
        foreach ([true, false] as $notify) {
            $order = $this->order(); $order->update(['payment_method' => 'bank', 'status' => 'proforma_pending']);
            app(\App\Services\EcommerceOrderProforma::class)->create($order);
            $this->patch(route('admin.ecommerce-orders.payment', $order), ['notify_customer' => $notify ? 1 : 0])->assertSessionHasNoErrors();
            $this->assertSame('paid', $order->fresh()->status);
            $event = $order->events()->where('kind', 'payment_received')->firstOrFail();
            $this->assertSame($notify ? 'sent' : 'suppressed', $event->notification_status);
            $this->patch(route('admin.ecommerce-orders.payment', $order), ['notify_customer' => 1])->assertSessionHasNoErrors();
            $this->assertSame(1, $order->events()->where('kind', 'payment_received')->count());
        }
        $this->artisan('email:send-ecommerce-updates')->assertSuccessful();
        $this->assertSame(1, \App\Models\EcommerceOrderEvent::where('kind', 'payment_received')->where('notification_status', 'sent')->count());
    }

    public function test_bank_payment_email_failure_preserves_payment_and_retries_without_duplicates(): void {
        Storage::fake('ecommerce_orders');
        $order = $this->order(); $order->update(['payment_method' => 'bank', 'status' => 'proforma_pending']);
        app(\App\Services\EcommerceOrderProforma::class)->create($order);
        $this->login(['*']);
        $this->mock(\App\Services\BrevoEmailService::class, fn ($mock) => $mock->shouldReceive('sendTransactionalEmail')->once()->andThrow(new \RuntimeException('Offline')));
        $this->patch(route('admin.ecommerce-orders.payment', $order), ['notify_customer' => 1])
            ->assertSessionHasNoErrors()->assertSessionHas('notification_warning');
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame('failed', $order->events()->where('kind', 'payment_received')->firstOrFail()->notification_status);
        $this->assertSame('paid', $order->fresh()->proforma->payment_status);
        $this->mock(\App\Services\BrevoEmailService::class, fn ($mock) => $mock->shouldReceive('sendTransactionalEmail')->once()->andReturn(['messageId' => 'retried']));
        $this->artisan('email:send-ecommerce-updates')->assertSuccessful();
        $this->artisan('email:send-ecommerce-updates')->assertSuccessful();
        $this->assertSame('sent', $order->events()->where('kind', 'payment_received')->firstOrFail()->notification_status);
    }

    public function test_bank_orders_reuse_standard_proformas_and_payment_schedules(): void {
        Storage::fake('ecommerce_orders');
        $order = $this->order(); $order->update(['payment_method' => 'bank', 'status' => 'proforma_pending']);
        $this->login(['*']);
        $this->post(route('admin.ecommerce-orders.proforma', $order))->assertSessionHasNoErrors()->assertRedirect();
        $document = $order->fresh()->proforma;
        $this->assertNotNull($document);
        $this->assertSame('proforma', $document->type);
        $this->assertEquals(183, $document->total);
        $this->assertEquals(183, $document->paymentSchedules->first()->amount);
        $this->post(route('admin.ecommerce-orders.proforma', $order))->assertRedirect(route('admin.documents.show', $document));
        $this->assertSame(1, \App\Models\AdminDocument::where('type', 'proforma')->count());
        $this->mock(\App\Services\BrevoEmailService::class, function ($mock) use ($order) {
            $mock->shouldReceive('sendTransactionalEmail')->once()->withArgs(fn ($to, $subject, $html, $text, $tags, $files) => $to['email'] === 'mario@example.test' && $files[0]['content'] === 'test-pdf' && $subject === 'Proforma nr. BOZZA' && $text === \App\Support\ProformaCustomerMessage::body('BOZZA', $order->reference))->andReturn(['messageId' => 'test']);
        });
        app(\App\Services\EcommerceOrderProforma::class)->send($order->fresh(), $document, 'test-pdf', 'proforma.pdf');
        app(\App\Services\EcommerceOrderProforma::class)->send($order->fresh(), $document, 'test-pdf', 'proforma.pdf');
        $this->assertSame('bank_transfer_pending', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->proforma_sent_at);
        $this->patch(route('admin.ecommerce-orders.payment', $order), ['notify_customer' => 0])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame('paid', $document->fresh()->payment_status);
        $this->assertNotNull($order->fresh()->paid_at);
    }
    public function test_confirmation_job_runs_in_admin_retries_failures_and_ignores_cancelled_or_unpaid_online_orders(): void {
        Storage::fake('ecommerce_orders');
        $order = $this->order();
        $job = new \App\Jobs\SendEcommerceOrderConfirmation($order->id);
        $this->assertSame('database', $job->connection);
        $this->assertSame('admin', $job->queue);
        $this->assertSame(file_get_contents(app_path('Jobs/SendEcommerceOrderConfirmation.php')), file_get_contents(base_path('../public_html/app/Jobs/SendEcommerceOrderConfirmation.php')));
        $this->mock(\App\Services\BrevoEmailService::class, fn ($mock) => $mock->shouldNotReceive('sendTransactionalEmail'));
        $job->handle();
        $this->assertNull($order->fresh()->confirmation_sent_at);
        $order->update(['status' => 'paid', 'paid_at' => now()]);
        $this->mock(\App\Services\BrevoEmailService::class, fn ($mock) => $mock->shouldReceive('sendTransactionalEmail')->once()->andThrow(new \RuntimeException('Offline')));
        try { $job->handle(); $this->fail('Expected retriable exception'); }
        catch (\RuntimeException $error) { $this->assertSame('Offline', $error->getMessage()); }
        $this->assertNull($order->fresh()->confirmation_sent_at);
        $this->mock(\App\Services\BrevoEmailService::class, fn ($mock) => $mock->shouldReceive('sendTransactionalEmail')->once()->andReturn(['messageId' => 'test']));
        $job->handle();
        $job->handle();
        $this->assertNotNull($order->fresh()->confirmation_sent_at);
        $cancelled = $this->order(); $cancelled->update(['status' => 'cancelled']);
        (new \App\Jobs\SendEcommerceOrderConfirmation($cancelled->id))->handle();
        $this->assertNull($cancelled->fresh()->confirmation_sent_at);
    }

    public function test_confirmations_reuse_brevo_and_do_not_repeat_successful_sends(): void {
        Storage::fake('ecommerce_orders');
        $order = $this->order(); $order->update(['status' => 'paid', 'paid_at' => now()]);
        $this->mock(\App\Services\BrevoEmailService::class, function ($mock) use ($order) {
            $mock->shouldReceive('sendTransactionalEmail')->once()->withArgs(fn ($recipient, $subject, $html, $text, $tags) => $recipient['email'] === $order->email && $subject === 'Ordine nr. '.$order->reference.' - Conferma ordine' && str_contains($html, 'Maglietta') && str_contains($html, 'Gentile Cliente,') && str_contains($html, 'Cordiali saluti') && !preg_match('/<(?:h[1-6]|strong|b)(?:\s|>)/i', $html) && str_contains($text, '€ 183,00') && $tags === ['ecommerce-order'])->andReturn(['messageId' => 'test']);
        });
        $this->artisan('email:send-ecommerce-confirmations')->assertSuccessful();
        $this->assertNotNull($order->fresh()->confirmation_sent_at);
        $this->artisan('email:send-ecommerce-confirmations')->assertSuccessful();
    }
    public function test_failed_brevo_sends_stay_pending_and_can_be_retried(): void {
        Storage::fake('ecommerce_orders');
        $order = $this->order(); $order->update(['status' => 'paid', 'paid_at' => now()]);
        $this->mock(\App\Services\BrevoEmailService::class, function ($mock) {
            $mock->shouldReceive('sendTransactionalEmail')->once()->andThrow(new \RuntimeException('Offline'));
        });
        $this->artisan('email:send-ecommerce-confirmations')->assertSuccessful();
        $this->assertNull($order->fresh()->confirmation_sent_at);
        $this->mock(\App\Services\BrevoEmailService::class, function ($mock) {
            $mock->shouldReceive('sendTransactionalEmail')->once()->andReturn(['messageId' => 'test']);
        });
        $this->artisan('email:send-ecommerce-confirmations')->assertSuccessful();
        $this->assertNotNull($order->fresh()->confirmation_sent_at);
    }
    public function test_orders_and_private_mockups_are_visible_to_authorized_admins(): void {
        Storage::fake('ecommerce_orders');
        $order = $this->order();
        $this->login(['leads.view']);
        $this->get(route('admin.ecommerce-orders.index'))->assertOk()->assertSee($order->reference)->assertSee('Mario Rossi');
        $this->get(route('admin.ecommerce-orders.show', $order))->assertOk()->assertSee('Via Roma 1')->assertSee('Grafica posizionata')->assertSee('logo.png')->assertDontSee('<script>alert(1)</script>', false);
        $key = array_key_first($order->items);
        $this->get(route('admin.ecommerce-orders.file', [$order, $key, 'front']))->assertOk()->assertDownload('logo.png');
        $this->get(route('admin.ecommerce-orders.file', [$order, $key, 'front', 'preview' => 1]))->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get(route('admin.ecommerce-orders.file', [$order, Str::uuid(), 'front']))->assertNotFound();
    }
    public function test_order_pages_and_files_require_order_permissions(): void {
        Storage::fake('ecommerce_orders');
        $order = $this->order();
        $this->login(['crm_catalog.view']);
        $this->get(route('admin.ecommerce-orders.index'))->assertForbidden();
        $this->get(route('admin.ecommerce-orders.show', $order))->assertForbidden();
        $this->get(route('admin.ecommerce-orders.file', [$order, array_key_first($order->items), 'front']))->assertForbidden();
    }
}
