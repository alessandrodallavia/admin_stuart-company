<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminDocument;
use App\Models\DocumentRelation;
use App\Models\DocumentsPaymentMethod;
use App\Services\AdminDocumentPdfService;
use App\Services\AdminDocumentService;
use App\Services\AdminDocumentXmlService;
use App\Services\DocumentGeneratorService;
use App\Services\DocumentRelationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class DocumentController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.documents.index');
    }

    public function payments(Request $request): View
    {
        return view('admin.documents.payments', [
            'currentStatus' => $request->string('status')->toString(),
        ]);
    }

    public function importXml(): View
    {
        return view('admin.documents.import-xml');
    }

    public function create(Request $request): View
    {
        $type = $request->string('type')->toString() ?: 'quote';

        return view('admin.documents.create', [
            'document' => new AdminDocument([
                'type' => array_key_exists($type, AdminDocument::TYPES) ? $type : 'quote',
                'fiscal_type' => $type === 'invoice' ? 'TD01' : null,
                'document_date' => now(),
                'status' => 'draft',
                'payment_conditions' => 'TP02',
                'currency' => 'EUR',
                'customer_country' => 'IT',
            ]),
            'types' => AdminDocument::TYPES,
            'statuses' => AdminDocument::statusesFor($type),
            'paymentMethods' => DocumentsPaymentMethod::query()->where('is_active', true)->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request, AdminDocumentService $service): RedirectResponse
    {
        $document = $service->create($this->validatedData($request));

        return redirect()
            ->route('admin.documents.show', $document)
            ->with('status', 'Documento creato.');
    }

    public function show(AdminDocument $document): View
    {
        $document->load(['items', 'paymentSchedules.paymentMethod', 'paymentMethod', 'sourceDocument', 'generatedDocuments', 'shipments.parcels']);

        return view('admin.documents.show', [
            'document' => $document,
            'types' => AdminDocument::TYPES,
            'statuses' => AdminDocument::statusesFor($document->type),
            'directRelations' => $this->directRelations($document),
        ]);
    }

    public function preview(AdminDocument $document, AdminDocumentPdfService $pdfService)
    {
        $pdf = $pdfService->output($document);

        return response($pdf)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="'.$pdfService->filename($document).'"');
    }

    public function exportXml(AdminDocument $document, AdminDocumentXmlService $xmlService)
    {
        abort_unless($document->type === 'invoice', 404);

        $xml = $xmlService->output($document);
        $this->markInvoicesAsSent(collect([$document]));

        return response($xml)
            ->header('Content-Type', 'application/xml')
            ->header('Content-Disposition', 'attachment; filename="'.$xmlService->filename($document).'"');
    }

    public function exportSdi(Request $request, AdminDocumentXmlService $xmlService): RedirectResponse|BinaryFileResponse
    {
        $documentIds = collect($request->input('document_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($documentIds->isEmpty()) {
            return back()->with('status', 'Seleziona almeno una fattura da esportare per Aruba.');
        }

        $documents = AdminDocument::query()
            ->where('type', 'invoice')
            ->whereIn('id', $documentIds)
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->orderBy('document_date')
            ->orderBy('number')
            ->get();

        if ($documents->isEmpty()) {
            return back()->with('status', 'Nessuna fattura valida tra quelle selezionate. Bozze e annullate non vengono esportate.');
        }

        $zipPath = storage_path('app/private/export-fatture-sdi-'.now()->format('Ymd-His').'.zip');
        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return back()->with('status', 'Export SDI non riuscito. Riprova tra poco.');
        }

        foreach ($documents as $document) {
            $zip->addFromString($xmlService->filename($document), $xmlService->output($document));
        }

        $zip->close();
        $this->markInvoicesAsSent($documents);

        return response()
            ->download($zipPath, 'fatture-sdi-aruba-'.now()->format('Ymd-His').'.zip', ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend();
    }

    public function edit(AdminDocument $document): View
    {
        $document->load(['items', 'paymentSchedules.paymentMethod']);

        return view('admin.documents.edit', [
            'document' => $document,
            'types' => AdminDocument::TYPES,
            'statuses' => AdminDocument::statusesFor($document->type),
            'paymentMethods' => DocumentsPaymentMethod::query()->where('is_active', true)->orderBy('code')->get(),
        ]);
    }

    public function update(Request $request, AdminDocument $document, AdminDocumentService $service): RedirectResponse
    {
        $document = $service->update($document, $this->validatedData($request));

        return redirect()
            ->route('admin.documents.show', $document)
            ->with('status', 'Documento aggiornato.');
    }

    public function destroy(AdminDocument $document): RedirectResponse
    {
        $document->delete();

        return redirect()
            ->route('admin.documents.index')
            ->with('status', 'Documento eliminato.');
    }

    public function duplicate(Request $request, AdminDocument $document, AdminDocumentService $service, DocumentGeneratorService $generator): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(AdminDocument::TYPES))],
        ]);

        $newDocument = match ([$document->type, $data['type']]) {
            ['quote', 'proforma'] => $generator->fromQuoteToProforma($document->id),
            ['quote', 'offline_order'] => $generator->fromQuoteToOrder($document->id),
            ['quote', 'invoice'] => $generator->fromQuoteToInvoice($document->id),
            ['proforma', 'offline_order'] => $generator->fromProformaToOrder($document->id),
            ['proforma', 'invoice'] => $generator->fromProformaToInvoice($document->id),
            ['offline_order', 'delivery_note'] => $generator->fromOrderToDeliveryNote($document->id),
            ['offline_order', 'invoice'] => $generator->fromOrderToInvoice($document->id),
            ['delivery_note', 'invoice'] => $generator->fromDeliveryNoteToInvoice($document->id),
            default => $service->duplicateAs($document, $data['type']),
        };

        return redirect()
            ->route('admin.documents.edit', $newDocument)
            ->with('status', $data['type'] === $document->type ? 'Documento copiato. Puoi modificarlo prima di emetterlo.' : 'Documento generato. Controlla i dati prima di emetterlo.');
    }

    public function destroyRelation(AdminDocument $document, DocumentRelation $relation): RedirectResponse
    {
        abort_unless($this->relationBelongsToDocument($document, $relation), 404);

        $this->clearSourceDocumentLink($relation);
        $relation->delete();

        return back()->with('status', 'Collegamento eliminato.');
    }

    public function destroySourceLink(AdminDocument $document, AdminDocument $linkedDocument): RedirectResponse
    {
        abort_unless(
            (int) $document->source_document_id === (int) $linkedDocument->id
                || (int) $linkedDocument->source_document_id === (int) $document->id,
            404
        );

        if ((int) $document->source_document_id === (int) $linkedDocument->id) {
            $document->forceFill(['source_document_id' => null])->save();
        }

        if ((int) $linkedDocument->source_document_id === (int) $document->id) {
            $linkedDocument->forceFill(['source_document_id' => null])->save();
        }

        return back()->with('status', 'Collegamento eliminato.');
    }

    public function updatePayment(Request $request, AdminDocument $document, AdminDocumentService $service): RedirectResponse
    {
        $data = $request->validate([
            'schedule_id' => ['required', 'integer', Rule::exists('admin_document_payment_schedules', 'id')],
            'paid_amount' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'paid_at' => ['nullable', 'date'],
        ]);

        $service->markPayment($document, (int) $data['schedule_id'], (float) $data['paid_amount'], $data['paid_at'] ?? null);

        return back()->with('status', 'Pagamento aggiornato.');
    }

    private function validatedData(Request $request): array
    {
        $validator = validator($request->all(), [
            'type' => ['required', Rule::in(array_keys(AdminDocument::TYPES))],
            'fiscal_type' => ['nullable', Rule::in(array_keys(config('documents.invoice_fiscal_types')))],
            'document_date' => ['required', 'date'],
            'status' => ['required', Rule::in(array_keys(AdminDocument::statusesFor($request->input('type'))))],
            'currency' => ['required', 'string', 'size:3'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email:rfc', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:40'],
            'customer_tax_code' => ['nullable', 'string', 'max:40'],
            'customer_vat_number' => ['nullable', 'string', 'max:40'],
            'customer_recipient_code' => ['nullable', 'string', 'max:7'],
            'customer_pec' => ['nullable', 'email:rfc', 'max:255'],
            'customer_address' => ['nullable', 'string', 'max:255'],
            'customer_street_number' => ['nullable', 'string', 'max:30'],
            'customer_city' => ['nullable', 'string', 'max:120'],
            'customer_province' => ['nullable', 'string', 'max:10'],
            'customer_postal_code' => ['nullable', 'string', 'max:20'],
            'customer_country' => ['required', 'string', 'size:2'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'payment_conditions' => ['required', Rule::in(['TP00', 'TP01', 'TP02'])],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_code' => ['nullable', 'string', 'max:80'],
            'items.*.description' => ['nullable', 'string', 'max:5000'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:-99999999.99', 'max:99999999.99'],
            'items.*.vat_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'payments' => ['nullable', 'array'],
            'payments.*.due_date' => ['nullable', 'date'],
            'payments.*.method' => ['nullable', 'string', 'max:60'],
            'payments.*.payment_method_code' => ['nullable', Rule::exists('documents_payment_methods', 'code')],
            'payments.*.amount' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'payments.*.paid_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'payments.*.paid_at' => ['nullable', 'date'],
            'payments.*.notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $validator->after(function (Validator $validator) use ($request) {
            $taxCode = Str::upper(preg_replace('/\s+/', '', (string) $request->input('customer_tax_code')));
            $vatNumber = preg_replace('/\D+/', '', (string) $request->input('customer_vat_number'));
            $recipientCode = Str::upper(trim((string) $request->input('customer_recipient_code')));

            if ($taxCode !== '' && ! $this->isValidItalianTaxCode($taxCode) && ! $this->isValidItalianVatNumber($taxCode)) {
                $validator->errors()->add('customer_tax_code', 'Codice fiscale non valido.');
            }

            if ($vatNumber !== '' && ! $this->isValidItalianVatNumber($vatNumber)) {
                $validator->errors()->add('customer_vat_number', 'Partita IVA non valida.');
            }

            if ($recipientCode !== '' && ! preg_match('/^[A-Z0-9]{6,7}$/', $recipientCode)) {
                $validator->errors()->add('customer_recipient_code', 'Codice destinatario non valido.');
            }
        });

        $data = $validator->validate();

        $data['fiscal_type'] = ($data['type'] ?? null) === 'invoice' ? ($data['fiscal_type'] ?: 'TD01') : null;

        $data['customer_tax_code'] = Str::upper(preg_replace('/\s+/', '', (string) ($data['customer_tax_code'] ?? ''))) ?: null;
        $data['customer_vat_number'] = preg_replace('/\D+/', '', (string) ($data['customer_vat_number'] ?? '')) ?: null;
        $data['customer_recipient_code'] = Str::upper(trim((string) ($data['customer_recipient_code'] ?? ''))) ?: null;
        $data['customer_province'] = Str::upper(trim((string) ($data['customer_province'] ?? ''))) ?: null;
        $data['customer_country'] = Str::upper($data['customer_country']);

        return $data;
    }

    private function directRelations(AdminDocument $document)
    {
        return $document->relations()
            ->get()
            ->map(function (DocumentRelation $relation) use ($document) {
                $documentType = $document->currentDocumentType();
                $relatedType = $relation->from_type === $documentType && (int) $relation->from_id === (int) $document->id
                    ? $relation->to_type
                    : $relation->from_type;
                $relatedId = $relation->from_type === $documentType && (int) $relation->from_id === (int) $document->id
                    ? $relation->to_id
                    : $relation->from_id;

                $relatedDocument = AdminDocument::query()
                    ->where('type', DocumentRelationService::adminType($relatedType))
                    ->find((int) $relatedId);

                if (! $relatedDocument) {
                    return null;
                }

                return [
                    'relation' => $relation,
                    'document' => $relatedDocument,
                    'key' => $relatedType->value.'-'.$relatedDocument->id,
                ];
            })
            ->filter()
            ->values();
    }

    private function relationBelongsToDocument(AdminDocument $document, DocumentRelation $relation): bool
    {
        $type = $document->currentDocumentType();

        return ($relation->from_type === $type && (int) $relation->from_id === (int) $document->id)
            || ($relation->to_type === $type && (int) $relation->to_id === (int) $document->id);
    }

    private function markInvoicesAsSent($documents): void
    {
        AdminDocument::query()
            ->whereIn('id', $documents->pluck('id'))
            ->where('type', 'invoice')
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->update(['status' => 'sent']);
    }

    private function clearSourceDocumentLink(DocumentRelation $relation): void
    {
        $fromDocument = AdminDocument::query()
            ->where('type', DocumentRelationService::adminType($relation->from_type))
            ->find((int) $relation->from_id);
        $toDocument = AdminDocument::query()
            ->where('type', DocumentRelationService::adminType($relation->to_type))
            ->find((int) $relation->to_id);

        if ($fromDocument && $toDocument && (int) $toDocument->source_document_id === (int) $fromDocument->id) {
            $toDocument->forceFill(['source_document_id' => null])->save();
        }

        if ($fromDocument && $toDocument && (int) $fromDocument->source_document_id === (int) $toDocument->id) {
            $fromDocument->forceFill(['source_document_id' => null])->save();
        }
    }

    private function isValidItalianTaxCode(string $taxCode): bool
    {
        if (! preg_match('/^[A-Z0-9]{16}$/', $taxCode)) {
            return false;
        }

        $oddValues = [
            '0' => 1, '1' => 0, '2' => 5, '3' => 7, '4' => 9, '5' => 13, '6' => 15, '7' => 17, '8' => 19, '9' => 21,
            'A' => 1, 'B' => 0, 'C' => 5, 'D' => 7, 'E' => 9, 'F' => 13, 'G' => 15, 'H' => 17, 'I' => 19, 'J' => 21,
            'K' => 2, 'L' => 4, 'M' => 18, 'N' => 20, 'O' => 11, 'P' => 3, 'Q' => 6, 'R' => 8, 'S' => 12, 'T' => 14,
            'U' => 16, 'V' => 10, 'W' => 22, 'X' => 25, 'Y' => 24, 'Z' => 23,
        ];
        $evenValues = [
            '0' => 0, '1' => 1, '2' => 2, '3' => 3, '4' => 4, '5' => 5, '6' => 6, '7' => 7, '8' => 8, '9' => 9,
            'A' => 0, 'B' => 1, 'C' => 2, 'D' => 3, 'E' => 4, 'F' => 5, 'G' => 6, 'H' => 7, 'I' => 8, 'J' => 9,
            'K' => 10, 'L' => 11, 'M' => 12, 'N' => 13, 'O' => 14, 'P' => 15, 'Q' => 16, 'R' => 17, 'S' => 18,
            'T' => 19, 'U' => 20, 'V' => 21, 'W' => 22, 'X' => 23, 'Y' => 24, 'Z' => 25,
        ];

        $sum = 0;
        for ($i = 0; $i < 15; $i++) {
            $sum += ($i % 2 === 0) ? $oddValues[$taxCode[$i]] : $evenValues[$taxCode[$i]];
        }

        return chr(($sum % 26) + ord('A')) === $taxCode[15];
    }

    private function isValidItalianVatNumber(string $vatNumber): bool
    {
        if (! preg_match('/^[0-9]{11}$/', $vatNumber)) {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < 10; $i++) {
            $digit = (int) $vatNumber[$i];
            if ($i % 2 === 1) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }
            $sum += $digit;
        }

        return ((10 - ($sum % 10)) % 10) === (int) $vatNumber[10];
    }
}
