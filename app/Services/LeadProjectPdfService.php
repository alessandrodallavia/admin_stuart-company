<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadQuotePdf;
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
        $amount = round((float) $proposal->amount, 2);
        $subtotal = round($amount / (1 + self::VAT_RATE / 100), 2);
        $vat = round($amount - $subtotal, 2);

        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Stuart Company');
        $pdf->SetAuthor('Stuart Company');
        $pdf->SetTitle('Il tuo progetto - '.$proposal->proposal_number);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(15, 14, 15);
        $pdf->SetAutoPageBreak(true, 2);
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 10);

        $pdf->writeHTML(view('lead-projects.pdf', [
            'lead' => $lead,
            'proposal' => $proposal,
            'sheet' => $sheet,
            'subtotal' => $subtotal,
            'vat' => $vat,
            'vatRate' => self::VAT_RATE,
            'amount' => $amount,
            'logoPath' => public_path('assets/logos/logo-stuart.png'),
            'mockups' => $this->mockups($proposal),
        ])->render(), true, false, true, false, '');

        $directory = "leads/{$lead->id}/proposals";
        Storage::disk('local')->makeDirectory($directory);
        $safeNumber = Str::slug($proposal->proposal_number) ?: (string) $proposal->id;
        $filename = "progetto-{$safeNumber}.pdf";
        $path = $directory.'/'.$proposal->id.'-'.$filename;
        Storage::disk('local')->put($path, $pdf->Output('', 'S'));

        return [
            'disk' => 'local',
            'path' => $path,
            'filename' => $filename,
            'mime_type' => 'application/pdf',
            'size' => Storage::disk('local')->size($path),
        ];
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
}
