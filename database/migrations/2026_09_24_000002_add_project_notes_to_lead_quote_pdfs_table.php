<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_quote_pdfs', function (Blueprint $table) {
            $table->text('project_notes')->nullable()->after('project_size_chart_path');
        });
    }

    public function down(): void
    {
        Schema::table('lead_quote_pdfs', function (Blueprint $table) {
            $table->dropColumn('project_notes');
        });
    }
};
