<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_order_checkouts', function (Blueprint $table) {
            $table->foreignId('lead_quote_pdf_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! DB::table('lead_order_checkouts')->whereNull('lead_quote_pdf_id')->exists()) {
            Schema::table('lead_order_checkouts', function (Blueprint $table) {
                $table->foreignId('lead_quote_pdf_id')->nullable(false)->change();
            });
        }
    }
};
