<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_admin_user_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->foreignId('sent_message_id')->nullable()->constrained('whatsapp_messages')->nullOnDelete();

            $table->timestamp('due_at');
            $table->text('body');
            $table->string('status')->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['status', 'due_at']);
            $table->index(['whatsapp_conversation_id', 'status', 'due_at'], 'wa_follow_ups_conversation_status_due_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_follow_ups');
    }
};
