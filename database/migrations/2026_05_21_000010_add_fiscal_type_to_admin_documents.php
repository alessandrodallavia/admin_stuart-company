<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_documents', function (Blueprint $table) {
            $table->string('fiscal_type', 10)->nullable()->after('type');
        });

        DB::table('admin_documents')
            ->where('type', 'invoice')
            ->whereNull('fiscal_type')
            ->update(['fiscal_type' => 'TD01']);
    }

    public function down(): void
    {
        Schema::table('admin_documents', function (Blueprint $table) {
            $table->dropColumn('fiscal_type');
        });
    }
};
