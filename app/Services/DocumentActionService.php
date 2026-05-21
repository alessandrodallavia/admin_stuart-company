<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Models\DocumentRelation;
use App\Support\DocumentFlow;

class DocumentActionService
{
    public static function availableActions(
        DocumentType $type,
        int $id
    ): array {
        $allowed = DocumentFlow::allowed()[$type->value] ?? [];

        return collect($allowed)
            ->reject(fn ($targetType) => self::hasLinkedTypeRecursive($type, $id, $targetType))
            ->values()
            ->all();
    }

    private static function hasLinkedTypeRecursive(
        DocumentType $startType,
        int $startId,
        string $targetType
    ): bool {
        $visited = [];
        $queue = [[$startType->value, $startId]];

        while (! empty($queue)) {
            [$currentType, $currentId] = array_shift($queue);
            $key = $currentType.'-'.$currentId;

            if (isset($visited[$key])) {
                continue;
            }

            $visited[$key] = true;

            $relations = DocumentRelation::where(function ($q) use ($currentType, $currentId) {
                $q->where('from_type', $currentType)
                    ->where('from_id', $currentId);
            })->orWhere(function ($q) use ($currentType, $currentId) {
                $q->where('to_type', $currentType)
                    ->where('to_id', $currentId);
            })->get();

            foreach ($relations as $rel) {
                if ($rel->from_type->value === $currentType && $rel->from_id === $currentId) {
                    $nextType = $rel->to_type->value;
                    $nextId = $rel->to_id;
                } else {
                    $nextType = $rel->from_type->value;
                    $nextId = $rel->from_id;
                }

                if ($nextType === $targetType) {
                    return true;
                }

                $queue[] = [$nextType, $nextId];
            }
        }

        return false;
    }
}
