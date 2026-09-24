<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadQuotePdf;
use App\Models\LeadSalesSheet;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use TCPDF;

class LeadProjectPdfService
{
    private const VAT_RATE = 22;

    public function generate(Lead $lead, LeadQuotePdf $proposal): array
    {
        $lead->loadMissing('salesSheets.items.prints');
        $sheet = $lead->salesSheets->first();
        $contents = $this->render($lead, $proposal, $sheet, $this->mockups($proposal));

        $directory = "leads/{$lead->id}/proposals";
        Storage::disk('local')->makeDirectory($directory);
        $safeNumber = Str::slug($proposal->proposal_number) ?: (string) $proposal->id;
        $filename = "progetto-{$safeNumber}.pdf";
        $path = $directory.'/'.$proposal->id.'-'.$filename;
        Storage::disk('local')->put($path, $contents);

        return [
            'disk' => 'local',
            'path' => $path,
            'filename' => $filename,
            'mime_type' => 'application/pdf',
            'size' => Storage::disk('local')->size($path),
        ];
    }

    public function render(Lead $lead, LeadQuotePdf $proposal, ?LeadSalesSheet $sheet = null, array $mockups = []): string
    {
        $amount = round((float) $proposal->amount, 2);
        $subtotal = round($amount / (1 + self::VAT_RATE / 100), 2);
        $vat = round($amount - $subtotal, 2);

        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->registerFonts();
        $pdf->SetCreator('Stuart Company');
        $pdf->SetAuthor('Stuart Company');
        $pdf->SetTitle('Il tuo progetto - '.$proposal->proposal_number);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(15, 14, 15);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->AddPage();
        $pdf->SetFont('roboto', '', 9);
        $pdf->setCellHeightRatio(1.08);

        $this->drawProject($pdf, $lead, $proposal, $sheet, $mockups, $subtotal, $vat, $amount);

        return $pdf->Output('', 'S');
    }

    private function mockups(LeadQuotePdf $proposal): array
    {
        return collect([
            'Fronte' => $proposal->project_mockup_front_path,
            'Retro' => $proposal->project_mockup_back_path,
        ])->filter(fn (?string $path) => filled($path) && Storage::disk('local')->exists($path))
            ->map(fn (string $path) => Storage::disk('local')->path($path))
            ->all();
    }

    private function itemSizeChart($item): ?string
    {
        $path = $item?->size_chart_path;

        if (filled($path) && file_exists($path)) {
            return $path;
        }

        return filled($path) && Storage::disk('local')->exists($path)
            ? Storage::disk('local')->path($path)
            : null;
    }

    private function registerFonts(): void
    {
        if (! file_exists(K_PATH_FONTS.'roboto.php')) {
            foreach (['Roboto-Regular.ttf', 'Roboto-Bold.ttf', 'Roboto-Italic.ttf', 'Roboto-BoldItalic.ttf'] as $font) {
                $path = resource_path('fonts/'.$font);

                if (file_exists($path)) {
                    \TCPDF_FONTS::addTTFfont($path, 'TrueTypeUnicode', '', 96);
                }
            }
        }

        $futura = resource_path('fonts/Futura-CondensedExtraBold.ttf');
        if (! file_exists(K_PATH_FONTS.'futuracondensedextrab.php') && file_exists($futura)) {
            \TCPDF_FONTS::addTTFfont($futura, 'TrueTypeUnicode', '', 96);
        }
    }

