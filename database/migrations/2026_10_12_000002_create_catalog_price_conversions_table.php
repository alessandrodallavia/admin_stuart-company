<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_price_conversions', function (Blueprint $table) {
            $table->id();
            $table->decimal('vat_rate', 5, 2);
            $table->unsignedInteger('product_prices_converted');
            $table->unsignedInteger('print_prices_converted');
            $table->unsignedInteger('product_costs_converted');
            $table->unsignedInteger('print_costs_converted');
            $table->timestamp('applied_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_price_conversions');
    }
};
