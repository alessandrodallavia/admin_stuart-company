<?php

namespace App\Services;

use TCPDF;

class TcpdfDocumentoService extends TCPDF
{
    public $header_data;

    public $footer_data;

    public $totale_imponibile = 0;

    public $totale_iva = 0;

    public $totale_finale = 0;

    public $Y_TAB_START = 70 + (7.5 * 5);

    public $ROW_LINE_H = 4;     // deve combaciare con MultiCell(..., 4, ...)

    public $ROW_PAD_T = 1;     // margine sopra testo

    public $ROW_PAD_B = 1;     // margine sotto testo

    public bool $isLastPage = false;

    public bool $isDeliveryNote = false;

    public bool $isQuote = false;

    public function __construct()
    {
        parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);

        $this->registerRobotoFont();

        $this->SetCreator('Laravel');
        $this->SetAuthor('Stuart');
        $this->setPrintHeader(true);
        $this->setPrintFooter(true);

        // Margini come da tuo layout
        $this->SetMargins(7.5, 7.5, 7.5);
        $this->SetAutoPageBreak(false, 0);

        $this->SetFont('roboto', '', 9);

        $this->setCellHeightRatio(1.0);
        $this->setCellPaddings(0, 0, 0, 0);
    }

    private function registerRobotoFont(): void
    {
        if (file_exists(K_PATH_FONTS.'roboto.php')) {
            return;
        }

        foreach (['Roboto-Regular.ttf', 'Roboto-Bold.ttf', 'Roboto-Italic.ttf', 'Roboto-BoldItalic.ttf'] as $font) {
            $path = resource_path('fonts/'.$font);

            if (file_exists($path)) {
                \TCPDF_FONTS::addTTFfont($path, 'TrueTypeUnicode', '', 96);
            }
        }
    }

    public function calcRowHeight(array $colonne, array $valori, int $fontSize = 9): float
    {
        $this->SetFont('roboto', '', $fontSize);

        $maxLines = 1;

        foreach ($colonne as $i => $col) {
            $w = $col[1];
            $text = (string) ($valori[$i] ?? '');

            // larghezza interna: togli padding laterale che usi nel render
            $lines = $this->getNumLines($text, $w - 2);

            if ($lines > $maxLines) {
                $maxLines = $lines;
            }
        }

        return ($maxLines * $this->ROW_LINE_H) + $this->ROW_PAD_T + $this->ROW_PAD_B;
    }

    public function getImponibile()
    {
        return number_format($this->totale_imponibile, 2, ',', '.');
    }

    public function getIva()
    {
        return number_format($this->totale_iva, 2, ',', '.');
    }

    public function getTotale()
    {
        return number_format($this->totale_finale, 2, ',', '.');
    }

    public function setDocumentType(bool $isDeliveryNote, bool $isQuote = false): void
    {
        $this->isDeliveryNote = $isDeliveryNote;
        $this->isQuote = $isQuote;

        if ($isDeliveryNote) {
            $this->Y_TAB_START = 70 + (7.5 * 2);
        } else {
            $this->Y_TAB_START = 70 + (7.5 * 5);
        }
    }

    public function drawTopHeader()
    {
        $this->SetFont('roboto', '', 9);

        $x = 7.5;
        $y = 10;

        $this->SetXY($x, $y);

        $totalWidth = 210 - 15;
        $leftWidth = $totalWidth * 0.60;
        $rightWidth = $totalWidth * 0.40;

        /* --------------------------------------------------------------
        COLONNA SINISTRA – Logo + Dati azienda
        -------------------------------------------------------------- */

        $startLeftY = $y;

        if (! empty($this->header_data['logo'])) {
            $this->Image(
                $this->header_data['logo'],
                $x,
                $startLeftY,
                20,
                0,
                '',
                '',
                'T'
            );

            $logoHeight = 20;
        } else {
            $logoHeight = 0;
        }

        $currentLeftY = $startLeftY + $logoHeight + 10;
        $this->SetXY($x, $currentLeftY);

        $this->SetFont('roboto', '', 8);

        foreach ($this->header_data['company_address'] as $line) {
            $this->MultiCell($leftWidth, 4, $line, 0, 'L');
        }

        $finalLeftY = $this->GetY();

        /* --------------------------------------------------------------
        COLONNA DESTRA – Cliente + Spedizione
        -------------------------------------------------------------- */

        $rightX = $x + $leftWidth - 10;
        $rightY = $y;

        $this->SetXY($rightX, $rightY);

        // --- TITOLO CLIENTE ---
        $this->SetFont('roboto', 'B', 10);
        $this->Cell($rightWidth, 6, 'Spett.le', 0, 1, 'L');

        // --- DATI CLIENTE ---
        $this->SetFont('roboto', '', 9);

        foreach ($this->header_data['billing_address'] as $line) {
            $this->SetX($rightX);
            $this->MultiCell($rightWidth, 0, $line, 0, 'L');
        }

        $this->Ln(3);

        if (! empty($this->header_data['shipping_address'])) {
            // --- SPEDIZIONE (se esiste) ---
            $this->SetX($rightX);
            $this->SetFont('roboto', 'B', 10);
            $this->Cell($rightWidth, 6, 'Destinazione', 0, 1, 'L');

            $this->SetFont('roboto', '', 9);

            foreach ($this->header_data['shipping_address'] as $line) {
                $this->SetX($rightX);
                $this->MultiCell($rightWidth, 0, $line, 0, 'L');
            }
        }

        $finalRightY = $this->GetY();

        /* --------------------------------------------------------------
        ALLINEAMENTO FINALE
        -------------------------------------------------------------- */

        $this->SetY(max($finalLeftY, $finalRightY));
    }

    public function Header()
    {
        $isDeliveryNote = $this->isDeliveryNote;
        $isQuote = $this->isQuote;

        // Blocca Y di partenza
        $this->SetY(70);

        $h = 7.5;
        $this->SetFont('roboto', '', 9);
        $page = $this->getAliasNumPage().'/'.$this->getAliasNbPages();

        // === PRIMA RIGA ===
        $this->cellTitoloValore(45.5, $h, 'Tipo Documento', $this->header_data['tipo_documento']);
        $this->cellTitoloValore(27.5, $h, 'Numero Doc.', $this->header_data['numero_documento']);
        $this->cellTitoloValore(24.5, $h, 'Data Doc.', $this->header_data['data_documento']);
        $this->cellTitoloValore(23.5, $h, 'Cliente', $this->header_data['cliente']);
        $this->cellTitoloValore(41, $h, $this->header_data['tax_label'], $this->header_data['tax_value']);
        $this->cellTitoloValore(19, $h, 'Cod.Dest.', $this->header_data['codice_destinatario']);
        $this->cellTitoloValore(13, $h, 'Pag.', $page);
        $this->Ln($h);

        if ($isDeliveryNote == false) {
            // === SECONDA RIGA ===
            $this->cellTitoloValore(93.5, $h, '', '');
            $this->cellTitoloValore(44.5, $h, '', '');
            $this->cellTitoloValore(56, $h, '', '');
            $this->Ln($h);
        }

        // === TERZA RIGA ===
        if ($isDeliveryNote == false && $isQuote == false) {
            $this->cellTitoloValore(93.5, $h, 'Cod. Agente     Nome Agente', $this->header_data['cod_agente']);
            $this->cellTitoloValore(55.5, $h, 'Cod. IBAN', $this->header_data['cod_iban']);
            $this->cellTitoloValore(45, $h, 'Cod. BIC', $this->header_data['cod_bic']);
            $this->Ln($h);
        } elseif ($isDeliveryNote == false && $isQuote == true) {
            $this->cellTitoloValore(93.5, $h, 'Cod. Agente     Nome Agente', '');
            $this->cellTitoloValore(55.5, $h, 'Cod. IBAN', '');
            $this->cellTitoloValore(45, $h, 'Cod. BIC', '');
            $this->Ln($h);
        }

        // === QUARTA RIGA ===
        if ($isDeliveryNote == false && $isQuote == false) {
            $this->cellTitoloValore(16.5, $h, 'Cod. Pag.', $this->header_data['cod_pag']);
            $this->cellTitoloValore(77, $h, 'Descrizione Pagamento', $this->header_data['descrizione_pagamento']);
            $this->cellTitoloValore(100.5, $h, "Banca d'appoggio - (ABI / CAB)", $this->header_data['banca_appoggio']);
            $this->Ln($h);
        } elseif ($isDeliveryNote == false && $isQuote == true) {
            $this->cellTitoloValore(16.5, $h, 'Cod. Pag.', '');
            $this->cellTitoloValore(77, $h, 'Descrizione Pagamento', '');
            $this->cellTitoloValore(100.5, $h, "Banca d'appoggio - (ABI / CAB)", '');
            $this->Ln($h);
        }

        // === QUINTA RIGA ===
        if ($isDeliveryNote == true) {
            $spaceBetween = null;
            if ($this->header_data['reference_name'] && $this->header_data['reference_phone']) {
                $spaceBetween = ' - ';
            }
            $referenteConsegna = $this->header_data['reference_name'].$spaceBetween.$this->header_data['reference_phone'];
            $this->cellTitoloValore(154, $h, 'Referente consegna', $referenteConsegna);
            $this->cellTitoloValore(40, $h, 'Resa', '');
            $this->Ln($h);
        } else {
            $this->cellTitoloValore(67.5, $h, 'Annotazioni', $this->header_data['annotazioni']);
            $this->cellTitoloValore(126.5, $h, 'Invio Fattura', $this->header_data['invio_fattura']);
            $this->Ln($h);
        }

        // Dopo il blocco, la tabella comincia subito sotto
    }

    public function drawFooterBlocco()
    {
        // Posiziona il footer esattamente dopo la tabella corrente
        // $this->Ln(3); // piccolo spazio sotto tabella, se serve

        $isLastPage = $this->isLastPage;
        $isDeliveryNote = $this->isDeliveryNote;

        $startY = $this->GetY();
        $this->SetLineWidth(0.1);

        /* -----------------------------------------
        RIGA 1 — 7 colonne
        ----------------------------------------- */
        $h = 9.0;

        if ($isDeliveryNote == true) {
            $cols1 = [36.5, 51.5, 106];
            $titles1 = ['Causale trasporto', 'Trasporto a cura', 'Data inizio trasporto'];
            $values1 = [$this->footer_data['causale_trasporto'], $this->footer_data['trasporto_cura'], $this->footer_data['data_inizio_trasporto']]; // default vuoti
        } else {
            $cols1 = [36.5, 21.5, 31, 33.5, 21, 20.5, 30];
            $titles1 = ['Tot. Merci Lordo', '% Sconto', 'Imp. Sconto', 'Tot. Merci Netto', 'Imballo', 'Varie', 'Spese Banca'];
            $values1 = [$this->footer_data['totale_merci_lordo'], '', '', $this->footer_data['totale_merci_netto'], '', '', '']; // default vuoti
        }

        foreach ($cols1 as $i => $w) {
            $this->infoCell($w, $h, $titles1[$i], $isLastPage ? $values1[$i] : '');
        }
        $this->Ln($h);

        /* -----------------------------------------
        RIGA 2/A — 5 colonne
        ----------------------------------------- */
        if ($isDeliveryNote == true) {
            $h = 9.0;
            $cols2 = [92, 34, 34, 34];
            $titles2 = ['Aspetto esteriore dei beni', 'N. Colli', 'Peso Lordo', 'Peso Netto'];
            $values2 = [
                $this->footer_data['aspetto_beni'],
                $this->footer_data['n_colli'],
                $this->footer_data['peso_lordo'] ? 'KG '.number_format($this->footer_data['peso_lordo'], 2, ',', '.') : '',
                $this->footer_data['peso_netto'] ? 'KG '.number_format($this->footer_data['peso_netto'], 2, ',', '.') : '',
            ];

            foreach ($cols2 as $i => $w) {
                $this->infoCell($w, $h, $titles2[$i], $isLastPage ? $values2[$i] : '');
            }
            $this->Ln($h);
        }

        /* -----------------------------------------
        RIGA 2/B — 5 colonne
        ----------------------------------------- */
        if ($isDeliveryNote == false) {

            $aliquote_iva = [];
            $descrizioni_iva = [];
            $imponibili = [];
            $imponibili_iva = [];

            foreach ($this->footer_data['aliquote_descrizioni_imponibili_iva'] as $vat) {
                $aliquote_iva[] = number_format($vat->vat_rate, 0).'%';
                $descrizioni_iva[] = 'IVA '.number_format($vat->vat_rate, 0).'%';
                $imponibili[] = number_format($vat->taxable_amount, 2, ',', '.');
                $imponibili_iva[] = number_format($vat->vat_amount, 2, ',', '.');
            }

            $aliquote_iva_str = implode("\n", $aliquote_iva);
            $descrizioni_iva_str = implode("\n", $descrizioni_iva);
            $imponibili_str = implode("\n", $imponibili);
            $imponibili_iva_str = implode("\n", $imponibili_iva);

        }

        if ($isDeliveryNote == true) {
            $h = 9.0;
            $cols2 = [194];
            $titles2 = ['Vettore'];
            $values2 = [$this->footer_data['carrier']];
        } else {
            $h = 35;
            $cols2 = [107.5, 13.5, 27.5, 23.5, 22];
            $titles2 = ['Scadenze Pagamento', 'Al.IVA', 'Descrizione', 'Imponibile', 'Imp. IVA'];
            $values2 = [
                $this->footer_data['scadenze_pagamento'],
                $aliquote_iva_str,
                $descrizioni_iva_str,
                $imponibili_str,
                $imponibili_iva_str,
            ];
        }

        foreach ($cols2 as $i => $w) {
            $this->infoCell($w, $h, $titles2[$i], $isLastPage ? $values2[$i] : '');
        }
        $this->Ln($h);

        /* -----------------------------------------
        RIGA 3 — 5 colonne
        ----------------------------------------- */
        $h = 9.0;

        if ($isDeliveryNote == true) {
            $cols3 = [64.60, 64.60, 64.8];
            $titles3 = ['Firma vettore', 'Firma conducente', 'Firma destinatario'];
            $values3 = [
                '',
                '',
                '',
            ];
        } else {
            $cols3 = [40, 38.5, 29, 41, 45.5];
            $titles3 = ['Tot. Imponibile', 'Totale IVA', 'Arrotondamento', 'Totale Fattura', 'Totale a pagare'];
            $values3 = [
                'EUR '.$this->footer_data['totale_imponibile'],
                'EUR '.$this->footer_data['totale_iva'],
                $this->footer_data['arrotondamento'] !== '' ? 'EUR '.$this->footer_data['arrotondamento'] : '',
                'EUR '.$this->footer_data['totale_fattura'],
                'EUR '.$this->footer_data['totale_fattura'],
            ];
        }

        foreach ($cols3 as $i => $w) {
            $this->infoCell($w, $h, $titles3[$i], $isLastPage ? $values3[$i] : '');
        }
    }

    public function infoCell(float $w, float $h, string $title, string $value = ''): void
    {
        $x = $this->GetX();
        $y = $this->GetY();

        // Bordo cella
        $this->Rect($x, $y, $w, $h);

        // Titolo piccolo
        $this->SetFont('roboto', '', 7);
        $this->SetXY($x, $y + 1);
        $this->Cell($w - 2, 3, $title, 0, 0, 'L');

        // Valore grande
        $this->SetFont('roboto', '', 10);
        $this->SetXY($x + 1, $y + 3.5);
        $this->MultiCell($w - 2, 4, $value, 0, 'L', false);

        // Sposta cursore a destra
        $this->SetXY($x + $w, $y);
    }

    /* ============================================================
       Celle titolo/valore (blocco informazioni)
       ============================================================ */
    public function cellTitoloValore(float $w, float $h, string $titolo, string $valore): void
    {
        $x = $this->GetX();
        $y = $this->GetY();

        $this->SetLineWidth(0.1);

        // Bordo cella
        $this->Rect($x, $y, $w, $h);

        // Titolo (piccolo)
        $this->SetFont('roboto', '', 7);
        $this->SetXY($x + 1, $y + 1);
        $this->Cell($w - 2, 3, $titolo, 0, 0, 'L');

        // Valore (grande)
        $this->SetFont('roboto', '', 10);
        $this->SetXY($x + 1, $y + 3.5);
        $this->Cell($w - 2, 4, $valore, 0, 0, 'L');

        // Sposta il cursore a destra della cella
        $this->SetXY($x + $w, $y);
    }

    /* ============================================================
       Header tabella prodotti
       ============================================================ */
    public function drawHeader(array $colonne): void
    {
        $h = 6.5;
        $this->SetFont('roboto', 'B', 8);
        $this->SetLineWidth(0.1);

        foreach ($colonne as $i => $col) {

            $border = 'B'; // sempre bordo sotto

            if ($i === 0) {
                $border .= 'L'; // solo prima colonna ha sinistro
            }

            $border .= 'R'; // tutte hanno destro

            $this->Cell($col[1], $h, $col[0], $border, 0, 'C');
        }

        $this->Ln($h);
    }

    /* ============================================================
       Calcolo altezza testo per una cella
       (stesse condizioni di disegno: larghezza interna -2, line-height 4)
       ============================================================ */
    public function getTextHeight(float $w, string $text, int $fontSize = 9): float
    {
        $this->SetFont('roboto', '', $fontSize);

        // Altezza per riga = 4 (come usi nella tabella)
        $lineHeight = 4;

        // TCPDF calcola l'altezza senza disegnare nulla
        $height = $this->getStringHeight(
            $w - 2,     // larghezza interna
            $text,
            false,      // reseth
            true,       // autopadding
            '',         // cellpadding
            0           // border
        );

        // margine extra se vuoi
        return $height + 1;
    }

    /* ============================================================
       Riga prodotti — altezza dinamica, SOLO righe verticali
       ============================================================ */
    public function drawProductRow(array $colonne, array $valori, float $rowHeight): void
    {
        $x_start = $this->GetX();
        $y_start = $this->GetY();

        $this->SetFont('roboto', '', 9);

        $xCursor = $x_start;

        foreach ($colonne as $i => $col) {

            $w = $col[1];
            $text = (string) ($valori[$i] ?? '');

            $this->SetXY($xCursor, $y_start + $this->ROW_PAD_T);

            // ln = 0 → non va a capo a fine cella
            $this->MultiCell(
                $w,
                $this->ROW_LINE_H,
                $text,
                0,
                'L',
                false,
                0
            );

            $xCursor += $w;
        }

        // Bordi verticali
        $xCursor = $x_start;
        $numColonne = count($colonne);

        for ($i = 0; $i < $numColonne; $i++) {
            $w = $colonne[$i][1];

            $this->Line($xCursor, $y_start, $xCursor, $y_start + $rowHeight);

            if ($i === $numColonne - 1) {
                $this->Line($xCursor + $w, $y_start, $xCursor + $w, $y_start + $rowHeight);
            }

            $xCursor += $w;
        }

        $this->SetXY($x_start, $y_start + $rowHeight);
    }

    /* ============================================================
       Riga vuota — solo righe verticali, nessun testo
       ============================================================ */
    public function drawEmptyRow(array $colonne, float $rowHeight): void
    {
        $x_start = $this->GetX();
        $y_start = $this->GetY();

        $numColonne = count($colonne);

        foreach ($colonne as $i => $col) {
            $wCol = $col[1];
            $xCol = $this->GetX();

            // Bordo verticale sinistro
            $this->Line($xCol, $y_start, $xCol, $y_start + $rowHeight);

            // Bordo verticale destro SOLO sull’ultima colonna
            if ($i === $numColonne - 1) {
                $this->Line($xCol + $wCol, $y_start, $xCol + $wCol, $y_start + $rowHeight);
            }

            // Nessun testo, solo avanzamento X
            $this->SetXY($xCol + $wCol, $y_start);
        }

        // Riga successiva
        $this->SetXY($x_start, $y_start + $rowHeight);
    }
}