    private function drawProject(TCPDF $pdf, Lead $lead, LeadQuotePdf $proposal, ?LeadSalesSheet $sheet, array $mockups, float $subtotal, float $vat, float $amount): void
    {
        $blue = [32, 106, 233];
        $black = [17, 17, 17];
        $muted = [101, 109, 122];
        $surface = [246, 247, 249];
        $line = [222, 226, 232];
        $money = fn (float $value) => 'EUR '.number_format($value, 2, ',', '.');
        $x = 15.0;
        $width = 180.0;

        $pdf->Image(public_path('assets/logos/logo-stuart.png'), $x, 13, 16, 16, 'PNG');
        $pdf->SetTextColor(...$black);
        $pdf->SetFont('roboto', 'B', 11);
        $pdf->SetXY(34, 15.5);
        $pdf->Cell(70, 5, 'STUART COMPANY');
        $pdf->SetTextColor(...$muted);
        $pdf->SetFont('roboto', '', 7);
        $pdf->SetXY(34, 21);
        $pdf->Cell(70, 4, 'Abbigliamento personalizzato');
        $pdf->SetXY(132, 15.5);
        $pdf->Cell(63, 4, 'PROPOSTA', 0, 0, 'R');
        $pdf->SetTextColor(...$blue);
        $pdf->SetFont('roboto', 'B', 8.5);
        $pdf->SetXY(132, 20);
        $pdf->Cell(63, 5, (string) $proposal->proposal_number, 0, 0, 'R');

        $pdf->SetFillColor(...$black);
        $pdf->RoundedRect($x, 34, $width, 27, 5, '1111', 'F');
        $pdf->SetTextColor(...$blue);
        $pdf->SetFont('roboto', 'B', 7);
        $pdf->SetXY($x + 8, 39);
        $pdf->Cell($width - 16, 4, 'IL TUO PROGETTO PRENDE FORMA', 0, 0, 'C');
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('futuracondensedextrab', '', 19);
        $pdf->SetXY($x + 8, 44);
        $pdf->Cell($width - 16, 8, 'ORA VISUALIZZA IL TUO PROGETTO', 0, 0, 'C');
        $pdf->SetTextColor(193, 198, 206);
        $pdf->SetFont('roboto', '', 7.5);
        $pdf->SetXY($x + 8, 53);
        $pdf->Cell($width - 16, 4, 'Controlla mockup, configurazione e prezzo prima di confermare.', 0, 0, 'C');

        $this->sectionLabel($pdf, 70, '1. MOCKUP DEFINITIVO', $blue);
        $cardGap = 5.0;
        $cardWidth = ($width - $cardGap) / 2;
        foreach (['Fronte', 'Retro'] as $index => $label) {
            $cardX = $x + ($index * ($cardWidth + $cardGap));
            $pdf->SetFillColor(...$surface);
            $pdf->SetDrawColor(...$line);
            $pdf->RoundedRect($cardX, 77, $cardWidth, 56, 5, '1111', 'DF');
            $pdf->SetTextColor(...$blue);
            $pdf->SetFont('roboto', 'B', 7.5);
            $pdf->SetXY($cardX + 5, 82);
            $pdf->Cell($cardWidth - 10, 4, 'VISTA '.strtoupper($label), 0, 0, 'C');
            $pdf->SetTextColor(...$muted);
            $pdf->SetFont('roboto', '', 6.5);
            $pdf->SetXY($cardX + 5, 87);
            $pdf->Cell($cardWidth - 10, 4, 'Mockup definitivo', 0, 0, 'C');
            $path = $mockups[$label] ?? null;
            if ($path && file_exists($path)) {
                $pdf->Image($path, $cardX + 8, 92, $cardWidth - 16, 35, '', '', '', false, 300, '', false, false, 0, 'CM');
            }
        }

        $items = collect($sheet?->items ?? []);
        if ($items->count() > 1) {
            $this->drawMultipleProducts($pdf, $lead, $items, $proposal->project_notes, $subtotal, $vat, $amount, $blue, $black, $muted, $surface, $line, $money);

            return;
        }

        $this->sectionLabel($pdf, 139, '2. RIEPILOGO CONFIGURAZIONE', $blue);

        $item = $items->first();
        $sizeChart = $this->itemSizeChart($item);
        $product = $item?->configuration_name ?: $item?->product_name ?: $lead->product ?: $lead->calculator_model ?: 'Progetto personalizzato';
        $quantity = (float) ($item?->quantity ?: $lead->quantity ?: $lead->calculator_quantity ?: 1);
        $unitPrice = $item ? (float) $item->final_unit_price : ($quantity > 0 ? $subtotal / $quantity : 0);
        $colors = collect($item?->colors)->filter()->join(', ') ?: ($lead->live_mockup_color ?: '-');
        $prints = $item?->prints?->pluck('print_name')->filter()->join(', ') ?: 'Personalizzazione inclusa';

        $pdf->SetFillColor(...$surface);
        $pdf->SetDrawColor(...$line);
        $pdf->RoundedRect($x, 146, $width, 65, 5, '1111', 'DF');
        $pdf->SetTextColor(...$blue);
        $pdf->SetFont('roboto', 'B', 7.5);
        $pdf->SetXY($x + 6, 149);
        $pdf->Cell($width - 12, 4, 'PRODOTTO 1');
        $pdf->SetFillColor(255, 255, 255);
        $pdf->RoundedRect($x + 4, 156, $width - 8, 20, 3, '1111', 'F');
        $pdf->SetTextColor(...$muted);
        $pdf->SetFont('roboto', 'B', 6.5);
        $pdf->SetXY($x + 8, 159);
        $pdf->Cell(82, 4, 'PRODOTTO E PERSONALIZZAZIONE');
        $pdf->SetXY($x + 93, 159);
        $pdf->Cell(20, 4, 'Q.TA', 0, 0, 'C');
        $pdf->SetXY($x + 118, 159);
        $pdf->Cell(27, 4, 'PREZZO/PZ', 0, 0, 'R');
        $pdf->SetXY($x + 150, 159);
        $pdf->Cell(24, 4, 'TOTALE', 0, 0, 'R');
        $pdf->SetTextColor(...$black);
        $pdf->SetFont('roboto', 'B', 8);
        $pdf->SetXY($x + 8, 165);
        $pdf->Cell(82, 4, $product);
        $pdf->SetFont('roboto', '', 7);
        $pdf->SetTextColor(...$muted);
        $pdf->SetXY($x + 8, 170);
        $pdf->MultiCell(82, 7, 'Colore: '.$colors."\n".$prints, 0, 'L', false, 0);
        $pdf->SetTextColor(...$black);
        $pdf->SetXY($x + 93, 165);
        $pdf->Cell(20, 5, number_format($quantity, 0, ',', '.'), 0, 0, 'C');
        $pdf->SetXY($x + 114, 165);
        $pdf->Cell(31, 5, $money($unitPrice).' + IVA', 0, 0, 'R');
        $pdf->SetFont('roboto', 'B', 8);
        $pdf->SetXY($x + 146, 165);
        $pdf->Cell(28, 5, $money((float) ($item?->revenue_total ?: $subtotal)).' + IVA', 0, 0, 'R');

        $pdf->SetTextColor(...$muted);
        $pdf->SetFont('roboto', 'B', 6.2);
        $pdf->SetXY($x + 8, 179);
        $pdf->Cell($width - 16, 4, 'TABELLA TAGLIE');
        $pdf->SetFillColor(255, 255, 255);
        $pdf->RoundedRect($x + 4, 184, $width - 8, 23, 3, '1111', 'F');
        if ($sizeChart && file_exists($sizeChart)) {
            $pdf->Image($sizeChart, $x + 9, 187, $width - 18, 17, '', '', '', false, 300, '', false, false, 0, 'CM');
        } else {
            $pdf->SetTextColor(...$muted);
            $pdf->SetFont('roboto', '', 7);
            $pdf->SetXY($x + 9, 193);
            $pdf->Cell($width - 10, 5, 'Tabella taglie non allegata.', 0, 0, 'C');
        }

        $this->sectionLabel($pdf, 216, '3. PREZZO E CONFERMA', $blue);
        $pdf->SetFillColor(...$black);
        $pdf->RoundedRect($x, 222, $width, 27, 5, '1111', 'F');
        $pdf->SetTextColor(174, 180, 190);
        $pdf->SetFont('roboto', 'B', 6.5);
        $pdf->SetXY($x + 7, 226);
        $pdf->Cell(75, 4, 'PREZZO DEL PROGETTO');
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('futuracondensedextrab', '', 20);
        $pdf->SetXY($x + 7, 231);
        $pdf->Cell(75, 9, $money($amount));
        $pdf->SetFont('roboto', '', 6.5);
        $pdf->SetTextColor(174, 180, 190);
        $pdf->SetXY($x + 7, 241);
        $pdf->Cell(75, 4, 'IVA inclusa');
        $pdf->SetFont('roboto', '', 7.5);
        foreach ([['Imponibile', $subtotal], ['IVA '.self::VAT_RATE.'%', $vat], ['Totale IVA inclusa', $amount]] as $index => [$label, $value]) {
            $rowY = 225 + ($index * 7);
            $pdf->SetTextColor($index === 2 ? 255 : 174, $index === 2 ? 255 : 180, $index === 2 ? 255 : 190);
            $pdf->SetFont('roboto', $index === 2 ? 'B' : '', 7.5);
            $pdf->SetXY($x + 105, $rowY);
            $pdf->Cell(39, 5, $label);
            $pdf->SetTextColor(...($index === 2 ? $blue : [255, 255, 255]));
            $pdf->SetXY($x + 144, $rowY);
            $pdf->Cell(29, 5, $money((float) $value), 0, 0, 'R');
        }

        foreach ([['TEMPI DI CONSEGNA', 'Spedizione entro 6 giorni lavorativi dalla conferma + 24/48 ore lavorative per la consegna.'], ['ASSISTENZA DIRETTA', 'Andrea resta a disposizione prima della conferma.']] as $index => [$title, $body]) {
            $cardX = $x + ($index * ($cardWidth + $cardGap));
            $pdf->SetFillColor(...$surface);
            $pdf->RoundedRect($cardX, 253, $cardWidth, 12, 4, '1111', 'F');
            $pdf->SetTextColor(...$blue);
            $pdf->SetFont('roboto', 'B', 7);
            $pdf->SetXY($cardX + 5, 254.5);
            $pdf->Cell($cardWidth - 10, 4, $title);
            $pdf->SetTextColor(...$muted);
            $pdf->SetFont('roboto', '', 6.5);
            $pdf->SetXY($cardX + 5, 258.5);
            $pdf->MultiCell($cardWidth - 10, 6, $body, 0, 'L', false, 0);
        }

        $pdf->SetFillColor(...$blue);
        $pdf->RoundedRect($x, 268, $width, 10, 5, '1111', 'F');
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('roboto', 'B', 9);
        $pdf->SetXY($x + 5, 270);
        $pdf->Cell($width - 10, 6, "CONFERMA L'ORDINE E PROCEDI AL PAGAMENTO", 0, 0, 'C', false, $lead->payment_link ?: '');

        $this->drawSecondaryActions($pdf, 278.5, $black, $muted, $surface, $line);

        if (filled($proposal->project_notes)) {
            $pdf->AddPage();
            $this->drawNotesSection($pdf, (string) $proposal->project_notes, 18, $blue, $black, $muted, $surface, $line);
        }
    }

