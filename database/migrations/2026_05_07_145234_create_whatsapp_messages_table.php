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
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('whatsapp_conversation_id')->constrained()->cascadeOnDelete();

            $table->string('provider_message_id')->nullable()->unique();
            $table->string('direction');
            $table->string('source')->default('automation');
            $table->string('type')->default('text');
            $table->string('status')->nullable();

            $table->string('from_phone')->nullable();
            $table->string('to_phone')->nullable();
            $table->text('body')->nullable();

            $table->string('media_id')->nullable();
            $table->string('media_disk')->nullable();
            $table->string('media_path')->nullable();
            $table->string('media_mime_type')->nullable();
            $table->string('media_filename')->nullable();
            $table->unsignedBigInteger('media_size')->nullable();

            $table->json('payload')->nullable();
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('admin_read_at')->nullable();
            $table->timestamps();

            $table->index(['whatsapp_conversation_id', 'created_at']);
            $table->index(['direction', 'status']);
            $table->index('received_at');
            $table->index(['direction', 'admin_read_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
