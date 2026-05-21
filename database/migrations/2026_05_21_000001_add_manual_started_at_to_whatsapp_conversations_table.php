<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            if (! Schema::hasColumn('whatsapp_conversations', 'manual_started_at')) {
                $table->timestamp('manual_started_at')->nullable()->after('human_requested_at');
                $table->index('manual_started_at');
            }
        });

        DB::table('whatsapp_conversations')
            ->where('mode', 'manual')
            ->whereNull('manual_started_at')
            ->update(['manual_started_at' => DB::raw('COALESCE(human_requested_at, updated_at, created_at)')]);
    }

    public function down(): void
    {
        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            if (Schema::hasColumn('whatsapp_conversations', 'manual_started_at')) {
                $table->dropIndex(['manual_started_at']);
                $table->dropColumn('manual_started_at');
            }
        });
    }
};
