<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_relations', function (Blueprint $table) {
            $table->id();
            $table->string('from_type');
            $table->unsignedBigInteger('from_id');
            $table->string('to_type');
            $table->unsignedBigInteger('to_id');
            $table->string('relation_type')->nullable();
            $table->timestamps();

            $table->index(['from_type', 'from_id']);
            $table->index(['to_type', 'to_id']);
            $table->unique(['from_type', 'from_id', 'to_type', 'to_id'], 'document_relation_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_relations');
    }
};
