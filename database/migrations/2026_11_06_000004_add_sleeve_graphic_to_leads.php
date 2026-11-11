<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('leads', 'live_mockup_sleeve_file')) {
            Schema::table('leads', fn (Blueprint $table) => $table->text('live_mockup_sleeve_file')->nullable());
        }
    }

    public function down(): void
    { /* Preserve uploaded graphics on shared admin/landing rollback. */
    }
};
