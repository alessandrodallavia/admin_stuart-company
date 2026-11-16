<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_sales_sheets', function (Blueprint $table) {
            $table->boolean('prices_include_vat')->default(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('lead_sales_sheets', function (Blueprint $table) {
            $table->boolean('prices_include_vat')->default(true)->change();
        });
    }
};
