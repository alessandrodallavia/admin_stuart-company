<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('leads', 'cta_origin')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->text('cta_origin')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('leads', 'cta_origin')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->dropColumn('cta_origin');
            });
        }
    }
};