    private function drawMultipleProducts(TCPDF $pdf, Lead $lead, $items, ?string $notes, float $subtotal, float $vat, float $amount, array $blue, array $black, array $muted, array $surface, array $line, callable $money): void
    {
        $x = 15.0;
        $width = 180.0;
        $y = 139.0;
        $this->sectionLabel($pdf, $y, '2. RIEPILOGO CONFIGURAZIONE', $blue);
        $y += 7;

        foreach ($items->values() as $index => $item) {
            if ($y + 63 > 282) {
                $pdf->AddPage();
                $y = 18;
                $this->sectionLabel($pdf, $y, '2. RIEPILOGO CONFIGURAZIONE — CONTINUA', $blue);
                $y += 7;
            }

            $product = $item->configuration_name ?: $item->product_name ?: 'Prodotto personalizzato';
            $quantity = (float) ($item->quantity ?: 1);
            $colors = collect($item->colors)->filter()->join(', ') ?: ($lead->live_mockup_color ?: '-');
            $prints = $item->prints?->pluck('print_name')->filter()->join(', ') ?: 'Personalizzazione inclusa';

            $pdf->SetFillColor(...$surface);
            $pdf->SetDrawColor(...$line);
            $pdf->RoundedRect($x, $y, $width, 60, 5, '1111', 'DF');
            $pdf->SetTextColor(...$blue);
            $pdf->SetFont('roboto', 'B', 7.5);
            $pdf->SetXY($x + 6, $y + 3);
            $pdf->Cell($width - 12, 4, 'PRODOTTO '.($index + 1));

            $detailY = $y + 9;
            $pdf->SetFillColor(255, 255, 255);
            $pdf->RoundedRect($x + 4, $detailY, $width - 8, 20, 3, '1111', 'F');
            $pdf->SetTextColor(...$black);
            $pdf->SetFont('roboto', 'B', 8);
            $pdf->SetXY($x + 8, $detailY + 4);
            $pdf->Cell(82, 4, $product);
            $pdf->SetTextColor(...$muted);
            $pdf->SetFont('roboto', '', 6.5);
            $pdf->SetXY($x + 8, $detailY + 10);
            $pdf->MultiCell(82, 6, 'Colore: '.$colors.' · '.$prints, 0, 'L', false, 0);

            foreach ([
                [$x + 93, 20, 'Q.TÀ', number_format($quantity, 0, ',', '.'), 'C'],
                [$x + 116, 29, 'PREZZO/PZ', $money((float) $item->final_unit_price).' + IVA', 'R'],
                [$x + 148, 26, 'TOTALE', $money((float) $item->revenue_total).' + IVA', 'R'],
            ] as [$cellX, $cellWidth, $label, $value, $align]) {
                $pdf->SetTextColor(...$muted);
                $pdf->SetFont('roboto', 'B', 6);
                $pdf->SetXY($cellX, $detailY + 3);
                $pdf->Cell($cellWidth, 4, $label, 0, 0, $align);
                $pdf->SetTextColor(...$black);
                $pdf->SetFont('roboto', $label === 'TOTALE' ? 'B' : '', 7);
                $pdf->SetXY($cellX, $detailY + 9);
                $pdf->Cell($cellWidth, 5, $value, 0, 0, $align);
            }

            $chartY = $y + 32;
            $pdf->SetTextColor(...$muted);
            $pdf->SetFont('roboto', 'B', 6.2);
            $pdf->SetXY($x + 8, $chartY);
            $pdf->Cell($width - 16, 4, 'TABELLA TAGLIE');
            $pdf->SetFillColor(255, 255, 255);
            $pdf->RoundedRect($x + 4, $chartY + 5, $width - 8, 20, 3, '1111', 'F');
            $sizeChart = $this->itemSizeChart($item);
            if ($sizeChart) {
                $pdf->Image($sizeChart, $x + 9, $chartY + 7, $width - 18, 16, '', '', '', false, 300, '', false, false, 0, 'CM');
            } else {
                $pdf->SetTextColor(...$muted);
                $pdf->SetFont('roboto', '', 7);
                $pdf->SetXY($x + 9, $chartY + 11);
                $pdf->Cell($width - 10, 5, 'Tabella taglie non allegata.', 0, 0, 'C');
            }
            $y += 64;
        }

        if ($y + 85 > 292) {
            $pdf->AddPage();
            $y = 18;
        }

        $notesY = $this->drawConfirmation($pdf, $lead, $subtotal, $vat, $amount, $y, $blue, $black, $muted, $surface, $line, $money);
        if (filled($notes)) {
            if ($notesY > 245) {
                $pdf->AddPage();
                $notesY = 18;
            }
            $this->drawNotesSection($pdf, (string) $notes, $notesY, $blue, $black, $muted, $surface, $line);
        }
    }

