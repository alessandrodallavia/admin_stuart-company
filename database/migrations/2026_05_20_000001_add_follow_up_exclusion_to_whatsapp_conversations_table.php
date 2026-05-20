<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            $table->timestamp('follow_up_excluded_until')->nullable()->after('human_requested_at');
            $table->boolean('follow_up_excluded_permanently')->default(false)->after('follow_up_excluded_until');
            $table->text('follow_up_exclusion_reason')->nullable()->after('follow_up_excluded_permanently');

            $table->index(['follow_up_excluded_permanently', 'follow_up_excluded_until'], 'wa_conv_follow_up_exclusion_index');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            $table->dropIndex('wa_conv_follow_up_exclusion_index');
            $table->dropColumn([
                'follow_up_excluded_until',
                'follow_up_excluded_permanently',
                'follow_up_exclusion_reason',
            ]);
        });
    }
};
