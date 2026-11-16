<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_sales_sheets', function (Blueprint $table) {
            $table->boolean('prices_include_vat')->default(false)->after('status');
        });

        // Tutti gli ordini già presenti al momento della migrazione usavano
        // importi IVA inclusa; i nuovi ordini lavorano invece al netto.
        DB::table('lead_sales_sheets')->update(['prices_include_vat' => true]);
    }

    public function down(): void
    {
        Schema::table('lead_sales_sheets', function (Blueprint $table) {
            $table->dropColumn('prices_include_vat');
        });
    }
};
