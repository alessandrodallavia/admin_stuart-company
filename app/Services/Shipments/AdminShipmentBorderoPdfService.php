<?php

namespace App\Services\Shipments;

use Illuminate\Support\Collection;
use TCPDF;

class AdminShipmentBorderoPdfService
{
    public function output(Collection $shipments, string $carrier): string
    {
        return $carrier === 'sda'
            ? $this->outputSda($shipments)
            : $this->outputBrt($shipments);
    }

    public function filename(string $carrier): string
    {
        $prefix = $carrier === 'sda' ? 'riepilogo_sda' : 'bordero_brt';

        return $prefix.'_'.now(config('app.display_timezone', 'Europe/Rome'))->format('Ymd_His').'.pdf';
    }

    private function outputBrt(Collection $shipments): string
    {
        $printedAt = now(config('app.display_timezone', 'Europe/Rome'));
        $columns = [
            ['label' => 'Destinatario', 'w' => 60, 'align' => 'L'],
            ['label' => 'Indirizzo', 'w' => 64, 'align' => 'L'],
            ['label' => 'LNA', 'w' => 12, 'align' => 'L'],
            ['label' => 'Rif.', 'w' => 23, 'align' => 'L'],
            ['label' => 'C/Ass.', 'w' => 17, 'align' => 'L'],
            ['label' => 'Colli', 'w' => 13, 'align' => 'C'],
            ['label' => 'Peso', 'w' => 16, 'align' => 'C'],
            ['label' => 'MC', 'w' => 14, 'align' => 'L'],
            ['label' => 'Segnacolli dal', 'w' => 29, 'align' => 'L'],
            ['label' => 'Segnacolli al', 'w' => 29, 'align' => 'L'],
        ];

        $pdf = $this->borderoPdf($columns, $printedAt, 'Borderò BRT');
        $this->setupPdf($pdf, 'Bordero BRT');

        $formatNumber = fn ($value, int $decimals = 0) => number_format((float) $value, $decimals, ',', '.');
        $pageBottom = 200;
        $this->drawSender($pdf, $columns, 'Mittente '.config('services.brt.username').' STUART COMPANY SRLS');

        foreach ($shipments as $shipment) {
            $mainCells = [
                ($this->upper($shipment->recipient_name)."\n"."Tipo Servizio ".$shipment->carrier_response['createResponse']['serviceType']." Cod.Tar. "."000"),
                $this->addressBlock($shipment),
                $shipment->carrier_response['createResponse']['arrivalDepot'] ?? '',
                $shipment->reference ?: '',
                $shipment->cash_on_delivery ? number_format((float) $shipment->cash_on_delivery, 2, ',', '.').' EUR' : '',
                $formatNumber($shipment->parcels_count),
                $formatNumber($shipment->weight_kg, 1),
                $formatNumber($shipment->volume_m3, 3),
                $shipment->parcels->first()?->parcel_id ?: $shipment->tracking_number,
                $shipment->parcels->last()?->parcel_id ?: $shipment->tracking_number,
            ];

            $rowHeight = $this->rowHeight($pdf, $columns, $mainCells);

            if ($pdf->GetY() + $rowHeight + 3.2 > $pageBottom) {
                $pdf->AddPage();
                $this->drawSender($pdf, $columns, 'Mittente '.config('services.brt.username').' STUART COMPANY SRLS');
            }

            $this->drawCells($pdf, $columns, $mainCells, $rowHeight);
            $pdf->Ln(1);
        }

        $this->drawTotals($pdf, $columns, $shipments, $pageBottom);

        return $pdf->Output('', 'S');
    }

    private function outputSda(Collection $shipments): string
    {
        $printedAt = now(config('app.display_timezone', 'Europe/Rome'));
        $columns = [
            ['label' => 'Destinatario', 'w' => 70, 'align' => 'L'],
            ['label' => 'Indirizzo', 'w' => 74, 'align' => 'L'],
            ['label' => 'Rif.', 'w' => 27, 'align' => 'L'],
            ['label' => 'C/Ass.', 'w' => 20, 'align' => 'L'],
            ['label' => 'Colli', 'w' => 14, 'align' => 'C'],
            ['label' => 'Peso', 'w' => 15, 'align' => 'C'],
            ['label' => 'MC', 'w' => 13, 'align' => 'L'],
            ['label' => 'Lettera di vettura', 'w' => 45, 'align' => 'L'],
        ];

        $pdf = $this->borderoPdf($columns, $printedAt, 'Riepilogo SDA');
        $this->setupPdf($pdf, 'Riepilogo SDA');

        $formatNumber = fn ($value, int $decimals = 0) => number_format((float) $value, $decimals, ',', '.');
        $pageBottom = 200;
        $this->drawSender($pdf, $columns, 'Mittente STUART COMPANY SRLS');

        foreach ($shipments as $shipment) {
            $cells = [
                $this->upper($shipment->recipient_name),
                $this->addressBlock($shipment),
                $shipment->reference ?: '',
                $shipment->cash_on_delivery ? number_format((float) $shipment->cash_on_delivery, 2, ',', '.').' EUR' : '',
                $formatNumber($shipment->parcels_count),
                $formatNumber($shipment->weight_kg, 1),
                $formatNumber($shipment->volume_m3, 2),
                $shipment->parcels->first()?->parcel_id ?: $shipment->tracking_number,
            ];

            $rowHeight = $this->rowHeight($pdf, $columns, $cells);

            if ($pdf->GetY() + $rowHeight + 3.5 > $pageBottom) {
                $pdf->AddPage();
                $this->drawSender($pdf, $columns, 'Mittente STUART COMPANY SRLS');
            }

            $this->drawCells($pdf, $columns, $cells, $rowHeight);
            $pdf->Ln(1);
        }

        $this->drawTotals($pdf, $columns, $shipments, $pageBottom);

        return $pdf->Output('', 'S');
    }

