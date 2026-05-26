<?php

namespace App\Services;

use App\Models\AdminDocument;
use DOMDocument;
use DOMElement;
use Illuminate\Support\Str;
use InvalidArgumentException;

class AdminDocumentXmlService
{
    public function output(AdminDocument $document): string
    {
        if ($document->type !== 'invoice') {
            throw new InvalidArgumentException('Export XML disponibile solo per le fatture.');
        }

        $document->loadMissing(['items', 'paymentSchedules.paymentMethod']);

        $xml = new DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;

        $root = $xml->createElement('p:FatturaElettronica');
        $root->setAttribute('versione', 'FPR12');
        $root->setAttribute('xmlns:p', 'http://ivaservizi.agenziaentrate.gov.it/docs/xsd/fatture/v1.2');
        $root->setAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $root->setAttribute('xsi:schemaLocation', 'http://ivaservizi.agenziaentrate.gov.it/docs/xsd/fatture/v1.2 http://www.fatturapa.gov.it/export/fatturazione/sdi/fatturapa/v1.2/Schema_del_file_xml_FatturaPA_versione_1.2.xsd');
        $xml->appendChild($root);

        $header = $this->append($xml, $root, 'FatturaElettronicaHeader');
        $this->appendTransmissionData($xml, $header, $document);
        $this->appendSeller($xml, $header);
        $this->appendCustomer($xml, $header, $document);

        $body = $this->append($xml, $root, 'FatturaElettronicaBody');
        $general = $this->append($xml, $body, 'DatiGenerali');
        $documentData = $this->append($xml, $general, 'DatiGeneraliDocumento');
        $this->append($xml, $documentData, 'TipoDocumento', $document->fiscal_type ?: 'TD01');
        $this->append($xml, $documentData, 'Divisa', $document->currency ?: 'EUR');
        $this->append($xml, $documentData, 'Data', $document->document_date->format('Y-m-d'));
        $this->append($xml, $documentData, 'Numero', $document->display_code);
        $this->append($xml, $documentData, 'ImportoTotaleDocumento', $this->money($document->total));

        $goods = $this->append($xml, $body, 'DatiBeniServizi');
        foreach ($document->items as $index => $item) {
            $line = $this->append($xml, $goods, 'DettaglioLinee');
            $this->append($xml, $line, 'NumeroLinea', (string) ($index + 1));
            if ($item->item_code) {
                $articleCode = $this->append($xml, $line, 'CodiceArticolo');
                $this->append($xml, $articleCode, 'CodiceTipo', 'CODICE');
                $this->append($xml, $articleCode, 'CodiceValore', $item->item_code);
            }
            $this->append($xml, $line, 'Descrizione', $item->description);
            $this->append($xml, $line, 'Quantita', $this->decimal($item->quantity));
            $this->append($xml, $line, 'PrezzoUnitario', $this->money($item->unit_price));
            $this->append($xml, $line, 'PrezzoTotale', $this->money($item->line_subtotal));
            $this->append($xml, $line, 'AliquotaIVA', $this->decimal($item->vat_rate));
        }

        foreach ($document->items->groupBy(fn ($item) => (string) $item->vat_rate) as $vatRate => $items) {
            $summary = $this->append($xml, $goods, 'DatiRiepilogo');
            $this->append($xml, $summary, 'AliquotaIVA', $this->decimal($vatRate));
            $this->append($xml, $summary, 'ImponibileImporto', $this->money($items->sum('line_subtotal')));
            $this->append($xml, $summary, 'Imposta', $this->money($items->sum('line_vat')));
            $this->append($xml, $summary, 'EsigibilitaIVA', 'I');
        }

        if ($document->paymentSchedules->isNotEmpty()) {
            $payments = $this->append($xml, $body, 'DatiPagamento');
            $this->append($xml, $payments, 'CondizioniPagamento', $document->payment_conditions ?: 'TP02');
            foreach ($document->paymentSchedules as $payment) {
                $detail = $this->append($xml, $payments, 'DettaglioPagamento');
                $this->append($xml, $detail, 'ModalitaPagamento', $payment->payment_method_code ?: 'MP05');
                $this->append($xml, $detail, 'DataScadenzaPagamento', $payment->due_date->format('Y-m-d'));
                $this->append($xml, $detail, 'ImportoPagamento', $this->money($payment->amount));
                if (($payment->payment_method_code ?: 'MP05') === 'MP05') {
                    if ($this->bankName($document)) {
                        $this->append($xml, $detail, 'IstitutoFinanziario', $this->bankName($document));
                    }
                    if ($this->bankIban($document)) {
                        $this->append($xml, $detail, 'IBAN', $this->bankIban($document));
                    }
                    if ($this->bankBic($document)) {
                        $this->append($xml, $detail, 'BIC', $this->bankBic($document));
                    }
                }
            }
        }

        return $xml->saveXML();
    }

