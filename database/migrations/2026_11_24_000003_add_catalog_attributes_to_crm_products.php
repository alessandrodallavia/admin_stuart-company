<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_products', function (Blueprint $table) {
            foreach (['model', 'gender', 'fit', 'range', 'composition', 'dye'] as $attribute) {
                $table->string('catalog_'.$attribute)->nullable();
            }
            $table->unsignedSmallInteger('catalog_weight_gsm')->nullable();
            $table->json('catalog_usage')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('crm_products', function (Blueprint $table) {
            $table->dropColumn(['catalog_model', 'catalog_gender', 'catalog_fit', 'catalog_range', 'catalog_composition', 'catalog_dye', 'catalog_weight_gsm', 'catalog_usage']);
        });
    }
};
