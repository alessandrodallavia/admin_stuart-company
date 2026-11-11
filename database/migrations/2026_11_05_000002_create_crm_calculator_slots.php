<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_calculator_slots', function (Blueprint $table) {
            $table->id();
            $table->string('family', 30);
            $table->string('model', 30);
            $table->string('quality', 30);
            $table->foreignId('crm_product_id')->nullable()->constrained('crm_products')->nullOnDelete();
            $table->boolean('is_enabled_on_site')->default(false);
            $table->timestamps();
            $table->unique(['family', 'model', 'quality']);
        });
        Schema::create('crm_calculator_slot_print_type', function (Blueprint $table) {
            $table->foreignId('crm_calculator_slot_id')->constrained('crm_calculator_slots')->cascadeOnDelete();
            $table->foreignId('crm_print_type_id')->constrained('crm_print_types')->cascadeOnDelete();
            $table->primary(['crm_calculator_slot_id', 'crm_print_type_id']);
        });
        $prints = DB::table('crm_print_types')->where('is_active', true)->whereIn('code', ['STAMPA_CUORE', 'STAMPA_FRONT', 'STAMPA_BACK', 'STAMPA_MAX'])->pluck('id');
        foreach (['hood' => ['21953', 'BS332', 'BS342'], 'crewneck' => ['21951', 'BS331', 'BS341'], 'zip' => ['21956', 'BS333', 'BS343']] as $model => $codes) {
            foreach (array_combine(['economic', 'basic', 'organic'], $codes) as $quality => $code) {
                $id = DB::table('crm_calculator_slots')->insertGetId([
                    'family' => 'hoodie', 'model' => $model, 'quality' => $quality,
                    'crm_product_id' => DB::table('crm_products')->where('code', $code)->value('id'),
                    'is_enabled_on_site' => false, 'created_at' => now(), 'updated_at' => now(),
                ]);
                foreach ($prints as $print) {
                    DB::table('crm_calculator_slot_print_type')->insert(['crm_calculator_slot_id' => $id, 'crm_print_type_id' => $print]);
                }
            }
        }
        foreach (['economic', 'basic', 'premium'] as $quality) {
            DB::table('crm_calculator_slots')->insert(['family' => 'tshirt', 'model' => 'tshirt', 'quality' => $quality, 'is_enabled_on_site' => false, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_calculator_slot_print_type');
        Schema::dropIfExists('crm_calculator_slots');
    }
};
