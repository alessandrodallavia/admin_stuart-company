<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_product_position_print_type', function (Blueprint $table) {
            $table->foreignId('crm_product_id')->constrained('crm_products')->cascadeOnDelete();
            $table->string('position', 20);
            $table->foreignId('crm_print_type_id')->constrained('crm_print_types')->cascadeOnDelete();
            $table->primary(['crm_product_id', 'position', 'crm_print_type_id'], 'crm_product_position_print_primary');
        });
        // No defaults: each position must be explicitly configured by the operator.
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_product_position_print_type');
    }
};
