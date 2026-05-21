<?php

namespace App\Traits;

use App\Enums\DocumentType;
use App\Models\DocumentRelation;
use App\Services\DocumentRelationService;

trait HasDocumentRelations
{
    public function relations()
    {
        return DocumentRelation::where(function ($q) {
            $q->where('from_type', static::documentType())
                ->where('from_id', $this->id);
        })->orWhere(function ($q) {
            $q->where('to_type', static::documentType())
                ->where('to_id', $this->id);
        });
    }

    public function getRelatedDocumentsAttribute()
    {
        $startType = static::documentType();
        $startId = $this->id;
        $visited = collect();
        $queue = collect([
            ['type' => $startType->value, 'id' => $startId],
        ]);

        while ($queue->isNotEmpty()) {
            $current = $queue->shift();
            $key = $current['type'].'-'.$current['id'];

            if ($visited->contains($key)) {
                continue;
            }

            $visited->push($key);

            $relations = DocumentRelation::where(function ($q) use ($current) {
                $q->where('from_type', $current['type'])
                    ->where('from_id', $current['id']);
            })->orWhere(function ($q) use ($current) {
                $q->where('to_type', $current['type'])
                    ->where('to_id', $current['id']);
            })->get();

            foreach ($relations as $rel) {
                $nodes = [
                    ['type' => $rel->from_type->value, 'id' => $rel->from_id],
                    ['type' => $rel->to_type->value, 'id' => $rel->to_id],
                ];

                foreach ($nodes as $node) {
                    $nodeKey = $node['type'].'-'.$node['id'];

                    if (! $visited->contains($nodeKey)) {
                        $queue->push($node);
                    }
                }
            }
        }

        $visited = $visited->reject(fn ($key) => $key === $startType->value.'-'.$startId);

        return $visited->map(function ($key) {
            [$type, $id] = explode('-', $key);
            $documentType = DocumentType::from($type);
            $model = DocumentRelationService::resolveModel($documentType)::query()
                ->where('type', DocumentRelationService::adminType($documentType))
                ->find((int) $id);

            if (! $model) {
                return null;
            }

            return [
                'label' => $model->type_label.' nr. '.$model->display_code,
                'class' => $this->buildClass($documentType),
                'type' => $type,
                'id' => (int) $id,
            ];
        })->filter()->values();
    }

    protected function buildClass($type)
    {
        return match ($type->value) {
            'order' => 'bg-blue-100 text-blue-700',
            'delivery_note' => 'bg-purple-100 text-purple-700',
            'invoice' => 'bg-green-100 text-green-700',
            'quote' => 'bg-yellow-100 text-yellow-700',
            default => 'bg-gray-100 text-gray-700',
        };
    }
}
