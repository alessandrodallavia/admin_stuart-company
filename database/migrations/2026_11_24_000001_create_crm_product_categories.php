<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_product_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->foreignId('parent_id')->nullable()->constrained('crm_product_categories')->restrictOnDelete();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        if (DB::getDriverName() === 'sqlite') {
            // Avoid rebuilding the product table: that could cascade-delete price tiers.
            DB::statement('ALTER TABLE crm_products ADD COLUMN crm_product_category_id INTEGER REFERENCES crm_product_categories(id) ON DELETE RESTRICT');
        } else {
            Schema::table('crm_products', function (Blueprint $table) {
                $table->foreignId('crm_product_category_id')->nullable()->constrained('crm_product_categories')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('ALTER TABLE crm_products DROP COLUMN crm_product_category_id');
        } else {
            Schema::table('crm_products', function (Blueprint $table) {
                $table->dropConstrainedForeignId('crm_product_category_id');
            });
        }
        Schema::dropIfExists('crm_product_categories');
    }
};
