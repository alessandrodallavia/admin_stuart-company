<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\DocumentController;
use App\Models\AdminDocument;
use App\Models\AdminUser;
use App\Services\AdminDocumentPdfService;
use App\Services\AdminDocumentService;
use App\Services\AdminDocumentXmlService;
use App\Services\DocumentGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class AdminDocumentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_documents_index_opens_offline_orders_by_default(): void
    {
        $admin = AdminUser::create([
            'name' => 'Admin Test',
            'email' => 'admin@example.test',
            'password' => 'password',
            'role' => 'owner',
            'is_active' => true,
        ]);

        AdminDocument::create([
            'type' => 'quote',
            'number' => 1,
            'year' => 2026,
            'code' => 'PREV-1',
            'document_date' => '2026-05-26',
            'status' => 'sent',
            'payment_status' => 'not_managed',
            'payment_conditions' => 'TP02',
            'currency' => 'EUR',
            'customer_name' => 'Mario Rossi',
            'customer_country' => 'IT',
            'subtotal' => 100,
            'vat_total' => 22,
            'total' => 122,
        ]);
        AdminDocument::create([
            'type' => 'invoice',
            'fiscal_type' => 'TD01',
            'number' => 7,
            'year' => 2026,
            'code' => 'FPR 7/26',
            'document_date' => '2026-05-27',
            'status' => 'issued',
            'payment_status' => 'unpaid',
            'payment_conditions' => 'TP02',
            'currency' => 'EUR',
            'customer_name' => 'Luigi Verdi',
            'customer_country' => 'IT',
            'subtotal' => 200,
            'vat_total' => 44,
            'total' => 244,
        ]);

        $this->actingAs($admin, 'admin')
            ->get('/documents')
            ->assertOk()
            ->assertSee('Ordine offline')
            ->assertSee('Crea nuovo ordine offline')
            ->assertDontSee('Preventivo PREV-1')
            ->assertDontSee('Fattura FPR 7/26');
    }

    public function test_documents_area_only_shows_create_button_for_current_type(): void
    {
        $admin = AdminUser::create([
            'name' => 'Admin Test',
            'email' => 'admin-area@example.test',
            'password' => 'password',
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->get('/documents?type=invoice')
            ->assertOk()
            ->assertSee('Crea nuovo fattura')
            ->assertDontSee('Crea nuovo ordine offline')
            ->assertDontSee('Crea nuovo preventivo');
    }

    public function test_documents_filters_are_contextual_and_auto_submitted(): void
    {
        $admin = AdminUser::create([
            'name' => 'Admin Test',
            'email' => 'admin-filters@example.test',
            'password' => 'password',
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->get('/documents?type=invoice')
            ->assertOk()
            ->assertSee('name="type" value="invoice"', false)
            ->assertSee('data-auto-filter-form', false)
            ->assertSee('data-auto-filter-input', false)
            ->assertDontSee('<span class="text-12 font-extrabold uppercase tracking-normal text-gray">Tipo</span>', false)
            ->assertDontSee('Filtra');
    }

    public function test_status_filter_uses_current_document_type_statuses(): void
    {
        $admin = AdminUser::create([
            'name' => 'Admin Test',
            'email' => 'admin-statuses@example.test',
            'password' => 'password',
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->get('/documents?type=quote')
            ->assertOk()
            ->assertSee('Inviato')
            ->assertDontSee('Inviata SDI')
            ->assertDontSee('Emessa');

        $this->actingAs($admin, 'admin')
            ->get('/documents?type=invoice')
            ->assertOk()
            ->assertSee('Inviata SDI')
            ->assertSee('Emessa');

        $this->actingAs($admin, 'admin')
            ->get('/documents')
            ->assertOk()
            ->assertDontSee('<span class="text-12 font-extrabold uppercase tracking-normal text-gray">Stato</span>', false);
    }

    public function test_invoice_tools_are_only_visible_inside_invoice_area(): void
    {
        $admin = AdminUser::create([
            'name' => 'Admin Test',
            'email' => 'admin-invoice-tools@example.test',
            'password' => 'password',
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->get('/documents?type=invoice')
            ->assertOk()
            ->assertSee('Importa XML')
            ->assertSee('Invia allo SDI')
            ->assertSee('Scadenze pagamenti');

        $this->actingAs($admin, 'admin')
            ->get('/documents?type=offline_order')
            ->assertOk()
            ->assertDontSee('Importa XML')
            ->assertDontSee('Invia allo SDI');
    }

    public function test_paid_order_payment_is_preserved_when_generating_invoice(): void
    {
        $order = AdminDocument::create([
            'type' => 'offline_order',
            'number' => 1,
            'year' => 2026,
            'code' => 'OFF-1',
            'document_date' => '2026-05-26',
            'status' => 'completed',
            'payment_status' => 'paid',
            'payment_conditions' => 'TP02',
            'payment_method' => 'MP05',
            'currency' => 'EUR',
            'customer_name' => 'Mario Rossi',
            'customer_country' => 'IT',
            'subtotal' => 100,
            'vat_total' => 22,
            'total' => 122,
        ]);
        $order->items()->create([
            'position' => 1,
            'description' => 'Scarpe test',
            'quantity' => 1,
            'unit_price' => 100,
            'vat_rate' => 22,
            'line_subtotal' => 100,
            'line_vat' => 22,
            'line_total' => 122,
        ]);
        $order->paymentSchedules()->create([
            'due_date' => '2026-05-26',
            'method' => 'Bonifico bancario',
            'payment_method_code' => 'MP05',
            'amount' => 122,
            'paid_amount' => 122,
            'paid_at' => '2026-05-26',
            'status' => 'paid',
        ]);

        $invoice = app(AdminDocumentService::class)->duplicateAs($order, 'invoice', true);

        $this->assertSame('paid', $invoice->payment_status);
        $this->assertSame('paid', $invoice->paymentSchedules->first()->status);
        $this->assertEquals(122.0, (float) $invoice->paymentSchedules->first()->paid_amount);
    }

    public function test_invoice_xml_uses_sdi_filename_and_includes_payment_details(): void
    {
        $invoice = AdminDocument::create([
            'type' => 'invoice',
            'fiscal_type' => 'TD01',
            'number' => 7,
            'year' => 2026,
            'code' => 'FPR 7/26',
            'document_date' => '2026-05-26',
            'status' => 'issued',
            'payment_status' => 'paid',
            'payment_conditions' => 'TP02',
            'payment_method' => 'MP05',
            'currency' => 'EUR',
            'customer_name' => 'Mario Rossi',
            'customer_tax_code' => 'RSSMRA80A01H501U',
            'customer_address' => 'Via Roma',
            'customer_city' => 'Padova',
            'customer_postal_code' => '35100',
            'customer_country' => 'IT',
            'subtotal' => 100,
            'vat_total' => 22,
            'total' => 122,
        ]);
        $invoice->items()->create([
            'position' => 1,
            'description' => 'Scarpe test',
            'quantity' => 1,
            'unit_price' => 100,
            'vat_rate' => 22,
            'line_subtotal' => 100,
            'line_vat' => 22,
            'line_total' => 122,
        ]);
        $invoice->paymentSchedules()->create([
            'due_date' => '2026-05-26',
            'method' => 'Bonifico bancario',
            'payment_method_code' => 'MP05',
            'amount' => 122,
            'paid_amount' => 122,
            'paid_at' => '2026-05-26',
            'status' => 'paid',
        ]);

        $service = app(AdminDocumentXmlService::class);

        $this->assertMatchesRegularExpression('/^IT05040450289_[A-Z0-9]{5}\.xml$/', $service->filename($invoice));
        $this->assertStringContainsString('<ProgressivoInvio>7</ProgressivoInvio>', $service->output($invoice));
        $this->assertStringContainsString('<DatiPagamento>', $service->output($invoice));
        $this->assertStringContainsString('<ImportoPagamento>122.00</ImportoPagamento>', $service->output($invoice));
    }

    public function test_export_marks_selected_invoices_as_sent(): void
    {
        $invoice = AdminDocument::create([
            'type' => 'invoice',
            'fiscal_type' => 'TD01',
            'number' => 8,
            'year' => 2026,
            'code' => 'FPR 8/26',
            'document_date' => '2026-05-26',
            'status' => 'issued',
            'payment_status' => 'unpaid',
            'payment_conditions' => 'TP02',
            'payment_method' => 'MP05',
            'currency' => 'EUR',
            'customer_name' => 'Mario Rossi',
            'customer_country' => 'IT',
            'subtotal' => 100,
            'vat_total' => 22,
            'total' => 122,
        ]);

        $method = new ReflectionMethod(DocumentController::class, 'markInvoicesAsSent');
        $method->setAccessible(true);
        $method->invoke(app(DocumentController::class), collect([$invoice]));

        $this->assertSame('sent', $invoice->fresh()->status);
    }

    public function test_delivery_note_pdf_uses_shipping_address_and_hides_amount_columns(): void
    {
        $deliveryNote = AdminDocument::create([
            'type' => 'delivery_note',
            'number' => 3,
            'year' => 2026,
            'code' => 'DDT-3',
            'document_date' => '2026-05-29',
            'status' => 'sent',
            'payment_status' => 'unpaid',
            'payment_conditions' => 'TP02',
            'currency' => 'EUR',
            'customer_name' => 'Mario Rossi',
            'customer_country' => 'IT',
            'shipping_name' => 'Club Tennis Padova',
            'shipping_address' => 'Via Spedizione',
            'shipping_street_number' => '10',
            'shipping_city' => 'Padova',
            'shipping_province' => 'PD',
            'shipping_postal_code' => '35100',
            'shipping_country' => 'IT',
            'transport_reason' => 'Vendita',
            'transport_care' => 'Mittente',
            'transport_start_date' => '2026-05-30',
            'goods_appearance' => 'Cartoni',
            'parcels_count' => 3,
            'gross_weight_kg' => 12.50,
            'net_weight_kg' => 10.25,
            'carrier_name' => 'BRT',
            'subtotal' => 100,
            'vat_total' => 22,
            'total' => 122,
        ]);

        $service = app(AdminDocumentPdfService::class);
        $columnsMethod = new ReflectionMethod(AdminDocumentPdfService::class, 'columns');
        $columnsMethod->setAccessible(true);
        $headerMethod = new ReflectionMethod(AdminDocumentPdfService::class, 'headerData');
        $headerMethod->setAccessible(true);

        $columns = collect($columnsMethod->invoke($service, $deliveryNote))->pluck(0)->all();
        $header = $headerMethod->invoke($service, $deliveryNote);

        $this->assertSame(['Codice', 'Descrizione', 'U.M.', 'Q.ta'], $columns);
        $this->assertNotContains('Prezzo', $columns);
        $this->assertNotContains('Importo', $columns);
        $this->assertNotContains('C.I.', $columns);
        $this->assertContains('CLUB TENNIS PADOVA', $header['shipping_address']);
        $this->assertContains('VIA SPEDIZIONE 10', $header['shipping_address']);
        $this->assertContains('35100 PADOVA (PD)', $header['shipping_address']);

        $footerMethod = new ReflectionMethod(AdminDocumentPdfService::class, 'footerData');
        $footerMethod->setAccessible(true);
        $footer = $footerMethod->invoke($service, $deliveryNote);

        $this->assertSame('Vendita', $footer['causale_trasporto']);
        $this->assertSame('Mittente', $footer['trasporto_cura']);
        $this->assertSame('30/05/2026', $footer['data_inizio_trasporto']);
        $this->assertSame('Cartoni', $footer['aspetto_beni']);
        $this->assertSame(3, $footer['n_colli']);
        $this->assertSame('12.50', (string) $footer['peso_lordo']);
        $this->assertSame('10.25', (string) $footer['peso_netto']);
        $this->assertSame('BRT', $footer['carrier']);
    }

    public function test_generated_documents_show_the_document_chain(): void
    {
        $admin = AdminUser::create([
            'name' => 'Admin Test',
            'email' => 'admin-links@example.test',
            'password' => 'password',
            'role' => 'owner',
            'is_active' => true,
        ]);

        $order = AdminDocument::create([
            'type' => 'offline_order',
            'number' => 4,
            'year' => 2026,
            'code' => 'OFF-4',
            'document_date' => '2026-05-29',
            'status' => 'confirmed',
            'payment_status' => 'unpaid',
            'payment_conditions' => 'TP02',
            'currency' => 'EUR',
            'customer_name' => 'Mario Rossi',
            'customer_country' => 'IT',
            'subtotal' => 100,
            'vat_total' => 22,
            'total' => 122,
        ]);
        $order->items()->create([
            'position' => 1,
            'description' => 'Scarpe test',
            'quantity' => 1,
            'unit_price' => 100,
            'vat_rate' => 22,
            'line_subtotal' => 100,
            'line_vat' => 22,
            'line_total' => 122,
        ]);

        $deliveryNote = app(DocumentGeneratorService::class)->fromOrderToDeliveryNote($order->id);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.documents.show', $deliveryNote))
            ->assertOk()
            ->assertSee('Filiera documenti')
            ->assertSee('Ordine offline OFF-4')
            ->assertSee('DDT BOZZA');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.documents.index', ['type' => 'delivery_note']))
            ->assertOk()
            ->assertSee('Documenti collegati')
            ->assertSee('Ordine offline OFF-4')
            ->assertSee(route('admin.documents.show', $order), false);
    }

    public function test_delivery_note_detail_does_not_show_prices_or_payments(): void
    {
        $admin = AdminUser::create([
            'name' => 'Admin Test',
            'email' => 'admin-ddt-detail@example.test',
            'password' => 'password',
            'role' => 'owner',
            'is_active' => true,
        ]);

        $deliveryNote = AdminDocument::create([
            'type' => 'delivery_note',
            'number' => 5,
            'year' => 2026,
            'code' => 'DDT-5',
            'document_date' => '2026-05-29',
            'status' => 'sent',
            'payment_status' => 'unpaid',
            'payment_conditions' => 'TP02',
            'currency' => 'EUR',
            'customer_name' => 'Mario Rossi',
            'customer_country' => 'IT',
            'subtotal' => 100,
            'vat_total' => 22,
            'total' => 122,
        ]);
        $deliveryNote->items()->create([
            'position' => 1,
            'description' => 'Scarpe test',
            'quantity' => 1,
            'unit_price' => 100,
            'vat_rate' => 22,
            'line_subtotal' => 100,
            'line_vat' => 22,
            'line_total' => 122,
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.documents.show', $deliveryNote))
            ->assertOk()
            ->assertSee('Scarpe test')
            ->assertDontSee('Prezzo')
            ->assertDontSee('Imponibile')
            ->assertDontSee('Totale')
            ->assertDontSee('€ 100,00')
            ->assertDontSee('€ 122,00')
            ->assertDontSee('Pagamenti');
    }

    public function test_delivery_note_area_does_not_show_payment_columns(): void
    {
        $admin = AdminUser::create([
            'name' => 'Admin Test',
            'email' => 'admin-ddt-index@example.test',
            'password' => 'password',
            'role' => 'owner',
            'is_active' => true,
        ]);

        AdminDocument::create([
            'type' => 'delivery_note',
            'number' => 11,
            'year' => 2026,
            'code' => 'DDT-11',
            'document_date' => '2026-05-29',
            'status' => 'sent',
            'payment_status' => 'unpaid',
            'payment_conditions' => 'TP02',
            'currency' => 'EUR',
            'customer_name' => 'Mario Rossi',
            'customer_country' => 'IT',
        ]);

        $this->actingAs($admin, 'admin')
            ->get('/documents?type=delivery_note')
            ->assertOk()
            ->assertSee('DDT DDT-11')
            ->assertDontSee('Pagamento')
            ->assertDontSee('Non pagato')
            ->assertDontSee('Totale');
    }

    public function test_delivery_note_does_not_persist_amounts_or_payments(): void
    {
        $deliveryNote = app(AdminDocumentService::class)->create([
            'type' => 'delivery_note',
            'document_date' => '2026-05-29',
            'status' => 'sent',
            'payment_conditions' => 'TP02',
            'currency' => 'EUR',
            'customer_name' => 'Mario Rossi',
            'customer_country' => 'IT',
            'items' => [[
                'description' => 'Scarpe test',
                'quantity' => 2,
                'unit_price' => 100,
                'vat_rate' => 22,
            ]],
            'payments' => [[
                'due_date' => '2026-05-29',
                'payment_method_code' => 'MP05',
                'amount' => 244,
                'paid_amount' => 0,
            ]],
        ]);

        $item = $deliveryNote->items()->first();

        $this->assertEquals(0.0, (float) $item->unit_price);
        $this->assertEquals(0.0, (float) $item->vat_rate);
        $this->assertEquals(0.0, (float) $item->line_subtotal);
        $this->assertEquals(0.0, (float) $item->line_vat);
        $this->assertEquals(0.0, (float) $item->line_total);
        $this->assertEquals(0.0, (float) $deliveryNote->fresh()->subtotal);
        $this->assertEquals(0.0, (float) $deliveryNote->fresh()->vat_total);
        $this->assertEquals(0.0, (float) $deliveryNote->fresh()->total);
        $this->assertNull($deliveryNote->fresh()->payment_method);
        $this->assertSame(0, $deliveryNote->paymentSchedules()->count());
    }

    public function test_delivery_note_generated_from_order_does_not_copy_amounts_or_payments(): void
    {
        $order = AdminDocument::create([
            'type' => 'offline_order',
            'number' => 6,
            'year' => 2026,
            'code' => 'OFF-6',
            'document_date' => '2026-05-29',
            'status' => 'confirmed',
            'payment_status' => 'unpaid',
            'payment_conditions' => 'TP02',
            'payment_method' => 'MP05',
            'currency' => 'EUR',
            'customer_name' => 'Mario Rossi',
            'customer_country' => 'IT',
            'subtotal' => 100,
            'vat_total' => 22,
            'total' => 122,
        ]);
        $order->items()->create([
            'position' => 1,
            'description' => 'Scarpe test',
            'quantity' => 1,
            'unit_price' => 100,
            'vat_rate' => 22,
            'line_subtotal' => 100,
            'line_vat' => 22,
            'line_total' => 122,
        ]);
        $order->paymentSchedules()->create([
            'due_date' => '2026-05-29',
            'method' => 'Bonifico bancario',
            'payment_method_code' => 'MP05',
            'amount' => 122,
            'paid_amount' => 0,
            'status' => 'unpaid',
        ]);

        $deliveryNote = app(DocumentGeneratorService::class)->fromOrderToDeliveryNote($order->id);
        $item = $deliveryNote->items()->first();

        $this->assertEquals(0.0, (float) $item->unit_price);
        $this->assertEquals(0.0, (float) $item->line_total);
        $this->assertEquals(0.0, (float) $deliveryNote->fresh()->total);
        $this->assertNull($deliveryNote->fresh()->payment_method);
        $this->assertSame(0, $deliveryNote->paymentSchedules()->count());
    }

    public function test_admin_can_link_existing_documents_manually(): void
    {
        $admin = AdminUser::create([
            'name' => 'Admin Test',
            'email' => 'admin-manual-link@example.test',
            'password' => 'password',
            'role' => 'owner',
            'is_active' => true,
        ]);

        $order = AdminDocument::create([
            'type' => 'offline_order',
            'number' => 9,
            'year' => 2026,
            'code' => 'OFF-9',
            'document_date' => '2026-05-29',
            'status' => 'confirmed',
            'payment_status' => 'unpaid',
            'payment_conditions' => 'TP02',
            'currency' => 'EUR',
            'customer_name' => 'Mario Rossi',
            'customer_country' => 'IT',
        ]);
        $deliveryNote = AdminDocument::create([
            'type' => 'delivery_note',
            'number' => 10,
            'year' => 2026,
            'code' => 'DDT-10',
            'document_date' => '2026-05-29',
            'status' => 'sent',
            'payment_status' => 'unpaid',
            'payment_conditions' => 'TP02',
            'currency' => 'EUR',
            'customer_name' => 'Mario Rossi',
            'customer_country' => 'IT',
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.documents.relations.store', $order), [
                'related_document_id' => $deliveryNote->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('document_relations', [
            'from_type' => 'delivery_note',
            'from_id' => $deliveryNote->id,
            'to_type' => 'order',
            'to_id' => $order->id,
            'relation_type' => 'manual',
        ]);
        $this->assertSame($order->id, $deliveryNote->fresh()->source_document_id);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.documents.show', $order))
            ->assertOk()
            ->assertSee('Collega documento esistente')
            ->assertSee('DDT DDT-10');
    }

    public function test_shipping_address_can_be_copied_from_customer_on_save(): void
    {
        $admin = AdminUser::create([
            'name' => 'Admin Test',
            'email' => 'admin-shipping-copy@example.test',
            'password' => 'password',
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.documents.store'), [
                'type' => 'offline_order',
                'document_date' => '2026-07-15',
                'status' => 'draft',
                'currency' => 'EUR',
                'payment_conditions' => 'TP02',
                'customer_name' => 'Mario Rossi',
                'customer_phone' => '3331234567',
                'customer_address' => 'Via Roma',
                'customer_street_number' => '12',
                'customer_city' => 'Milano',
                'customer_province' => 'mi',
                'customer_postal_code' => '20100',
                'customer_country' => 'it',
                'shipping_same_as_customer' => '1',
                'items' => [[
                    'description' => 'Articolo test',
                    'quantity' => 1,
                    'unit_price' => 10,
                    'vat_rate' => 22,
                ]],
            ])
            ->assertRedirect();

        $document = AdminDocument::latest('id')->firstOrFail();

        $this->assertSame('Mario Rossi', $document->shipping_name);
        $this->assertSame('3331234567', $document->shipping_phone);
        $this->assertSame('Via Roma', $document->shipping_address);
        $this->assertSame('12', $document->shipping_street_number);
        $this->assertSame('Milano', $document->shipping_city);
        $this->assertSame('MI', $document->shipping_province);
        $this->assertSame('20100', $document->shipping_postal_code);
        $this->assertSame('IT', $document->shipping_country);
    }
}
