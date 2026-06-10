<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('leads', 'whatsapp_conversation_id')) {
            return;
        }

        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('whatsapp_conversation_id')
                ->nullable()
                ->after('payment_amount')
                ->constrained('whatsapp_conversations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('leads', 'whatsapp_conversation_id')) {
            return;
        }

        Schema::table('leads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('whatsapp_conversation_id');
        });
    }
};