    private function borderoPdf(array $columns, $printedAt, string $title): TCPDF
    {
        return new class('L', 'mm', 'A4', true, 'UTF-8', false, $columns, $printedAt, $title) extends TCPDF
        {
            private array $borderoColumns;

            private $printedAt;

            private string $titleText;

            public function __construct($orientation, $unit, $format, $unicode, $encoding, $diskcache, array $columns, $printedAt, string $titleText)
            {
                parent::__construct($orientation, $unit, $format, $unicode, $encoding, $diskcache);

                $this->borderoColumns = $columns;
                $this->printedAt = $printedAt;
                $this->titleText = $titleText;
            }

            public function Header(): void
            {
                $this->SetTextColor(0, 0, 0);
                $this->SetDrawColor(0, 0, 0);
                $this->SetLineWidth(0.2);
                $this->SetCellPadding(0);

                $this->SetY(7);
                $this->SetFont('helvetica', 'B', 7);
                $this->Cell(119, 5, '', 0, 0);
                $this->Cell(39, 5, $this->titleText, 0, 0, 'C');

                $this->SetFont('helvetica', '', 7);
                $this->Cell(45, 5, 'Del '.$this->printedAt->format('d/m/Y'), 0, 0, 'R');
                $this->Cell(74, 5, 'Data Stampa '.$this->printedAt->format('d/m/Y H:i'), 0, 1, 'R');

                $this->SetY(15);
                $this->SetFont('helvetica', 'B', 7);

                foreach ($this->borderoColumns as $column) {
                    $this->Cell($column['w'], 5, $column['label'], 'B', 0, $column['align']);
                }
            }
        };
    }

    private function setupPdf(TCPDF $pdf, string $title): void
    {
        $pdf->SetCreator('Stuart Admin');
        $pdf->SetAuthor('Stuart Company');
        $pdf->SetTitle($title);
        $pdf->setPrintHeader(true);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(false, 10);
        $pdf->SetMargins(10, 22, 10);
        $pdf->SetCellPadding(0);
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 7);
    }

    private function drawSender(TCPDF $pdf, array $columns, string $text): void
    {
        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->Cell($columns[0]['w'] + $columns[1]['w'], 3, $text, 0, 1, 'C');
        $pdf->Ln(0.5);
        $pdf->SetFont('helvetica', '', 7);
    }

    private function drawCells(TCPDF $pdf, array $columns, array $cells, float $height, string $fontStyle = ''): void
    {
        $pdf->SetFont('helvetica', $fontStyle, 7);
        $x = $pdf->GetX();
        $y = $pdf->GetY();

        foreach ($cells as $i => $text) {
            $column = $columns[$i];
            $pdf->MultiCell($column['w'], $height, (string) $text, 0, $column['align'], false, 0, '', '', true, 0, false, true, $height, 'T');
        }

        $pdf->SetXY($x, $y + $height);
    }

    private function rowHeight(TCPDF $pdf, array $columns, array $cells): float
    {
        $rowHeight = 8.5;

        foreach ($cells as $i => $text) {
            $rowHeight = max($rowHeight, $pdf->getStringHeight($columns[$i]['w'], (string) $text) + 1);
        }

        return $rowHeight;
    }

    private function drawTotals(TCPDF $pdf, array $columns, Collection $shipments, float $pageBottom): void
    {
        if ($pdf->GetY() + 7 > $pageBottom) {
            $pdf->AddPage();
        }

        $totalCashOnDelivery = (float) $shipments->sum('cash_on_delivery');
        $formatNumber = fn ($value, int $decimals = 0) => number_format((float) $value, $decimals, ',', '.');

        $pdf->Ln(1.5);
        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->Cell($columns[0]['w'] + $columns[1]['w'] + $columns[2]['w'], 4.5, '', 'T', 0);
        $pdf->Cell($columns[3]['w'], 4.5, 'Totali', 'T', 0, 'C');
        $pdf->Cell($columns[4]['w'], 4.5, $totalCashOnDelivery ? number_format($totalCashOnDelivery, 2, ',', '.').' EUR' : '', 'T', 0, 'L');
        $pdf->Cell($columns[5]['w'], 4.5, $formatNumber($shipments->sum('parcels_count')), 'T', 0, 'R');
        $pdf->Cell($columns[6]['w'], 4.5, $formatNumber($shipments->sum('weight_kg'), 1), 'T', 0, 'R');

        if (count($columns) > 8) {
            $pdf->Cell($columns[7]['w'], 4.5, $formatNumber($shipments->sum('volume_m3'), 2), 'T', 0, 'R');
            $pdf->Cell($columns[8]['w'] + $columns[9]['w'], 4.5, 'Spedizioni: '.$shipments->count(), 'T', 1, 'R');
        } else {
            $pdf->Cell($columns[7]['w'], 4.5, 'Spedizioni: '.$shipments->count(), 'T', 1, 'R');
        }
    }

    private function upper(?string $value): string
    {
        return strtoupper(trim((string) $value));
    }

    private function addressBlock($shipment): string
    {
        $province = $this->upper($shipment->recipient_province);
        $cityLine = trim($shipment->recipient_postal_code.' '.$this->upper($shipment->recipient_city));

        if ($province !== '') {
            $cityLine .= ' ('.$province.')';
        }

        return trim($this->upper($shipment->recipient_address_line)."\n".$cityLine);
    }
}
