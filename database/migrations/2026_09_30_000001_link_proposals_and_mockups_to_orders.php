<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_quote_pdfs', function (Blueprint $table) {
            $table->foreignId('lead_sales_sheet_id')->nullable()->after('lead_id')->constrained()->nullOnDelete();
        });

        Schema::table('lead_sales_item_attachments', function (Blueprint $table) {
            $table->string('role', 30)->default('artwork')->after('lead_sales_item_id');
        });
    }

    public function down(): void
    {
        Schema::table('lead_quote_pdfs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('lead_sales_sheet_id');
        });

        Schema::table('lead_sales_item_attachments', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
