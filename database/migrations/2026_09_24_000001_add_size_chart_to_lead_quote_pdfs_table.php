<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_quote_pdfs', function (Blueprint $table) {
            $table->string('project_size_chart_path')->nullable()->after('project_mockup_back_path');
        });
    }

    public function down(): void
    {
        Schema::table('lead_quote_pdfs', function (Blueprint $table) {
            $table->dropColumn('project_size_chart_path');
        });
    }
};
