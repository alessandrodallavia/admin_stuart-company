<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_user_id')->constrained('admin_users')->cascadeOnDelete();
            $table->string('email');
            $table->string('from_name')->nullable();
            $table->string('username');
            $table->text('password_encrypted')->nullable();
            $table->string('imap_host')->nullable();
            $table->unsignedInteger('imap_port')->default(993);
            $table->string('imap_encryption')->default('ssl');
            $table->string('smtp_host')->nullable();
            $table->unsignedInteger('smtp_port')->default(587);
            $table->string('smtp_encryption')->default('tls');
            $table->string('sync_folder')->default('INBOX');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_sync_error')->nullable();
            $table->timestamps();

            $table->unique(['admin_user_id', 'email']);
            $table->index(['admin_user_id', 'is_active']);
        });

        Schema::create('email_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->string('subject')->nullable();
            $table->string('contact_email');
            $table->string('contact_name')->nullable();
            $table->string('provider_thread_id')->nullable();
            $table->string('status')->default('open');
            $table->boolean('is_seen')->default(false);
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->index(['email_account_id', 'status']);
            $table->index(['lead_id', 'last_message_at']);
            $table->index(['assigned_user_id', 'is_seen']);
            $table->index('provider_thread_id');
        });

        Schema::create('email_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_conversation_id')->constrained()->cascadeOnDelete();
            $table->string('message_id')->nullable()->unique();
            $table->unsignedBigInteger('provider_uid')->nullable();
            $table->string('provider_folder')->nullable();
            $table->string('direction');
            $table->string('status')->nullable();
            $table->string('from_email')->nullable();
            $table->string('from_name')->nullable();
            $table->json('to')->nullable();
            $table->json('cc')->nullable();
            $table->json('bcc')->nullable();
            $table->string('subject')->nullable();
            $table->longText('body_text')->nullable();
            $table->longText('body_html')->nullable();
            $table->json('headers')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('seen_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['email_conversation_id', 'created_at']);
            $table->index(['direction', 'status']);
            $table->index('received_at');
            $table->unique(['email_conversation_id', 'provider_folder', 'provider_uid'], 'email_messages_provider_unique');
        });

        Schema::create('email_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_message_id')->constrained()->cascadeOnDelete();
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('filename');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('content_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_attachments');
        Schema::dropIfExists('email_messages');
        Schema::dropIfExists('email_conversations');
        Schema::dropIfExists('email_accounts');
    }
};
