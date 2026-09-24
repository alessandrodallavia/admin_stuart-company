<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_sales_items', function (Blueprint $table) {
            $table->string('size_chart_path')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('lead_sales_items', function (Blueprint $table) {
            $table->dropColumn('size_chart_path');
        });
    }
};
