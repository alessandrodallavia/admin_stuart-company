<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_sales_sheets', function (Blueprint $table) {
            $table->decimal('minimum_order_amount', 12, 2)->nullable()->after('rounding_adjustment');
            $table->decimal('minimum_order_adjustment', 12, 2)->default(0)->after('minimum_order_amount');
        });

        Schema::table('lead_order_checkouts', function (Blueprint $table) {
            $table->decimal('minimum_order_amount', 12, 2)->nullable()->after('rounding_adjustment');
            $table->decimal('minimum_order_adjustment', 12, 2)->default(0)->after('minimum_order_amount');
        });
    }

    public function down(): void
    {
        Schema::table('lead_order_checkouts', fn (Blueprint $table) => $table->dropColumn(['minimum_order_amount', 'minimum_order_adjustment']));
        Schema::table('lead_sales_sheets', fn (Blueprint $table) => $table->dropColumn(['minimum_order_amount', 'minimum_order_adjustment']));
    }
};
