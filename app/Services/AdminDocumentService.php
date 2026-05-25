<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Models\AdminDocument;
use App\Models\AdminDocumentPaymentSchedule;
use App\Models\DocumentRelation;
use App\Models\DocumentsPaymentMethod;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdminDocumentService
{
    public function create(array $data): AdminDocument
    {
        return DB::transaction(function () use ($data) {
            $document = AdminDocument::create($this->documentAttributes($data));
            $this->syncItems($document, $data['items'] ?? []);
            $this->syncPayments($document, $data['payments'] ?? []);
            $document->refreshTotals();

            return $document->fresh(['items', 'paymentSchedules']);
        });
    }

    public function update(AdminDocument $document, array $data): AdminDocument
    {
        return DB::transaction(function () use ($document, $data) {
            $document->update($this->documentAttributes($data, $document));
            $this->syncItems($document, $data['items'] ?? []);
            $this->syncPayments($document, $data['payments'] ?? []);
            $document->refreshTotals();

            return $document->fresh(['items', 'paymentSchedules']);
        });
    }

    public function duplicateAs(AdminDocument $source, string $type, bool $preservePaymentProgress = false): AdminDocument
    {
        return DB::transaction(function () use ($source, $type, $preservePaymentProgress) {
            $source->loadMissing(['items', 'paymentSchedules']);
            $document = AdminDocument::create([
                ...Arr::only($source->getAttributes(), [
                    'customer_name',
                    'customer_email',
                    'customer_phone',
                    'customer_tax_code',
                    'customer_vat_number',
                    'customer_recipient_code',
                    'customer_pec',
                    'customer_address',
                    'customer_street_number',
                    'customer_city',
                    'customer_province',
                    'customer_postal_code',
                    'customer_country',
                    'currency',
                    'notes',
                    'subtotal',
                    'vat_total',
                    'total',
                    'payment_conditions',
                    'payment_method',
                    'bank_name',
                    'bank_iban',
                    'bank_bic',
                    'fiscal_type',
                ]),
                'type' => $type,
                'number' => null,
                'year' => now()->year,
                'code' => null,
                'document_date' => now()->toDateString(),
                'status' => 'draft',
                'payment_status' => $preservePaymentProgress ? $source->payment_status : 'unpaid',
                'source_document_id' => $source->id,
            ]);

            foreach ($source->items as $item) {
                $document->items()->create(Arr::except($item->toArray(), ['id', 'admin_document_id', 'created_at', 'updated_at']));
            }

            foreach ($source->paymentSchedules as $payment) {
                $document->paymentSchedules()->create([
                    ...Arr::except($payment->toArray(), ['id', 'admin_document_id', 'created_at', 'updated_at', 'paid_amount', 'paid_at', 'status']),
                    'paid_amount' => $preservePaymentProgress ? $payment->paid_amount : 0,
                    'paid_at' => $preservePaymentProgress ? $payment->paid_at : null,
                    'status' => $preservePaymentProgress ? $payment->status : 'unpaid',
                ]);
            }

            $document->refreshTotals();

            return $document->fresh(['items', 'paymentSchedules']);
        });
    }

    public function markPayment(AdminDocument $document, int $scheduleId, float $paidAmount, ?string $paidAt): void
    {
        DB::transaction(function () use ($document, $scheduleId, $paidAmount, $paidAt) {
            $schedule = $document->paymentSchedules()->orderBy('due_date')->orderBy('id')->findOrFail($scheduleId);
            $scheduleIndex = $this->scheduleIndex($document, $schedule);
            $this->applyPaymentToSchedule($schedule, $paidAmount, $paidAt);

            $document->refreshPaymentStatus();
            $this->syncRelatedPaymentSchedules($document, $schedule, $scheduleIndex, $paidAmount, $paidAt);
        });
    }

    private function applyPaymentToSchedule(AdminDocumentPaymentSchedule $schedule, float $paidAmount, ?string $paidAt): void
    {
        $schedule->update([
            'paid_amount' => $paidAmount,
            'paid_at' => $paidAmount > 0 ? ($paidAt ?: now()->toDateString()) : null,
            'status' => $paidAmount >= (float) $schedule->amount ? 'paid' : ($paidAmount > 0 ? 'partial' : 'unpaid'),
        ]);
    }

    private function scheduleIndex(AdminDocument $document, AdminDocumentPaymentSchedule $schedule): int
    {
        $index = $document->paymentSchedules()
            ->orderBy('due_date')
            ->orderBy('id')
            ->pluck('id')
            ->search($schedule->id);

        return $index === false ? 0 : (int) $index;
    }

    private function syncRelatedPaymentSchedules(
        AdminDocument $document,
        AdminDocumentPaymentSchedule $schedule,
        int $scheduleIndex,
        float $paidAmount,
        ?string $paidAt
    ): void {
        foreach ($this->relatedPaymentDocuments($document) as $relatedDocument) {
            $targetSchedule = $this->matchingSchedule($relatedDocument, $schedule, $scheduleIndex);

            if (! $targetSchedule) {
                continue;
            }

            $this->applyPaymentToSchedule($targetSchedule, $paidAmount, $paidAt);
            $relatedDocument->refreshPaymentStatus();
        }
    }

    private function matchingSchedule(
        AdminDocument $document,
        AdminDocumentPaymentSchedule $sourceSchedule,
        int $scheduleIndex
    ): ?AdminDocumentPaymentSchedule {
        $schedules = $document->paymentSchedules()
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();

        return $schedules->first(function (AdminDocumentPaymentSchedule $schedule) use ($sourceSchedule) {
            return optional($schedule->due_date)->isSameDay($sourceSchedule->due_date)
                && abs((float) $schedule->amount - (float) $sourceSchedule->amount) < 0.01
                && ($schedule->payment_method_code ?: null) === ($sourceSchedule->payment_method_code ?: null);
        }) ?: $schedules->get($scheduleIndex);
    }

    private function relatedPaymentDocuments(AdminDocument $document): Collection
    {
        $startType = $document->currentDocumentType()->value;
        $startKey = $startType.'-'.$document->id;
        $visited = collect();
        $queue = collect([['type' => $startType, 'id' => $document->id]]);
        $documents = collect();

        while ($queue->isNotEmpty()) {
            $current = $queue->shift();
            $key = $current['type'].'-'.$current['id'];

            if ($visited->contains($key)) {
                continue;
            }

            $visited->push($key);

            if ($key !== $startKey) {
                $documentType = DocumentType::from($current['type']);
                $relatedDocument = DocumentRelationService::resolveModel($documentType)::query()
                    ->where('type', DocumentRelationService::adminType($documentType))
                    ->find((int) $current['id']);

                if ($relatedDocument) {
                    $documents->push($relatedDocument);
                }
            }

            $relations = DocumentRelation::where(function ($query) use ($current) {
                $query->where('from_type', $current['type'])
                    ->where('from_id', $current['id']);
            })->orWhere(function ($query) use ($current) {
                $query->where('to_type', $current['type'])
                    ->where('to_id', $current['id']);
            })->get();

            foreach ($relations as $relation) {
                foreach ([['type' => $relation->from_type->value, 'id' => $relation->from_id], ['type' => $relation->to_type->value, 'id' => $relation->to_id]] as $node) {
                    $nodeKey = $node['type'].'-'.$node['id'];

                    if (! $visited->contains($nodeKey)) {
                        $queue->push($node);
                    }
                }
            }
        }

        return $documents;
    }

    private function documentAttributes(array $data, ?AdminDocument $document = null): array
    {
        $year = (int) date('Y', strtotime($data['document_date']));
        $number = $document?->number ?: $this->nextNumber($data['type'], $year);
        $paymentSnapshot = $this->paymentSnapshot($data);

        return Arr::only($data, [
            'type',
            'fiscal_type',
            'document_date',
            'status',
            'currency',
            'customer_name',
            'customer_email',
            'customer_phone',
            'customer_tax_code',
            'customer_vat_number',
            'customer_recipient_code',
            'customer_pec',
            'customer_address',
            'customer_street_number',
            'customer_city',
            'customer_province',
            'customer_postal_code',
            'customer_country',
            'notes',
            'payment_conditions',
        ]) + [
            'year' => $year,
            'number' => $data['status'] === 'draft' ? null : $number,
            'code' => $data['status'] === 'draft' ? null : ($document?->code ?: $this->buildCode($data['type'], $year, $number)),
        ] + $paymentSnapshot;
    }

    private function syncItems(AdminDocument $document, array $items): void
    {
        $document->items()->delete();

        foreach (array_values($items) as $index => $item) {
            if (blank($item['description'] ?? null)) {
                continue;
            }

            $quantity = blank($item['quantity'] ?? null) ? 0 : (float) $item['quantity'];
            $unitPrice = (float) ($item['unit_price'] ?? 0);
            $vatRate = (float) ($item['vat_rate'] ?? 22);
            $lineSubtotal = round($quantity * $unitPrice, 2);
            $lineVat = round($lineSubtotal * $vatRate / 100, 2);

            $document->items()->create([
                'position' => $index + 1,
                'item_code' => blank($item['item_code'] ?? null) ? null : trim((string) $item['item_code']),
                'description' => $item['description'],
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'vat_rate' => $vatRate,
                'line_subtotal' => $lineSubtotal,
                'line_vat' => $lineVat,
                'line_total' => $lineSubtotal + $lineVat,
            ]);
        }
    }

    private function syncPayments(AdminDocument $document, array $payments): void
    {
        $document->paymentSchedules()->delete();

        foreach ($payments as $payment) {
            if (blank($payment['due_date'] ?? null) || ! isset($payment['amount'])) {
                continue;
            }

            $paidAmount = (float) ($payment['paid_amount'] ?? 0);
            $methodCode = $payment['payment_method_code'] ?? 'MP05';
            $document->paymentSchedules()->create([
                'due_date' => $payment['due_date'],
                'method' => $this->paymentMethodName($methodCode),
                'payment_method_code' => $methodCode,
                'amount' => (float) $payment['amount'],
                'paid_amount' => $paidAmount,
                'paid_at' => $paidAmount > 0 ? ($payment['paid_at'] ?? now()->toDateString()) : null,
                'status' => $paidAmount >= (float) $payment['amount'] ? 'paid' : ($paidAmount > 0 ? 'partial' : 'unpaid'),
                'notes' => $payment['notes'] ?? null,
            ]);
        }
    }

    private function paymentSnapshot(array $data): array
    {
        $methodCode = collect($data['payments'] ?? [])
            ->pluck('payment_method_code')
            ->filter()
            ->first() ?: 'MP05';

        if (($data['payment_conditions'] ?? 'TP02') !== 'TP02') {
            return [
                'payment_method' => null,
                'bank_name' => $methodCode === 'MP05' ? $this->bankName() : null,
                'bank_iban' => $methodCode === 'MP05' ? $this->bankIban() : null,
                'bank_bic' => $methodCode === 'MP05' ? $this->bankBic() : null,
            ];
        }

        return [
            'payment_method' => $methodCode,
            'bank_name' => $methodCode === 'MP05' ? $this->bankName() : null,
            'bank_iban' => $methodCode === 'MP05' ? $this->bankIban() : null,
            'bank_bic' => $methodCode === 'MP05' ? $this->bankBic() : null,
        ];
    }

    private function bankName(): string
    {
        return (string) config('documents.bank.name', '');
    }

    private function bankIban(): string
    {
        return (string) config('documents.bank.iban', '');
    }

    private function bankBic(): string
    {
        return (string) config('documents.bank.bic', '');
    }

    private function paymentMethodName(?string $code): string
    {
        return DocumentsPaymentMethod::query()
            ->where('code', $code ?: 'MP05')
            ->value('name') ?: 'Bonifico bancario';
    }

    private function nextNumber(string $type, int $year): int
    {
        return ((int) AdminDocument::query()
            ->where('type', $type)
            ->where('year', $year)
            ->max('number')) + 1;
    }

    private function buildCode(string $type, int $year, ?int $number = null): string
    {
        $prefix = match ($type) {
            'quote' => 'PREV',
            'proforma' => 'PRO',
            'offline_order' => 'OFF',
            'delivery_note' => 'DDT',
            'invoice' => 'FPR',
            default => 'DOC',
        };

        if ($type === 'offline_order') {
            $prefix = 'OFF';
        }

        $shortYear = substr((string) $year, -2);

        if ($type === 'invoice') {
            return sprintf(
                '%s %d/%s',
                $prefix,
                $number ?: $this->nextNumber($type, $year),
                $shortYear
            );
        }

        return sprintf(
            '%s-%d',
            $prefix,
            $number ?: $this->nextNumber($type, $year)
        );
    }
}