    private function drawConfirmation(TCPDF $pdf, Lead $lead, float $subtotal, float $vat, float $amount, float $y, array $blue, array $black, array $muted, array $surface, array $line, callable $money): float
    {
        $x = 15.0;
        $width = 180.0;
        $cardGap = 5.0;
        $cardWidth = ($width - $cardGap) / 2;

        $priceY = $y;
        $this->sectionLabel($pdf, $priceY, '3. PREZZO E CONFERMA', $blue);
        $pdf->SetFillColor(...$black);
        $pdf->RoundedRect($x, $priceY + 6, $width, 27, 5, '1111', 'F');
        $pdf->SetTextColor(174, 180, 190);
        $pdf->SetFont('roboto', 'B', 6.5);
        $pdf->SetXY($x + 7, $priceY + 10);
        $pdf->Cell(75, 4, 'TOTALE UNICO DELLA PROPOSTA');
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('futuracondensedextrab', '', 20);
        $pdf->SetXY($x + 7, $priceY + 15);
        $pdf->Cell(75, 9, $money($amount));
        $pdf->SetFont('roboto', '', 6.5);
        $pdf->SetTextColor(174, 180, 190);
        $pdf->SetXY($x + 7, $priceY + 25);
        $pdf->Cell(75, 4, 'IVA inclusa');
        foreach ([['Imponibile', $subtotal], ['IVA '.self::VAT_RATE.'%', $vat], ['Totale IVA inclusa', $amount]] as $index => [$label, $value]) {
            $rowY = $priceY + 9 + ($index * 7);
            $pdf->SetTextColor($index === 2 ? 255 : 174, $index === 2 ? 255 : 180, $index === 2 ? 255 : 190);
            $pdf->SetFont('roboto', $index === 2 ? 'B' : '', 7.5);
            $pdf->SetXY($x + 105, $rowY);
            $pdf->Cell(39, 5, $label);
            $pdf->SetTextColor(...($index === 2 ? $blue : [255, 255, 255]));
            $pdf->SetXY($x + 144, $rowY);
            $pdf->Cell(29, 5, $money((float) $value), 0, 0, 'R');
        }

        $infoY = $priceY + 37;
        foreach ([['TEMPI DI CONSEGNA', 'Spedizione entro 6 giorni lavorativi dalla conferma + 24/48 ore lavorative per la consegna.'], ['ASSISTENZA DIRETTA', 'Andrea resta a disposizione prima della conferma.']] as $index => [$title, $body]) {
            $cardX = $x + ($index * ($cardWidth + $cardGap));
            $pdf->SetFillColor(...$surface);
            $pdf->RoundedRect($cardX, $infoY, $cardWidth, 12, 4, '1111', 'F');
            $pdf->SetTextColor(...$blue);
            $pdf->SetFont('roboto', 'B', 7);
            $pdf->SetXY($cardX + 5, $infoY + 1.5);
            $pdf->Cell($cardWidth - 10, 4, $title);
            $pdf->SetTextColor(...$muted);
            $pdf->SetFont('roboto', '', 6.5);
            $pdf->SetXY($cardX + 5, $infoY + 5.5);
            $pdf->MultiCell($cardWidth - 10, 6, $body, 0, 'L', false, 0);
        }

        $primaryY = $infoY + 15;
        $pdf->SetFillColor(...$blue);
        $pdf->RoundedRect($x, $primaryY, $width, 10, 5, '1111', 'F');
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('roboto', 'B', 9);
        $pdf->SetXY($x + 5, $primaryY + 2);
        $pdf->Cell($width - 10, 6, "CONFERMA L'ORDINE E PROCEDI AL PAGAMENTO", 0, 0, 'C');
        $pdf->Link($x, $primaryY, $width, 10, $lead->payment_link ?: '');

        $secondaryY = $primaryY + 12;

        return $this->drawSecondaryActions($pdf, $secondaryY, $black, $muted, $surface, $line) + 5;
    }

