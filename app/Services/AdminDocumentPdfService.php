<?php

namespace App\Services;

use App\Models\AdminDocument;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AdminDocumentPdfService
{
    public function output(AdminDocument $document): string
    {
        $document->loadMissing(['items', 'paymentSchedules']);

        $columns = [
            ['Codice', 30.50],
            ['Descrizione', 79.00],
            ['U.M.', 7.00],
            ['Q.ta', 21.50],
            ['Prezzo', 20.50],
            ['Sc.', 10.00],
            ['Importo', 18.5],
            ['C.I.', 7.00],
        ];

        $pdf = new TcpdfDocumentoService;
        $pdf->setDocumentType($document->type === 'delivery_note', $document->type === 'quote');
        $pdf->header_data = $this->headerData($document);
        $pdf->footer_data = $this->footerData($document);
        $pdf->SetTitle($pdf->header_data['tipo_documento'].' nr. '.$document->display_code);

        $pdf->AddPage();
        $pdf->drawTopHeader();
        $pdf->SetY($pdf->Y_TAB_START);
        $pdf->drawHeader($columns);

        $this->drawRows($pdf, $columns, $this->productRows($document));

        return $pdf->Output('', 'S');
    }

    public function filename(AdminDocument $document): string
    {
        return Str::slug($document->type_label.' '.$document->display_code).'.pdf';
    }

    private function headerData(AdminDocument $document): array
    {
        [$taxLabel, $taxValue] = $this->taxData($document);

        $singlePayment = $document->paymentSchedules->count() === 1
            ? $document->paymentSchedules->first()
            : null;

        return [
            'logo' => public_path('assets/logos/logo-stuart.png'),
            'company_address' => array_values(array_filter(config('documents.company_address'))),
            'billing_address' => array_values(array_filter([
                Str::upper($document->customer_name),
                Str::upper($this->addressLine($document)),
                Str::upper(trim(($document->customer_postal_code ? $document->customer_postal_code.' ' : '').($document->customer_city ?: '').($document->customer_province ? ' ('.$document->customer_province.')' : ''))),
                $this->taxLine($document),
                $document->customer_recipient_code ? 'Cod.Dest. '.Str::upper($document->customer_recipient_code) : '',
                $document->customer_pec ? 'PEC '.$document->customer_pec : '',
            ])),
            'shipping_address' => [],
            'tipo_documento' => $this->documentTitle($document),
            'numero_documento' => $document->display_code,
            'data_documento' => $document->document_date->format('d/m/Y'),
            'cliente' => sprintf('%05d', $document->id),
            'tax_label' => $taxLabel,
            'tax_value' => $taxValue,
            'codice_destinatario' => Str::upper((string) $document->customer_recipient_code),
            'cod_agente' => '',
            'cod_iban' => '',
            'cod_bic' => '',
            'cod_pag' => $singlePayment?->method ?: '',
            'descrizione_pagamento' => $singlePayment?->method ?: '',
            'banca_appoggio' => '',
            'annotazioni' => '',
            'invio_fattura' => $document->customer_email ?: '',
            'reference_name' => '',
            'reference_phone' => '',
        ];
    }

    private function footerData(AdminDocument $document): array
    {
        return [
            'scadenze_pagamento' => $this->paymentDeadlines($document),
            'aliquote_descrizioni_imponibili_iva' => $this->vatSummaries($document),
            'totale_merci_lordo' => number_format((float) $document->subtotal, 2, ',', '.'),
            'totale_merci_netto' => number_format((float) $document->subtotal, 2, ',', '.'),
            'totale_imponibile' => number_format((float) $document->subtotal, 2, ',', '.'),
            'totale_iva' => number_format((float) $document->vat_total, 2, ',', '.'),
            'totale_fattura' => number_format((float) $document->total, 2, ',', '.'),
            'causale_trasporto' => 'Vendita',
            'trasporto_cura' => '',
            'data_inizio_trasporto' => optional($document->document_date)->format('d/m/Y'),
            'aspetto_beni' => '',
            'n_colli' => '',
            'peso_lordo' => '',
            'peso_netto' => '',
            'carrier' => '',
        ];
    }

    private function drawRows(TcpdfDocumentoService $pdf, array $columns, array $products): void
    {
        $yStartTable = $pdf->GetY();
        $maxHeight = 110;
        $lastRowHeight = 6.5;
        $pdf->isLastPage = false;

        foreach ($products as $product) {
            $values = [
                $product['codice'],
                $product['descrizione'],
                $product['um'],
                $product['qta'],
                $product['prezzo'],
                $product['sconto'],
                $product['importo'],
                $product['ci'],
            ];

            $rowHeight = 0;
            foreach ($columns as $index => $column) {
                $rowHeight = max($rowHeight, $pdf->getTextHeight($column[1], (string) ($values[$index] ?? '')));
            }

            if (($pdf->GetY() + $rowHeight) - $yStartTable > $maxHeight) {
                $remaining = $maxHeight - ($pdf->GetY() - $yStartTable);
                while ($remaining > 0) {
                    $pdf->drawEmptyRow($columns, $lastRowHeight);
                    $remaining -= $lastRowHeight;
                }

                $pdf->drawFooterBlocco();
                $pdf->AddPage();
                $pdf->drawTopHeader();
                $pdf->SetY($pdf->Y_TAB_START);
                $pdf->drawHeader($columns);
                $yStartTable = $pdf->GetY();
            }

            $pdf->drawProductRow($columns, $values, $rowHeight);
            $lastRowHeight = $rowHeight;
        }

        $remaining = $maxHeight - ($pdf->GetY() - $yStartTable);
        while ($remaining > 0) {
            $pdf->drawEmptyRow($columns, $lastRowHeight);
            $remaining -= $lastRowHeight;
        }

        $pdf->isLastPage = true;
        $pdf->drawFooterBlocco();
    }

    private function productRows(AdminDocument $document): array
    {
        return $document->items->map(fn ($item) => [
            'codice' => '',
            'descrizione' => $item->description,
            'um' => 'NR',
            'qta' => number_format((float) $item->quantity, 2, ',', '.'),
            'prezzo' => number_format((float) $item->unit_price, 2, ',', '.'),
            'sconto' => '',
            'importo' => number_format((float) $item->line_total, 2, ',', '.'),
            'ci' => number_format((float) $item->vat_rate, 0),
        ])->all();
    }

    private function vatSummaries(AdminDocument $document): Collection
    {
        return $document->items
            ->groupBy(fn ($item) => (string) $item->vat_rate)
            ->map(fn ($items, $vatRate) => (object) [
                'vat_rate' => (float) $vatRate,
                'taxable_amount' => (float) $items->sum('line_subtotal'),
                'vat_amount' => (float) $items->sum('line_vat'),
            ])
            ->values();
    }

    private function paymentDeadlines(AdminDocument $document): string
    {
        return $document->paymentSchedules
            ->map(fn ($payment) => trim(($payment->method ?: 'Pagamento').' € '.number_format((float) $payment->amount, 2, ',', '.').' scad. '.$payment->due_date->format('d/m/Y')))
            ->implode("\n");
    }

    private function documentTitle(AdminDocument $document): string
    {
        return match ($document->type) {
            'quote' => 'Preventivo',
            'offline_order' => 'Conferma ordine',
            'delivery_note' => 'Documento di trasporto',
            'invoice' => 'Fattura riepilogativa',
            default => $document->type_label,
        };
    }

    private function taxData(AdminDocument $document): array
    {
        if ($document->customer_vat_number && $document->customer_tax_code && $document->customer_vat_number === $document->customer_tax_code) {
            return ['P.Iva/CF', $document->customer_vat_number];
        }

        if ($document->customer_vat_number) {
            return ['P.Iva', $document->customer_vat_number];
        }

        if ($document->customer_tax_code) {
            return ['Codice fiscale', $document->customer_tax_code];
        }

        return ['', ''];
    }

    private function taxLine(AdminDocument $document): string
    {
        [$label, $value] = $this->taxData($document);

        return trim($label.' '.$value);
    }

    private function addressLine(AdminDocument $document): string
    {
        return trim(collect([$document->customer_address, $document->customer_street_number])
            ->filter(fn ($value) => filled($value))
            ->implode(' '));
    }
}
