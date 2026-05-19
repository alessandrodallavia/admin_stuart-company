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
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            if (! Schema::hasColumn('whatsapp_messages', 'admin_read_at')) {
                $table->timestamp('admin_read_at')->nullable()->after('received_at');
                $table->index(['direction', 'admin_read_at']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            if (Schema::hasColumn('whatsapp_messages', 'admin_read_at')) {
                $table->dropIndex(['direction', 'admin_read_at']);
                $table->dropColumn('admin_read_at');
            }
        });
    }
};
