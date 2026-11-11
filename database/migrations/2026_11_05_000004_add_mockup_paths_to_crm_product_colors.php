<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_product_colors', function (Blueprint $table) {
            $table->boolean('mockup_customized')->default(false);
            $table->string('mockup_front_path', 1024)->nullable();
            $table->string('mockup_back_path', 1024)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('crm_product_colors', function (Blueprint $table) {
            $table->dropColumn(['mockup_front_path', 'mockup_back_path', 'mockup_customized']);
        });
    }
};
