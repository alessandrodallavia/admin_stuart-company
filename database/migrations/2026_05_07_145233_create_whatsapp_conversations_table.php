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
        Schema::create('whatsapp_conversations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('admin_users')->nullOnDelete();

            $table->string('contact_phone');
            $table->string('business_phone')->nullable();

            $table->string('mode')->default('auto');
            $table->string('status')->default('open');
            $table->boolean('needs_human')->default(false);

            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('human_requested_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['contact_phone', 'status']);
            $table->index(['mode', 'status']);
            $table->index(['needs_human', 'human_requested_at']);
            $table->index('last_message_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_conversations');
    }
};
