<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_brands', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });
        Schema::create('crm_suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('website', 2048)->nullable();
            $table->timestamps();
        });
        Schema::create('crm_brand_supplier', function (Blueprint $table) {
            $table->foreignId('crm_brand_id')->constrained('crm_brands')->cascadeOnDelete();
            $table->foreignId('crm_supplier_id')->constrained('crm_suppliers')->cascadeOnDelete();
            $table->primary(['crm_brand_id', 'crm_supplier_id']);
        });
        if (DB::getDriverName() === 'sqlite') {
            // Add the nullable reference in place: rebuilding the product table
            // with foreign keys enabled would cascade-delete existing price tiers.
            DB::statement('ALTER TABLE crm_products ADD COLUMN crm_brand_id INTEGER REFERENCES crm_brands(id) ON DELETE RESTRICT');
        }
        Schema::table('crm_products', function (Blueprint $table) {
            if (DB::getDriverName() !== 'sqlite') {
                $table->foreignId('crm_brand_id')->nullable()->constrained('crm_brands')->restrictOnDelete();
            }
            $table->boolean('is_visible_on_site')->default(false)->index();
            $table->string('supplier_product_url', 2048)->nullable();
            $table->string('datasheet_path')->nullable();
            $table->string('datasheet_filename')->nullable();
        });
        Schema::create('crm_product_supplier', function (Blueprint $table) {
            $table->foreignId('crm_product_id')->constrained('crm_products')->cascadeOnDelete();
            $table->foreignId('crm_supplier_id')->constrained('crm_suppliers')->restrictOnDelete();
            $table->primary(['crm_product_id', 'crm_supplier_id']);
        });
        Schema::create('crm_product_colors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crm_product_id')->constrained('crm_products')->cascadeOnDelete();
            $table->string('code', 100);
            $table->string('name', 100);
            $table->char('hex', 7)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unique(['crm_product_id', 'code']);
        });
        Schema::create('crm_product_sizes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crm_product_id')->constrained('crm_products')->cascadeOnDelete();
            $table->string('name', 50);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unique(['crm_product_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_product_sizes');
        Schema::dropIfExists('crm_product_colors');
        Schema::dropIfExists('crm_product_supplier');
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('ALTER TABLE crm_products DROP COLUMN crm_brand_id');
        }
        Schema::table('crm_products', function (Blueprint $table) {
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropConstrainedForeignId('crm_brand_id');
            }
            $table->dropIndex(['is_visible_on_site']);
            $table->dropColumn(['is_visible_on_site', 'supplier_product_url', 'datasheet_path', 'datasheet_filename']);
        });
        Schema::dropIfExists('crm_brand_supplier');
        Schema::dropIfExists('crm_suppliers');
        Schema::dropIfExists('crm_brands');
    }
};
