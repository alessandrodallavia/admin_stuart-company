<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Models\AdminDocument;
use App\Models\DocumentRelation;
use Illuminate\Support\Facades\DB;

class DocumentGeneratorService
{
    public function __construct(
        private readonly AdminDocumentService $documentService
    ) {}

    public function fromQuoteToOrder(int $quoteId): AdminDocument
    {
        return $this->generate($quoteId, DocumentType::QUOTE, DocumentType::ORDER, 'offline_order');
    }

    public function fromQuoteToProforma(int $quoteId): AdminDocument
    {
        return $this->generate($quoteId, DocumentType::QUOTE, DocumentType::PROFORMA, 'proforma');
    }

    public function fromQuoteToInvoice(int $quoteId): AdminDocument
    {
        return $this->generate($quoteId, DocumentType::QUOTE, DocumentType::INVOICE, 'invoice');
    }

    public function fromProformaToOrder(int $proformaId): AdminDocument
    {
        return $this->generate($proformaId, DocumentType::PROFORMA, DocumentType::ORDER, 'offline_order');
    }

    public function fromProformaToInvoice(int $proformaId): AdminDocument
    {
        return $this->generate($proformaId, DocumentType::PROFORMA, DocumentType::INVOICE, 'invoice');
    }

    public function fromOrderToDeliveryNote(int $orderId): AdminDocument
    {
        return $this->generate($orderId, DocumentType::ORDER, DocumentType::DELIVERY_NOTE, 'delivery_note');
    }

    public function fromOrderToInvoice(int $orderId): AdminDocument
    {
        return $this->generate($orderId, DocumentType::ORDER, DocumentType::INVOICE, 'invoice');
    }

    public function fromDeliveryNoteToInvoice(int $noteId): AdminDocument
    {
        return $this->generate($noteId, DocumentType::DELIVERY_NOTE, DocumentType::INVOICE, 'invoice');
    }

    private function generate(int $sourceId, DocumentType $sourceType, DocumentType $targetType, string $adminTargetType): AdminDocument
    {
        return DB::transaction(function () use ($sourceId, $sourceType, $targetType, $adminTargetType) {
            if ($existing = $this->existingTarget($sourceType, $sourceId, $targetType)) {
                return $existing;
            }

            DocumentRelationValidator::validate($sourceType, $sourceId, $targetType, 0);

            $source = AdminDocument::query()
                ->where('type', DocumentRelationService::adminType($sourceType))
                ->findOrFail($sourceId);

            $generated = $this->documentService->duplicateAs(
                $source,
                $adminTargetType,
                $targetType === DocumentType::INVOICE
            );

            DocumentRelationService::link(
                $sourceType,
                $source->id,
                $targetType,
                $generated->id,
                'conversion'
            );

            return $generated;
        });
    }

    private function existingTarget(DocumentType $sourceType, int $sourceId, DocumentType $targetType): ?AdminDocument
    {
        $relation = DocumentRelation::query()
            ->where(function ($query) use ($sourceType, $sourceId, $targetType) {
                $query->where('from_type', $sourceType)
                    ->where('from_id', $sourceId)
                    ->where('to_type', $targetType);
            })
            ->orWhere(function ($query) use ($sourceType, $sourceId, $targetType) {
                $query->where('to_type', $sourceType)
                    ->where('to_id', $sourceId)
                    ->where('from_type', $targetType);
            })
            ->first();

        if (! $relation) {
            return null;
        }

        return AdminDocument::query()
            ->where('type', DocumentRelationService::adminType($targetType))
            ->find($relation->to_type === $targetType ? $relation->to_id : $relation->from_id);
    }
}
