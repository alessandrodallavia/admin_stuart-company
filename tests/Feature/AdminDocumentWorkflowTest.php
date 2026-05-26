<?php

namespace Tests\Feature;

use App\Models\AdminDocument;
use App\Services\AdminDocumentService;
use App\Services\AdminDocumentXmlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDocumentWorkflowTest extends TestCase
{
    use RefreshDatabase;

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
        $this->assertStringContainsString('<DatiPagamento>', $service->output($invoice));
        $this->assertStringContainsString('<ImportoPagamento>122.00</ImportoPagamento>', $service->output($invoice));
    }
}
