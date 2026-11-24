<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_products', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique();
        });
        DB::table('crm_products')->orderBy('id')->chunkById(200, function ($products) {
            foreach ($products as $product) {
                $base = substr(Str::slug($product->name.'-'.$product->code), 0, 240);
                if (! preg_match('/[a-z]/', $base)) {
                    $base = 'prodotto-'.$base;
                }
                $base = rtrim($base, '-');
                $slug = $base;
                for ($suffix = 2; DB::table('crm_products')->where('slug', $slug)->exists(); $suffix++) {
                    $slug = $base.'-'.$suffix;
                }
                DB::table('crm_products')->where('id', $product->id)->update(['slug' => $slug]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('crm_products', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
