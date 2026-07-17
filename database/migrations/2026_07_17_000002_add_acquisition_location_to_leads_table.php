<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('acquisition_country', 100)->nullable()->after('search_term');
            $table->string('acquisition_region', 150)->nullable()->after('acquisition_country');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['acquisition_country', 'acquisition_region']);
        });
    }
};
