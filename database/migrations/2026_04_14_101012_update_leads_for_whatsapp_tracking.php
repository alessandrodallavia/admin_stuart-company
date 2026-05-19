<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {

            // UUID pubblico per WhatsApp
            $table->uuid('uuid')->nullable()->unique()->after('id');

            // Stato lead
            $table->string('status')->default('pre')->after('uuid');

            // Rendi opzionali (fondamentale)
            $table->string('name')->nullable()->change();
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {

            $table->dropColumn(['uuid', 'status']);

            // torna come prima (se vuoi rollback)
            $table->string('name')->nullable(false)->change();
            $table->string('email')->nullable(false)->change();
        });
    }
};
