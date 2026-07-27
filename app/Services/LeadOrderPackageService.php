<?php

namespace App\Services;

use App\Models\LeadOrderDispatch;
use App\Models\LeadSalesSheet;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use TCPDF;
use ZipArchive;

class LeadOrderPackageService
{
    public function create(LeadSalesSheet $sheet, LeadOrderDispatch $dispatch): array
    {
        $sheet->loadMissing(['lead', 'items.prints', 'items.attachments']);
        $directory = 'lead-orders/generated/'.$sheet->lead_id;
        Storage::disk('local')->makeDirectory($directory);
        $path = $directory.'/'.Str::uuid().'.zip';
        $absolutePath = Storage::disk('local')->path($path);
        $zip = new ZipArchive;

        if ($zip->open($absolutePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Impossibile creare l’archivio dell’ordine.');
        }

        try {
            $zip->addFromString('Riepilogo-ordine.pdf', $this->pdf($sheet, $dispatch));

            foreach ($sheet->items as $index => $item) {
                $folder = sprintf(
                    '%02d-%s',
                    $index + 1,
                    Str::slug($item->configuration_name ?: $item->product_name) ?: 'prodotto'
                );
                $zip->addFromString($folder.'/dettagli.txt', $this->itemDetails($item));

                foreach ($item->attachments as $attachment) {
                    if (! Storage::disk($attachment->disk)->exists($attachment->path)) {
                        continue;
                    }

                    $filename = Str::ascii(basename($attachment->filename));
                    $filename = preg_replace('/[^A-Za-z0-9._-]/', '-', $filename) ?: 'grafica';
                    $zip->addFile(
                        Storage::disk($attachment->disk)->path($attachment->path),
                        $folder.'/grafiche/'.$attachment->id.'-'.$filename
                    );
                }
            }
        } finally {
            $zip->close();
        }

        return [
            'disk' => 'local',
            'path' => $path,
            'filename' => $dispatch->filename,
            'mime_type' => 'application/zip',
            'size' => Storage::disk('local')->size($path),
        ];
    }

    private function pdf(LeadSalesSheet $sheet, LeadOrderDispatch $dispatch): string
    {
        $lead = $sheet->lead;
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Stuart Company');
        $pdf->SetAuthor('Stuart Company');
        $pdf->SetTitle('Ordine Lead '.$dispatch->order_name);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(14, 14, 14);
        $pdf->SetAutoPageBreak(true, 14);
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 10);
        $pdf->writeHTML(view('lead-orders.summary-pdf', compact('sheet', 'dispatch', 'lead'))->render());

        return $pdf->Output('', 'S');
    }

    private function itemDetails($item): string
    {
        $prints = $item->prints->map(fn ($print) => $print->print_name.' (€ '.number_format((float) $print->unit_price, 2, ',', '.').'/pz)')->join(', ');

        return implode("\n", [
            'Prodotto: '.($item->configuration_name ?: $item->product_name),
            'Codice: '.$item->product_code,
            'Prodotto catalogo: '.$item->product_name,
            'Quantità: '.number_format((float) $item->quantity, 2, ',', '.'),
            'Colori: '.(collect($item->colors)->join(', ') ?: 'Non indicati'),
            'Lavorazioni: '.($prints ?: 'Nessuna'),
            'Prezzo finale unitario: € '.number_format((float) $item->final_unit_price, 2, ',', '.'),
            'Totale: € '.number_format((float) $item->revenue_total, 2, ',', '.'),
            'Costo totale: € '.number_format((float) $item->cost_total, 2, ',', '.'),
            'Margine: € '.number_format((float) $item->margin_total, 2, ',', '.'),
            '',
            'Note:',
            $item->notes ?: 'Nessuna nota',
        ]);
    }
}
