<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_users', function (Blueprint $table) {
            $table->boolean('training_mode_enabled')->default(false)->after('is_active');
            $table->boolean('training_mode_active')->default(false)->after('training_mode_enabled');
        });

        foreach (['leads', 'whatsapp_conversations', 'email_conversations'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->boolean('is_training')->default(false)->index();
                $table->foreignId('training_owner_id')->nullable()->constrained('admin_users')->cascadeOnDelete();
                $table->string('training_scenario')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['email_conversations', 'whatsapp_conversations', 'leads'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('training_owner_id');
                $table->dropColumn(['is_training', 'training_scenario']);
            });
        }

        Schema::table('admin_users', function (Blueprint $table) {
            $table->dropColumn(['training_mode_enabled', 'training_mode_active']);
        });
    }
};