    private function drawSecondaryActions(TCPDF $pdf, float $y, array $black, array $muted, array $surface, array $line): float
    {
        $x = 15.0;
        $width = 180.0;
        $gap = 5.0;
        $buttonX = $x + 3;
        $buttonWidth = ($width - 11) / 2;
        $buttonY = $y + 7;
        $secondaryX = $buttonX + $buttonWidth + $gap;

        $pdf->SetFillColor(...$surface);
        $pdf->SetDrawColor(...$line);
        $pdf->RoundedRect($x, $y, $width, 18, 5, '1111', 'DF');
        $pdf->SetTextColor(...$muted);
        $pdf->SetFont('roboto', 'B', 6.5);
        $pdf->SetXY($x + 5, $y + 1.5);
        $pdf->Cell($width - 10, 4, 'NON SEI ANCORA PRONTO A CONFERMARE?', 0, 0, 'C');

        $pdf->SetFillColor(...$black);
        $pdf->RoundedRect($buttonX, $buttonY, $buttonWidth, 9, 4.5, '1111', 'F');
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('dejavusans', '', 8.5);
        $pdf->SetXY($buttonX + 19, $buttonY + 0.9);
        $pdf->Cell(7, 7, '☎', 0, 0, 'C');
        $pdf->SetFont('roboto', 'B', 7.2);
        $pdf->SetXY($buttonX + 6, $buttonY + 1.5);
        $pdf->Cell($buttonWidth - 8, 6, 'PARLA CON ANDREA', 0, 0, 'C');
        $pdf->Link($buttonX, $buttonY, $buttonWidth, 9, (string) config('project_pdf.andrea_url'));

        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetDrawColor(...$line);
        $pdf->RoundedRect($secondaryX, $buttonY, $buttonWidth, 9, 4.5, '1111', 'DF');
        $pdf->SetTextColor(...$black);
        $this->drawSlidersIcon($pdf, $secondaryX + 17, $buttonY + 2.3, $black);
        $pdf->SetFont('roboto', 'B', 7.2);
        $pdf->SetXY($secondaryX + 7, $buttonY + 1.5);
        $pdf->Cell($buttonWidth - 8, 6, 'MODIFICA IL PROGETTO', 0, 0, 'C');
        $pdf->Link($secondaryX, $buttonY, $buttonWidth, 9, (string) config('project_pdf.configurator_url'));

        return $y + 18;
    }

