<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_product_categories', function (Blueprint $table) {
            $table->string('image_path')->nullable();
            $table->string('image_alt')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('crm_product_categories', function (Blueprint $table) {
            $table->dropColumn(['image_path', 'image_alt']);
        });
    }
};
