<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->text('payment_link')->nullable()->after('pipeline_lead_id');
            $table->decimal('quote_amount', 10, 2)->nullable()->after('payment_link');
            $table->decimal('payment_amount', 10, 2)->nullable()->after('quote_amount');
            $table->foreignId('whatsapp_conversation_id')
                ->nullable()
                ->after('payment_amount')
                ->constrained('whatsapp_conversations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('whatsapp_conversation_id');
            $table->dropColumn(['payment_link', 'quote_amount', 'payment_amount']);
        });
    }
};
