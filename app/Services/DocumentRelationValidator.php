<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Models\DocumentRelation;
use App\Support\DocumentFlow;
use Exception;

class DocumentRelationValidator
{
    public static function validate(
        DocumentType $parentType,
        int $parentId,
        DocumentType $childType,
        int $childId
    ): void {
        if ($parentType === $childType && $parentId === $childId) {
            throw new Exception('Non puoi collegare un documento a sé stesso');
        }

        $allowed = DocumentFlow::allowed()[$parentType->value] ?? [];

        if (! in_array($childType->value, $allowed, true)) {
            throw new Exception("Relazione non valida: {$parentType->value} → {$childType->value}");
        }

        $exists = DocumentRelation::where([
            'from_type' => $parentType,
            'from_id' => $parentId,
            'to_type' => $childType,
            'to_id' => $childId,
        ])->exists();

        if ($exists) {
            throw new Exception('Relazione già esistente');
        }
    }
}
