<?php

namespace App\Services;

use App\Models\AdminDocument;
use App\Models\DocumentsPaymentMethod;
use Exception;
use Illuminate\Support\Facades\DB;
use SimpleXMLElement;

class AdminDocumentXmlImportService
{
    public function import(string $path, ?string $filename = null, bool $markAsPaid = false): AdminDocument
    {
        if (! file_exists($path)) {
            throw new Exception('File XML non trovato.');
        }

        $xmlContent = file_get_contents($path);
        $hash = hash('sha256', $xmlContent);

        if (AdminDocument::where('xml_hash', $hash)->exists()) {
            throw new Exception('Questa fattura è già stata importata.');
        }

        $xml = simplexml_load_string($xmlContent);

        if (! $xml) {
            throw new Exception('XML non valido.');
        }

        return DB::transaction(function () use ($xml, $hash, $filename, $path, $markAsPaid) {
            $documentData = $this->node($xml, 'FatturaElettronicaBody/DatiGenerali/DatiGeneraliDocumento');
            $customer = $this->node($xml, 'FatturaElettronicaHeader/CessionarioCommittente');
            $transmission = $this->node($xml, 'FatturaElettronicaHeader/DatiTrasmissione');

            if (! $documentData || ! $customer) {
                throw new Exception('XML FatturaPA incompleto.');
            }

            $documentDate = $this->value($this->node($documentData, 'Data')) ?: now()->toDateString();
            $rawNumber = $this->value($this->node($documentData, 'Numero')) ?: null;
            $year = (int) substr($documentDate, 0, 4);
            $number = $this->numericNumber($rawNumber) ?: $this->nextInvoiceNumber($year);
            $code = $rawNumber ?: 'FAT-'.$number;

            if (AdminDocument::where('type', 'invoice')->where('year', $year)->where('code', $code)->exists()) {
                throw new Exception("Fattura {$code} già presente.");
            }

            $totals = $this->totals($xml);
            $firstPayment = $this->node($xml, 'FatturaElettronicaBody/DatiPagamento/DettaglioPagamento');
            $paymentMethod = $this->value($this->node($firstPayment, 'ModalitaPagamento')) ?: 'MP05';

            $document = AdminDocument::create([
                'type' => 'invoice',
                'fiscal_type' => $this->value($this->node($documentData, 'TipoDocumento')) ?: 'TD01',
                'number' => $number,
                'year' => $year,
                'code' => $code,
                'document_date' => $documentDate,
                'status' => 'sent',
                'payment_status' => 'unpaid',
                'payment_conditions' => $this->value($this->node($xml, 'FatturaElettronicaBody/DatiPagamento/CondizioniPagamento')) ?: 'TP02',
                'payment_method' => $paymentMethod,
                'bank_name' => $this->value($this->node($firstPayment, 'IstitutoFinanziario')),
                'bank_iban' => $this->value($this->node($firstPayment, 'IBAN')),
                'bank_bic' => $this->value($this->node($firstPayment, 'BIC')),
                'currency' => $this->value($this->node($documentData, 'Divisa')) ?: 'EUR',
                ...$this->customerAttributes($customer, $transmission),
                'subtotal' => $totals['subtotal'],
                'vat_total' => $totals['vat_total'],
                'total' => $totals['total'],
                'xml_filename' => $filename ?: basename($path),
                'xml_hash' => $hash,
                'xml_imported' => true,
            ]);

            $this->importItems($document, $xml);
            $this->importPayments($document, $xml, $markAsPaid);
            $document->refreshTotals();

            return $document->fresh(['items', 'paymentSchedules']);
        });
    }

    private function customerAttributes(SimpleXMLElement $customer, ?SimpleXMLElement $transmission): array
    {
        $registry = $this->node($customer, 'DatiAnagrafici');
        $name = $this->value($this->node($registry, 'Anagrafica/Denominazione'));

        if (! $name) {
            $name = trim(($this->value($this->node($registry, 'Anagrafica/Nome')) ?: '').' '.($this->value($this->node($registry, 'Anagrafica/Cognome')) ?: ''));
        }

        $address = $this->node($customer, 'Sede');

        return [
            'customer_name' => $name ?: 'Cliente importato',
            'customer_vat_number' => $this->value($this->node($registry, 'IdFiscaleIVA/IdCodice')),
            'customer_tax_code' => $this->value($this->node($registry, 'CodiceFiscale')),
            'customer_recipient_code' => $this->value($this->node($transmission, 'CodiceDestinatario')),
            'customer_pec' => $this->value($this->node($transmission, 'PECDestinatario')),
            'customer_address' => $this->value($this->node($address, 'Indirizzo')),
            'customer_street_number' => $this->value($this->node($address, 'NumeroCivico')),
            'customer_postal_code' => $this->value($this->node($address, 'CAP')),
            'customer_city' => $this->value($this->node($address, 'Comune')),
            'customer_province' => $this->value($this->node($address, 'Provincia')),
            'customer_country' => $this->value($this->node($address, 'Nazione')) ?: 'IT',
        ];
    }

