<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('lead_category_id')->nullable()->after('category')->constrained('lead_categories')->nullOnDelete();
        });

        Schema::create('crm_products', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->decimal('unit_cost', 12, 4)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('crm_product_price_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crm_product_id')->constrained('crm_products')->cascadeOnDelete();
            $table->decimal('min_quantity', 10, 2);
            $table->decimal('max_quantity', 10, 2)->nullable();
            $table->decimal('unit_price', 12, 4);
            $table->timestamps();
        });

        Schema::create('crm_print_types', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('crm_print_price_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crm_print_type_id')->constrained('crm_print_types')->cascadeOnDelete();
            $table->decimal('min_quantity', 10, 2);
            $table->decimal('max_quantity', 10, 2)->nullable();
            $table->decimal('unit_cost', 12, 4);
            $table->decimal('unit_price', 12, 4);
            $table->timestamps();
        });

        Schema::create('lead_sales_sheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('revenue_total', 12, 2)->default(0);
            $table->decimal('cost_total', 12, 2)->default(0);
            $table->decimal('margin_total', 12, 2)->default(0);
            $table->decimal('margin_percentage', 7, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_sales_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_sales_sheet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crm_product_id')->nullable()->constrained('crm_products')->nullOnDelete();
            $table->string('product_code');
            $table->string('product_name');
            $table->decimal('quantity', 10, 2);
            $table->decimal('product_unit_cost', 12, 4);
            $table->decimal('product_unit_price', 12, 4);
            $table->decimal('revenue_total', 12, 2)->default(0);
            $table->decimal('cost_total', 12, 2)->default(0);
            $table->decimal('margin_total', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('lead_sales_item_prints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_sales_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crm_print_type_id')->nullable()->constrained('crm_print_types')->nullOnDelete();
            $table->string('print_code');
            $table->string('print_name');
            $table->decimal('unit_cost', 12, 4);
            $table->decimal('unit_price', 12, 4);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_sales_item_prints');
        Schema::dropIfExists('lead_sales_items');
        Schema::dropIfExists('lead_sales_sheets');
        Schema::dropIfExists('crm_print_price_tiers');
        Schema::dropIfExists('crm_print_types');
        Schema::dropIfExists('crm_product_price_tiers');
        Schema::dropIfExists('crm_products');
        Schema::table('leads', fn (Blueprint $table) => $table->dropConstrainedForeignId('lead_category_id'));
        Schema::dropIfExists('lead_categories');
    }
};
