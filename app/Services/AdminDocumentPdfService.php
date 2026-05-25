<?php

namespace App\Services;

use App\Models\AdminDocument;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AdminDocumentPdfService
{
    public function output(AdminDocument $document): string
    {
        $document->loadMissing(['items', 'paymentSchedules.paymentMethod', 'paymentMethod']);

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
        [$taxLabel, $taxValue] = $this->taxHeaderData($document);

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
            'cliente' => '00000',
            'tax_label' => $taxLabel,
            'tax_value' => $taxValue,
            'codice_destinatario' => Str::upper((string) $document->customer_recipient_code),
            'cod_agente' => '',
            'cod_iban' => $this->bankIban($document),
            'cod_bic' => $this->bankBic($document),
            'cod_pag' => $singlePayment?->payment_method_code ?: $document->payment_method ?: '',
            'descrizione_pagamento' => $singlePayment ? $this->paymentLabel($document, $singlePayment) : ($document->paymentMethod?->name ?: ''),
            'banca_appoggio' => $this->bankName($document),
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
                $this->fillTableSpace($pdf, $columns, $remaining, $lastRowHeight);

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
        $this->fillTableSpace($pdf, $columns, $remaining, $lastRowHeight);

        $pdf->isLastPage = true;
        $pdf->drawFooterBlocco();
    }

    private function fillTableSpace(TcpdfDocumentoService $pdf, array $columns, float $remaining, float $preferredRowHeight): void
    {
        while ($remaining > 0.2) {
            $rowHeight = min($preferredRowHeight, $remaining);
            $pdf->drawEmptyRow($columns, $rowHeight);
            $remaining -= $rowHeight;
        }
    }

    private function productRows(AdminDocument $document): array
    {
        return $document->items->map(fn ($item) => [
            'codice' => $item->item_code ?: '',
            'descrizione' => $item->description,
            'um' => 'NR',
            'qta' => number_format((float) $item->quantity, 2, ',', '.'),
            'prezzo' => $this->formatUnitPrice($item->unit_price),
            'sconto' => '',
            'importo' => number_format((float) $item->line_subtotal, 2, ',', '.'),
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

    private function formatUnitPrice(mixed $value): string
    {
        $formatted = number_format((float) $value, 4, ',', '.');
        $formatted = preg_replace('/0{1,2}$/', '', $formatted);

        return rtrim($formatted, ',');
    }

    private function paymentDeadlines(AdminDocument $document): string
    {
        return $document->paymentSchedules
            ->map(fn ($payment) => trim($this->paymentDeadlineLabel($document, $payment).' € '.number_format((float) $payment->amount, 2, ',', '.').' scad. '.$payment->due_date->format('d/m/Y')))
            ->implode("\n");
    }

    private function paymentDeadlineLabel(AdminDocument $document, mixed $payment): string
    {
        return match ($payment->payment_method_code) {
            'MP05' => 'BB',
            'MP12' => $this->ribaLabel($document->document_date, $payment->due_date),
            default => $payment->paymentMethod?->name ?: $payment->method ?: 'Pagamento',
        };
    }

    private function hasBankTransfer(AdminDocument $document): bool
    {
        return $document->payment_method === 'MP05'
            || $document->paymentSchedules->contains(fn ($payment) => $payment->payment_method_code === 'MP05');
    }

    private function bankName(AdminDocument $document): string
    {
        return $this->hasBankTransfer($document) ? (string) config('documents.bank.name', '') : ($document->bank_name ?: '');
    }

    private function bankIban(AdminDocument $document): string
    {
        return $this->hasBankTransfer($document) ? (string) config('documents.bank.iban', '') : ($document->bank_iban ?: '');
    }

    private function bankBic(AdminDocument $document): string
    {
        return $this->hasBankTransfer($document) ? (string) config('documents.bank.bic', '') : ($document->bank_bic ?: '');
    }

    private function paymentLabel(AdminDocument $document, mixed $payment): string
    {
        if ($payment->payment_method_code === 'MP12') {
            return $this->ribaLabel($document->document_date, $payment->due_date);
        }

        return $payment->paymentMethod?->name ?: $payment->method ?: 'Pagamento';
    }

    private function ribaLabel(?CarbonInterface $documentDate, ?CarbonInterface $dueDate): string
    {
        if (! $documentDate || ! $dueDate) {
            return 'RIBA';
        }

        $documentDate = $documentDate->copy()->startOfDay();
        $dueDate = $dueDate->copy()->startOfDay();

        if ($dueDate->isSameDay($dueDate->copy()->endOfMonth())) {
            $months = (int) $documentDate->copy()->startOfMonth()->diffInMonths($dueDate->copy()->startOfMonth());

            return match ($months) {
                0 => 'RIBA f.m.',
                1 => 'RIBA 30 gg f.m.',
                2 => 'RIBA 60 gg f.m.',
                3 => 'RIBA 90 gg f.m.',
                4 => 'RIBA 120 gg f.m.',
                default => 'RIBA',
            };
        }

        $days = (int) $documentDate->diffInDays($dueDate);

        return match (true) {
            abs($days - 30) <= 5 => 'RIBA 30 gg',
            abs($days - 60) <= 5 => 'RIBA 60 gg',
            abs($days - 90) <= 5 => 'RIBA 90 gg',
            abs($days - 120) <= 5 => 'RIBA 120 gg',
            default => 'RIBA',
        };
    }

    private function documentTitle(AdminDocument $document): string
    {
        if ($document->type === 'invoice') {
            return match ($document->fiscal_type ?: 'TD01') {
                'TD01' => 'Fattura riepilogativa',
                'TD02' => 'Fattura di acconto',
                'TD24' => 'Fattura differita',
                'TD04' => 'Nota di credito',
                'TD05' => 'Nota di debito',
                'TD01A' => 'Autofattura',
                'TD16' => 'Integrazione fattura reverse charge interno',
                'TD17' => "Integrazione/autofattura per acquisto servizi all'estero",
                'TD18' => 'Integrazione/autofattura per acquisto di beni intracomunitari',
                default => 'Documento',
            };
        }

        return match ($document->type) {
            'quote' => 'Preventivo',
            'proforma' => 'Proforma',
            'offline_order' => 'Conferma ordine',
            'delivery_note' => 'Documento di trasporto',
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
            return ['CF', $document->customer_tax_code];
        }

        return ['', ''];
    }

    private function taxHeaderData(AdminDocument $document): array
    {
        if ($document->customer_vat_number && $document->customer_tax_code && $document->customer_vat_number === $document->customer_tax_code) {
            return ['P.Iva/CF', $document->customer_vat_number];
        }

        if ($document->customer_vat_number) {
            return ['P.Iva', $document->customer_vat_number];
        }

        if ($document->customer_tax_code) {
            return ['Codice Fiscale', $document->customer_tax_code];
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