    private function importItems(AdminDocument $document, SimpleXMLElement $xml): void
    {
        foreach ($this->nodes($xml, 'FatturaElettronicaBody/DatiBeniServizi/DettaglioLinee') as $index => $line) {
            $quantity = (float) ($this->value($this->node($line, 'Quantita')) ?: 1);
            $unitPrice = (float) ($this->value($this->node($line, 'PrezzoUnitario')) ?: 0);
            $vatRate = (float) ($this->value($this->node($line, 'AliquotaIVA')) ?: 0);
            $lineSubtotal = (float) ($this->value($this->node($line, 'PrezzoTotale')) ?: ($quantity * $unitPrice));
            $lineVat = round($lineSubtotal * $vatRate / 100, 2);

            $document->items()->create([
                'position' => (int) ($this->value($this->node($line, 'NumeroLinea')) ?: ($index + 1)),
                'description' => $this->value($this->node($line, 'Descrizione')) ?: 'Riga importata',
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'vat_rate' => $vatRate,
                'line_subtotal' => $lineSubtotal,
                'line_vat' => $lineVat,
                'line_total' => $lineSubtotal + $lineVat,
            ]);
        }
    }

    private function importPayments(AdminDocument $document, SimpleXMLElement $xml, bool $markAsPaid): void
    {
        foreach ($this->nodes($xml, 'FatturaElettronicaBody/DatiPagamento/DettaglioPagamento') as $payment) {
            $methodCode = $this->value($this->node($payment, 'ModalitaPagamento')) ?: 'MP05';
            $amount = (float) ($this->value($this->node($payment, 'ImportoPagamento')) ?: $document->total);
            $dueDate = $this->value($this->node($payment, 'DataScadenzaPagamento')) ?: $document->document_date;

            $document->paymentSchedules()->create([
                'due_date' => $dueDate,
                'method' => $this->paymentMethodName($methodCode),
                'payment_method_code' => $methodCode,
                'amount' => $amount,
                'paid_amount' => $markAsPaid ? $amount : 0,
                'paid_at' => $markAsPaid ? $dueDate : null,
                'status' => $markAsPaid ? 'paid' : 'unpaid',
            ]);
        }
    }

    private function totals(SimpleXMLElement $xml): array
    {
        $subtotal = 0.0;
        $vatTotal = 0.0;

        foreach ($this->nodes($xml, 'FatturaElettronicaBody/DatiBeniServizi/DatiRiepilogo') as $summary) {
            $subtotal += (float) ($this->value($this->node($summary, 'ImponibileImporto')) ?: 0);
            $vatTotal += (float) ($this->value($this->node($summary, 'Imposta')) ?: 0);
        }

        $documentTotal = (float) ($this->value($this->node($xml, 'FatturaElettronicaBody/DatiGenerali/DatiGeneraliDocumento/ImportoTotaleDocumento')) ?: 0);

        return [
            'subtotal' => $subtotal,
            'vat_total' => $vatTotal,
            'total' => $documentTotal > 0 ? $documentTotal : $subtotal + $vatTotal,
        ];
    }

    private function nodes(SimpleXMLElement $xml, string $path): array
    {
        return $xml->xpath($this->xpath($path)) ?: [];
    }

    private function node(?SimpleXMLElement $xml, string $path): ?SimpleXMLElement
    {
        if (! $xml) {
            return null;
        }

        return $this->nodes($xml, $path)[0] ?? null;
    }

    private function value(?SimpleXMLElement $node): ?string
    {
        $value = trim((string) $node);

        return $value === '' ? null : $value;
    }

    private function xpath(string $path): string
    {
        return collect(explode('/', $path))
            ->filter()
            ->map(fn (string $segment) => '*[local-name()="'.$segment.'"]')
            ->implode('/');
    }

    private function numericNumber(?string $rawNumber): ?int
    {
        if ($rawNumber && preg_match('/(\d+)/', $rawNumber, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    private function nextInvoiceNumber(int $year): int
    {
        return ((int) AdminDocument::query()
            ->where('type', 'invoice')
            ->where('year', $year)
            ->max('number')) + 1;
    }

    private function paymentMethodName(?string $code): string
    {
        return DocumentsPaymentMethod::query()
            ->where('code', $code ?: 'MP05')
            ->value('name') ?: 'Bonifico bancario';
    }
}
