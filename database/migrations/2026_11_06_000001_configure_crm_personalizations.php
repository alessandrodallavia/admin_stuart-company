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
            $table->string('technique', 30)->default('print');
            $table->string('preview_layout', 30)->default('STANDARD');
            $table->string('size_label')->nullable();
            $table->json('allowed_sides')->nullable();
            $table->foreignId('setup_print_type_id')->nullable()->constrained('crm_print_types')->restrictOnDelete();
            $table->decimal('fixed_price', 12, 2)->nullable();
        });
        $layouts = ['STAMPA_CUORE' => ['HEART', 'max 10 x 10 cm'], 'STAMPA_FRONT' => ['STANDARD', 'max 25 x 15 cm'], 'STAMPA_BACK' => ['LARGE', 'max 25 x 30 cm'], 'STAMPA_MAX' => ['MAX', 'dimensione massima compatibile con il capo e la taglia'], 'RICAMO_CUORE' => ['HEART', null], 'RICAMO_MEDIO' => ['STANDARD', null], 'RICAMO_GRANDE' => ['LARGE', null]];
        foreach ($layouts as $code => [$layout, $size]) {
            DB::table('crm_print_types')->where('code', $code)->update(['preview_layout' => $layout, 'size_label' => $size]);
        }
        DB::table('crm_print_types')->where('code', 'IMPIANTO_RICAMO')->update(['technique' => 'setup']);
        $setup = DB::table('crm_print_types')->where('code', 'IMPIANTO_RICAMO')->value('id');
        DB::table('crm_print_types')->whereIn('code', ['RICAMO_CUORE', 'RICAMO_MEDIO', 'RICAMO_GRANDE'])->update(['technique' => 'embroidery', 'setup_print_type_id' => $setup]);
    }

    public function down(): void
    {
        Schema::table('crm_print_types', function (Blueprint $table) {
            $table->dropForeign(['setup_print_type_id']);
            $table->dropColumn(['technique', 'preview_layout', 'size_label', 'allowed_sides', 'setup_print_type_id', 'fixed_price']);
        });
    }
};
