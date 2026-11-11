<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The Camac SQL import may already have created these tables.
        if (! Schema::hasTable('crm_product_supplier_details')) {
            Schema::create('crm_product_supplier_details', function (Blueprint $table) {
                $table->foreignId('crm_product_id')->constrained('crm_products')->cascadeOnDelete();
                $table->foreignId('crm_supplier_id')->constrained('crm_suppliers')->cascadeOnDelete();
                $table->string('supplier_product_name');
                $table->text('composition');
                $table->unsignedSmallInteger('weight_gsm');
                $table->text('details');
                $table->string('datasheet_url', 2048);
                $table->string('source_url', 2048);
                $table->date('source_checked_at');
                $table->primary(['crm_product_id', 'crm_supplier_id']);
            });
        }
        if (! Schema::hasTable('crm_product_color_sizes')) {
            Schema::create('crm_product_color_sizes', function (Blueprint $table) {
                $table->foreignId('crm_product_color_id')->constrained('crm_product_colors')->cascadeOnDelete();
                $table->foreignId('crm_product_size_id')->constrained('crm_product_sizes')->cascadeOnDelete();
                $table->primary(['crm_product_color_id', 'crm_product_size_id']);
            });
        }
        if (! Schema::hasColumn('crm_product_colors', 'sizes_restricted')) {
            Schema::table('crm_product_colors', fn (Blueprint $table) => $table->boolean('sizes_restricted')->default(false));
            // Imported subsets are explicit restrictions. Legacy colors inherit all sizes.
            DB::table('crm_product_colors')->orderBy('id')->chunkById(200, function ($colors) {
                foreach ($colors as $color) {
                    $sizes = DB::table('crm_product_sizes')->where('crm_product_id', $color->crm_product_id)->pluck('id');
                    $mapped = DB::table('crm_product_color_sizes')->where('crm_product_color_id', $color->id)->pluck('crm_product_size_id');
                    if ($mapped->isNotEmpty() && $mapped->count() < $sizes->count()) {
                        DB::table('crm_product_colors')->where('id', $color->id)->update(['sizes_restricted' => true]);
                    } else {
                        foreach ($sizes as $sizeId) {
                            DB::table('crm_product_color_sizes')->insertOrIgnore(['crm_product_color_id' => $color->id, 'crm_product_size_id' => $sizeId]);
                        }
                    }
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_product_color_sizes');
        Schema::dropIfExists('crm_product_supplier_details');
        Schema::table('crm_product_colors', fn (Blueprint $table) => $table->dropColumn('sizes_restricted'));
    }
};
