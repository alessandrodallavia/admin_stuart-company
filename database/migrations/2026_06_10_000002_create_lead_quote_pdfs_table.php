<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_quote_pdfs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('filename');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();

            $table->index(['lead_id', 'uploaded_at']);
        });

        DB::table('leads')
            ->whereNotNull('quote_pdf_path')
            ->orderBy('id')
            ->each(function ($lead) {
                DB::table('lead_quote_pdfs')->insert([
                    'lead_id' => $lead->id,
                    'disk' => $lead->quote_pdf_disk ?: 'local',
                    'path' => $lead->quote_pdf_path,
                    'filename' => $lead->quote_pdf_filename ?: 'preventivo.pdf',
                    'mime_type' => $lead->quote_pdf_mime_type ?: 'application/pdf',
                    'size' => $lead->quote_pdf_size,
                    'uploaded_at' => $lead->quote_pdf_uploaded_at,
                    'created_at' => $lead->quote_pdf_uploaded_at ?: now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_quote_pdfs');
    }
};
