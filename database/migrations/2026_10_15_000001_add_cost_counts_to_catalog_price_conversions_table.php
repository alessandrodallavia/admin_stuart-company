<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalog_price_conversions', function (Blueprint $table) {
            if (! Schema::hasColumn('catalog_price_conversions', 'product_costs_converted')) {
                $table->unsignedInteger('product_costs_converted')->default(0)->after('print_prices_converted');
            }

            if (! Schema::hasColumn('catalog_price_conversions', 'print_costs_converted')) {
                $table->unsignedInteger('print_costs_converted')->default(0)->after('product_costs_converted');
            }
        });
    }

    public function down(): void
    {
        Schema::table('catalog_price_conversions', function (Blueprint $table) {
            $columns = array_values(array_filter([
                Schema::hasColumn('catalog_price_conversions', 'product_costs_converted') ? 'product_costs_converted' : null,
                Schema::hasColumn('catalog_price_conversions', 'print_costs_converted') ? 'print_costs_converted' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
