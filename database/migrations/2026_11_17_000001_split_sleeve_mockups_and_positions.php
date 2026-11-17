<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crm_product_colors') && ! Schema::hasColumn('crm_product_colors', 'mockup_sleeve_left_path')) {
            Schema::table('crm_product_colors', function (Blueprint $table) {
                $table->string('mockup_sleeve_left_path', 1024)->nullable()->after('mockup_back_path');
                $table->string('mockup_sleeve_right_path', 1024)->nullable()->after('mockup_sleeve_left_path');
            });
        }

        if (Schema::hasTable('leads') && ! Schema::hasColumn('leads', 'live_mockup_sleeve_left_file')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->text('live_mockup_sleeve_left_file')->nullable()->after('live_mockup_sleeve_file');
                $table->text('live_mockup_sleeve_right_file')->nullable()->after('live_mockup_sleeve_left_file');
            });
        }

        if (! Schema::hasTable('crm_product_position_print_type') || ! Schema::hasTable('crm_product_print_areas')) {
            return;
        }

        DB::table('crm_product_position_print_type')->where('position', 'sleeve')->orderBy('crm_product_id')->each(function ($rule) {
            foreach (['sleeve_left', 'sleeve_right'] as $position) {
                DB::table('crm_product_position_print_type')->insertOrIgnore([
                    'crm_product_id' => $rule->crm_product_id,
                    'position' => $position,
                    'crm_print_type_id' => $rule->crm_print_type_id,
                ]);
            }
        });
        DB::table('crm_product_position_print_type')->where('position', 'sleeve')->delete();

        DB::table('crm_product_print_areas')->where('position', 'sleeve')->orderBy('crm_product_id')->each(function ($area) {
            $values = (array) $area;
            unset($values['position']);
            foreach (['sleeve_left', 'sleeve_right'] as $position) {
                DB::table('crm_product_print_areas')->insertOrIgnore([...$values, 'position' => $position]);
            }
        });
        DB::table('crm_product_print_areas')->where('position', 'sleeve')->delete();
    }

    public function down(): void
    {
        DB::table('crm_product_position_print_type')->whereIn('position', ['sleeve_left', 'sleeve_right'])->orderBy('crm_product_id')->each(function ($rule) {
            DB::table('crm_product_position_print_type')->insertOrIgnore([
                'crm_product_id' => $rule->crm_product_id,
                'position' => 'sleeve',
                'crm_print_type_id' => $rule->crm_print_type_id,
            ]);
        });
        DB::table('crm_product_position_print_type')->whereIn('position', ['sleeve_left', 'sleeve_right'])->delete();

        DB::table('crm_product_print_areas')->where('position', 'sleeve_left')->orderBy('crm_product_id')->each(function ($area) {
            $values = (array) $area;
            unset($values['position']);
            DB::table('crm_product_print_areas')->insertOrIgnore([...$values, 'position' => 'sleeve']);
        });
        DB::table('crm_product_print_areas')->whereIn('position', ['sleeve_left', 'sleeve_right'])->delete();

        Schema::table('leads', fn (Blueprint $table) => $table->dropColumn(['live_mockup_sleeve_left_file', 'live_mockup_sleeve_right_file']));
        Schema::table('crm_product_colors', fn (Blueprint $table) => $table->dropColumn(['mockup_sleeve_left_path', 'mockup_sleeve_right_path']));
    }
};
