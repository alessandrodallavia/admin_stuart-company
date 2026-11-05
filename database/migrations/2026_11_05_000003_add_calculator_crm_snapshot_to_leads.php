<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('leads', 'calculator_crm_snapshot')) {
            Schema::table('leads', fn (Blueprint $table) => $table->json('calculator_crm_snapshot')->nullable());
        }
    }

    public function down(): void
    {
        // Shared by admin and landing: preserve historical quotes on rollback.
    }
};