    private function drawNotesSection(TCPDF $pdf, string $notes, float $y, array $blue, array $black, array $muted, array $surface, array $line): void
    {
        $x = 15.0;
        $width = 180.0;
        $this->sectionLabel($pdf, $y, '4. NOTE GENERALI', $blue);
        $textHeight = max(18.0, $pdf->getStringHeight($width - 14, $notes) + 10);
        $pdf->SetFillColor(...$surface);
        $pdf->SetDrawColor(...$line);
        $pdf->RoundedRect($x, $y + 7, $width, $textHeight, 4, '1111', 'DF');
        $pdf->SetTextColor(...$black);
        $pdf->SetFont('roboto', '', 8);
        $pdf->SetXY($x + 7, $y + 12);
        $pdf->MultiCell($width - 14, $textHeight - 7, $notes, 0, 'L', false, 0);
    }

    private function drawSlidersIcon(TCPDF $pdf, float $x, float $y, array $color): void
    {
        $pdf->SetDrawColor(...$color);
        $pdf->SetFillColor(...$color);
        $pdf->SetLineWidth(0.35);
        foreach ([[0, 1.1], [1.5, 2.2], [0.8, 3.3]] as [$knobX, $lineY]) {
            $pdf->Line($x, $y + $lineY, $x + 4, $y + $lineY);
            $pdf->Circle($x + $knobX + 0.7, $y + $lineY, 0.45, 0, 360, 'F');
        }
    }

    private function sectionLabel(TCPDF $pdf, float $y, string $label, array $blue): void
    {
        $pdf->SetTextColor(...$blue);
        $pdf->SetFont('roboto', 'B', 7);
        $pdf->SetXY(15, $y);
        $pdf->Cell(180, 4, $label);
    }
}
