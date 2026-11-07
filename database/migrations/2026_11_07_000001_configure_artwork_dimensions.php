<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_print_types', function (Blueprint $table) {
            $table->decimal('max_width_cm', 6, 2)->nullable();
            $table->decimal('max_height_cm', 6, 2)->nullable();
            $table->string('legacy_preview_key', 20)->nullable();
        });
        foreach (['HEART' => 'STAMPA_CUORE', 'STANDARD' => 'STAMPA_FRONT', 'LARGE' => 'STAMPA_BACK', 'MAX' => 'STAMPA_MAX'] as $key => $code) {
            DB::table('crm_print_types')->where('code', $code)->update(['legacy_preview_key' => $key]);
        }
        Schema::create('crm_product_print_areas', function (Blueprint $table) {
            $table->foreignId('crm_product_id')->constrained('crm_products')->cascadeOnDelete();
            $table->string('position', 20);
            foreach (['width_cm', 'height_cm', 'x_percent', 'y_percent', 'width_percent', 'height_percent'] as $field) {
                $table->decimal($field, 6, 2);
            }
            $table->primary(['crm_product_id', 'position']);
        });
    }

    public function down(): void
    { /* Preserve configured dimensions and legacy identities. */
    }
};
