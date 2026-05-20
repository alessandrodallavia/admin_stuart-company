<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('quote_pdf_disk')->nullable()->after('payment_amount');
            $table->string('quote_pdf_path')->nullable()->after('quote_pdf_disk');
            $table->string('quote_pdf_filename')->nullable()->after('quote_pdf_path');
            $table->string('quote_pdf_mime_type')->nullable()->after('quote_pdf_filename');
            $table->unsignedBigInteger('quote_pdf_size')->nullable()->after('quote_pdf_mime_type');
            $table->timestamp('quote_pdf_uploaded_at')->nullable()->after('quote_pdf_size');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn([
                'quote_pdf_disk',
                'quote_pdf_path',
                'quote_pdf_filename',
                'quote_pdf_mime_type',
                'quote_pdf_size',
                'quote_pdf_uploaded_at',
            ]);
        });
    }
};
