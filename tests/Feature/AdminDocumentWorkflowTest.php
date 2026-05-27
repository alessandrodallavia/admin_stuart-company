<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\DocumentController;
use App\Models\AdminDocument;
use App\Models\AdminUser;
use App\Services\AdminDocumentService;
use App\Services\AdminDocumentXmlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class AdminDocumentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_documents_index_is_grouped_by_document_area(): void
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
            ->assertSee('Aree documenti')
            ->assertSee('Archivio per area')
            ->assertSee('Preventivo PREV-1')
            ->assertSee('Fattura FPR 7/26')
            ->assertSee('Apri area');
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
            ->assertSee('+ Fattura')
            ->assertDontSee('+ Ordine offline')
            ->assertDontSee('+ Preventivo');
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
            ->assertSee('Pagamenti')
            ->assertSee('Importa XML')
            ->assertSee('Export SDI Aruba');

        $this->actingAs($admin, 'admin')
            ->get('/documents?type=offline_order')
            ->assertOk()
            ->assertDontSee('Pagamenti')
            ->assertDontSee('Importa XML')
            ->assertDontSee('Export SDI Aruba');
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
}
