<?php

use App\Enums\DocumentType;
use App\Models\DocumentRelation;
use App\Services\DocumentRelationService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        DocumentRelation::query()
            ->get()
            ->each(function (DocumentRelation $relation) {
                if (
                    ! $this->documentExists($relation->from_type, $relation->from_id)
                    || ! $this->documentExists($relation->to_type, $relation->to_id)
                ) {
                    $relation->delete();
                }
            });
    }

    public function down(): void
    {
        //
    }

    private function documentExists(DocumentType $type, int $id): bool
    {
        return DocumentRelationService::resolveModel($type)::query()
            ->where('type', DocumentRelationService::adminType($type))
            ->whereKey($id)
            ->exists();
    }
};
