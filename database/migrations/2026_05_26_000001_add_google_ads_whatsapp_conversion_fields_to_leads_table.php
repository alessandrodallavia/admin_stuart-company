<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $afterColumn = Schema::hasColumn('leads', 'whatsapp_conversation_id')
                ? 'whatsapp_conversation_id'
                : 'payment_amount';

            $sentAt = $table->timestamp('google_ads_whatsapp_conversion_sent_at')->nullable();

            if ($afterColumn && Schema::hasColumn('leads', $afterColumn)) {
                $sentAt->after($afterColumn);
            }

            $table->string('google_ads_whatsapp_conversion_status')->nullable()->after('google_ads_whatsapp_conversion_sent_at');
            $table->text('google_ads_whatsapp_conversion_error')->nullable()->after('google_ads_whatsapp_conversion_status');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            foreach ([
                'google_ads_whatsapp_conversion_sent_at',
                'google_ads_whatsapp_conversion_status',
                'google_ads_whatsapp_conversion_error',
            ] as $column) {
                if (Schema::hasColumn('leads', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
