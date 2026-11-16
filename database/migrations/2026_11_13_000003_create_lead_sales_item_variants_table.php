<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_sales_item_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_sales_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crm_product_color_id')->constrained('crm_product_colors')->restrictOnDelete();
            $table->foreignId('crm_product_size_id')->constrained('crm_product_sizes')->restrictOnDelete();
            $table->string('color_name');
            $table->string('size_name');
            $table->decimal('quantity', 10, 2);
            $table->timestamps();
            $table->unique(['lead_sales_item_id', 'crm_product_color_id', 'crm_product_size_id'], 'lead_item_variant_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_sales_item_variants');
    }
};
