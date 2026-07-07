<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_quote_pdfs', function (Blueprint $table) {
            $table->string('disk')->nullable()->default(null)->change();
            $table->string('path')->nullable()->change();
            $table->string('filename')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('lead_quote_pdfs', function (Blueprint $table) {
            $table->string('disk')->nullable(false)->default('local')->change();
            $table->string('path')->nullable(false)->change();
            $table->string('filename')->nullable(false)->change();
        });
    }
};
