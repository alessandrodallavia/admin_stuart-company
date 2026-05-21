<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Models\AdminDocument;
use App\Models\DocumentRelation;
use Exception;

class DocumentRelationService
{
    public static function link(
        DocumentType $typeA,
        int $idA,
        DocumentType $typeB,
        int $idB,
        ?string $relationType = 'manual'
    ): DocumentRelation {
        if ($typeA === $typeB && $idA === $idB) {
            throw new Exception('Non puoi collegare un documento a sé stesso');
        }

        if (self::shouldSwap($typeA, $idA, $typeB, $idB)) {
            [$typeA, $typeB] = [$typeB, $typeA];
            [$idA, $idB] = [$idB, $idA];
        }

        $exists = DocumentRelation::where([
            'from_type' => $typeA,
            'from_id' => $idA,
            'to_type' => $typeB,
            'to_id' => $idB,
        ])->exists();

        if ($exists) {
            throw new Exception('Relazione già esistente');
        }

        self::validateSameCustomer($typeA, $idA, $typeB, $idB);

        return DocumentRelation::create([
            'from_type' => $typeA,
            'from_id' => $idA,
            'to_type' => $typeB,
            'to_id' => $idB,
            'relation_type' => $relationType,
        ]);
    }

    protected static function shouldSwap($typeA, $idA, $typeB, $idB): bool
    {
        return $typeA->value > $typeB->value
            || ($typeA === $typeB && $idA > $idB);
    }

    protected static function validateSameCustomer($typeA, $idA, $typeB, $idB): void
    {
        $docA = self::resolveModel($typeA)::where('type', self::adminType($typeA))->findOrFail($idA);
        $docB = self::resolveModel($typeB)::where('type', self::adminType($typeB))->findOrFail($idB);

        if ($docA->customer_name !== $docB->customer_name) {
            throw new Exception('Documenti di clienti diversi');
        }
    }

    public static function resolveModel(DocumentType $type)
    {
        return AdminDocument::class;
    }

    public static function adminType(DocumentType $type): string
    {
        return match ($type) {
            DocumentType::ORDER => 'offline_order',
            default => $type->value,
        };
    }
}