    public function filename(AdminDocument $document): string
    {
        $company = config('documents.company');
        $sender = $this->upper(($company['country'] ?? 'IT').preg_replace('/\W+/', '', (string) ($company['vat_number'] ?? '')));
        $progressive = str_pad(strtoupper(base_convert((string) max(1, (int) $document->id), 10, 36)), 5, '0', STR_PAD_LEFT);

        return $sender.'_'.substr($progressive, -5).'.xml';
    }

    private function appendTransmissionData(DOMDocument $xml, DOMElement $header, AdminDocument $document): void
    {
        $company = config('documents.company');
        $data = $this->append($xml, $header, 'DatiTrasmissione');
        $sender = $this->append($xml, $data, 'IdTrasmittente');
        $this->append($xml, $sender, 'IdPaese', $company['country']);
        $this->append($xml, $sender, 'IdCodice', $company['vat_number']);
        $this->append($xml, $data, 'ProgressivoInvio', (string) ($document->number ?: $document->id));
        $this->append($xml, $data, 'FormatoTrasmissione', 'FPR12');
        $this->append($xml, $data, 'CodiceDestinatario', $this->upper($document->customer_recipient_code ?: '0000000'));
        if ($document->customer_pec) {
            $this->append($xml, $data, 'PECDestinatario', $document->customer_pec);
        }
    }

    private function appendSeller(DOMDocument $xml, DOMElement $header): void
    {
        $company = config('documents.company');
        $seller = $this->append($xml, $header, 'CedentePrestatore');
        $registry = $this->append($xml, $seller, 'DatiAnagrafici');
        $vat = $this->append($xml, $registry, 'IdFiscaleIVA');
        $this->append($xml, $vat, 'IdPaese', $company['country']);
        $this->append($xml, $vat, 'IdCodice', $company['vat_number']);
        $this->append($xml, $registry, 'CodiceFiscale', $company['tax_code']);
        $name = $this->append($xml, $registry, 'Anagrafica');
        $this->append($xml, $name, 'Denominazione', $company['name']);
        $this->append($xml, $registry, 'RegimeFiscale', $company['regime_fiscale']);
        $this->appendAddress($xml, $seller, 'Sede', $company);
    }

    private function appendCustomer(DOMDocument $xml, DOMElement $header, AdminDocument $document): void
    {
        $customer = $this->append($xml, $header, 'CessionarioCommittente');
        $registry = $this->append($xml, $customer, 'DatiAnagrafici');
        if ($document->customer_vat_number) {
            $vat = $this->append($xml, $registry, 'IdFiscaleIVA');
            $this->append($xml, $vat, 'IdPaese', $this->upper($document->customer_country ?: 'IT'));
            $this->append($xml, $vat, 'IdCodice', $this->upper($document->customer_vat_number));
        }
        if ($document->customer_tax_code) {
            $this->append($xml, $registry, 'CodiceFiscale', $this->upper($document->customer_tax_code));
        }
        $name = $this->append($xml, $registry, 'Anagrafica');
        $this->append($xml, $name, 'Denominazione', $this->upper($document->customer_name));
        $this->appendAddress($xml, $customer, 'Sede', [
            'address' => $this->upper($document->customer_address ?: '.'),
            'street_number' => $this->upper($document->customer_street_number ?: ''),
            'postal_code' => $document->customer_postal_code ?: '00000',
            'city' => $this->upper($document->customer_city ?: '.'),
            'province' => $this->upper($document->customer_province ?: ''),
            'country' => $this->upper($document->customer_country ?: 'IT'),
        ]);
    }

    private function appendAddress(DOMDocument $xml, DOMElement $parent, string $nodeName, array $data): void
    {
        $address = $this->append($xml, $parent, $nodeName);
        $this->append($xml, $address, 'Indirizzo', $data['address']);
        if ($data['street_number']) {
            $this->append($xml, $address, 'NumeroCivico', $data['street_number']);
        }
        $this->append($xml, $address, 'CAP', $data['postal_code']);
        $this->append($xml, $address, 'Comune', $data['city']);
        if ($data['province']) {
            $this->append($xml, $address, 'Provincia', $data['province']);
        }
        $this->append($xml, $address, 'Nazione', $data['country']);
    }

    private function append(DOMDocument $xml, DOMElement $parent, string $name, ?string $value = null): DOMElement
    {
        $element = $xml->createElement($name);
        if ($value !== null) {
            $element->appendChild($xml->createTextNode($value));
        }
        $parent->appendChild($element);

        return $element;
    }

    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function decimal(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function bankName(AdminDocument $document): string
    {
        return (string) config('documents.bank.name', '') ?: ($document->bank_name ?: '');
    }

    private function bankIban(AdminDocument $document): string
    {
        return (string) config('documents.bank.iban', '') ?: ($document->bank_iban ?: '');
    }

    private function bankBic(AdminDocument $document): string
    {
        return (string) config('documents.bank.bic', '') ?: ($document->bank_bic ?: '');
    }

    private function upper(?string $value): string
    {
        return Str::upper((string) $value);
    }
}
