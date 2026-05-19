<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->moveAssignedUserForeignKeyToAdminUsers();

        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            if (! Schema::hasColumn('whatsapp_conversations', 'needs_human')) {
                $table->boolean('needs_human')->default(false)->after('status');
            }

            if (! Schema::hasColumn('whatsapp_conversations', 'human_requested_at')) {
                $table->timestamp('human_requested_at')->nullable()->after('last_message_at');
            }
        });
    }

    private function moveAssignedUserForeignKeyToAdminUsers(): void
    {
        if (! Schema::hasColumn('whatsapp_conversations', 'assigned_user_id')) {
            return;
        }

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $foreignKey = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'whatsapp_conversations')
            ->where('COLUMN_NAME', 'assigned_user_id')
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->value('CONSTRAINT_NAME');

        if ($foreignKey) {
            DB::statement("ALTER TABLE `whatsapp_conversations` DROP FOREIGN KEY `{$foreignKey}`");
        }

        DB::statement(
            'UPDATE `whatsapp_conversations` LEFT JOIN `admin_users` ON `whatsapp_conversations`.`assigned_user_id` = `admin_users`.`id` SET `whatsapp_conversations`.`assigned_user_id` = NULL WHERE `whatsapp_conversations`.`assigned_user_id` IS NOT NULL AND `admin_users`.`id` IS NULL'
        );

        DB::statement(
            'ALTER TABLE `whatsapp_conversations` ADD CONSTRAINT `whatsapp_conversations_assigned_user_id_admin_foreign` FOREIGN KEY (`assigned_user_id`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            if (Schema::hasColumn('whatsapp_conversations', 'human_requested_at')) {
                $table->dropColumn('human_requested_at');
            }

            if (Schema::hasColumn('whatsapp_conversations', 'needs_human')) {
                $table->dropColumn('needs_human');
            }
        });
    }
};
