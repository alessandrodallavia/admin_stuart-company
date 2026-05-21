<?php

namespace App\Services;

use App\Models\AdminDocument;
use Illuminate\Support\Arr;
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

    public function duplicateAs(AdminDocument $source, string $type): AdminDocument
    {
        return DB::transaction(function () use ($source, $type) {
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
                ]),
                'type' => $type,
                'number' => $this->nextNumber($type, now()->year),
                'year' => now()->year,
                'code' => $this->buildCode($type, now()->year),
                'document_date' => now()->toDateString(),
                'status' => 'draft',
                'payment_status' => 'unpaid',
                'source_document_id' => $source->id,
            ]);

            foreach ($source->items as $item) {
                $document->items()->create(Arr::except($item->toArray(), ['id', 'admin_document_id', 'created_at', 'updated_at']));
            }

            foreach ($source->paymentSchedules as $payment) {
                $document->paymentSchedules()->create([
                    ...Arr::except($payment->toArray(), ['id', 'admin_document_id', 'created_at', 'updated_at', 'paid_amount', 'paid_at', 'status']),
                    'paid_amount' => 0,
                    'paid_at' => null,
                    'status' => 'unpaid',
                ]);
            }

            $document->refreshTotals();

            return $document->fresh(['items', 'paymentSchedules']);
        });
    }

    public function markPayment(AdminDocument $document, int $scheduleId, float $paidAmount, ?string $paidAt): void
    {
        DB::transaction(function () use ($document, $scheduleId, $paidAmount, $paidAt) {
            $schedule = $document->paymentSchedules()->findOrFail($scheduleId);
            $schedule->update([
                'paid_amount' => $paidAmount,
                'paid_at' => $paidAmount > 0 ? ($paidAt ?: now()->toDateString()) : null,
                'status' => $paidAmount >= (float) $schedule->amount ? 'paid' : ($paidAmount > 0 ? 'partial' : 'unpaid'),
            ]);

            $document->refreshPaymentStatus();
        });
    }

    private function documentAttributes(array $data, ?AdminDocument $document = null): array
    {
        $year = (int) date('Y', strtotime($data['document_date']));
        $number = $document?->number ?: $this->nextNumber($data['type'], $year);

        return Arr::only($data, [
            'type',
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
        ]) + [
            'year' => $year,
            'number' => $number,
            'code' => $document?->code ?: $this->buildCode($data['type'], $year, $number),
        ];
    }

    private function syncItems(AdminDocument $document, array $items): void
    {
        $document->items()->delete();

        foreach (array_values($items) as $index => $item) {
            if (blank($item['description'] ?? null)) {
                continue;
            }

            $quantity = (float) ($item['quantity'] ?? 1);
            $unitPrice = (float) ($item['unit_price'] ?? 0);
            $vatRate = (float) ($item['vat_rate'] ?? 22);
            $lineSubtotal = round($quantity * $unitPrice, 2);
            $lineVat = round($lineSubtotal * $vatRate / 100, 2);

            $document->items()->create([
                'position' => $index + 1,
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
            $document->paymentSchedules()->create([
                'due_date' => $payment['due_date'],
                'method' => $payment['method'] ?? null,
                'amount' => (float) $payment['amount'],
                'paid_amount' => $paidAmount,
                'paid_at' => $paidAmount > 0 ? ($payment['paid_at'] ?? now()->toDateString()) : null,
                'status' => $paidAmount >= (float) $payment['amount'] ? 'paid' : ($paidAmount > 0 ? 'partial' : 'unpaid'),
                'notes' => $payment['notes'] ?? null,
            ]);
        }
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
            'offline_order' => 'ORD',
            'delivery_note' => 'DDT',
            'invoice' => 'FAT',
            default => 'DOC',
        };

        return sprintf('%s-%s-%04d', $prefix, $year, $number ?: $this->nextNumber($type, $year));
    }
}
