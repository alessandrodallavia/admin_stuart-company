<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_follow_ups', function (Blueprint $table) {
            $table->foreignId('trigger_message_id')
                ->nullable()
                ->after('sent_message_id')
                ->constrained('whatsapp_messages')
                ->nullOnDelete();
            $table->boolean('auto_generated')->default(false)->after('trigger_message_id');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_follow_ups', function (Blueprint $table) {
            $table->dropConstrainedForeignId('trigger_message_id');
            $table->dropColumn('auto_generated');
        });
    }
};
