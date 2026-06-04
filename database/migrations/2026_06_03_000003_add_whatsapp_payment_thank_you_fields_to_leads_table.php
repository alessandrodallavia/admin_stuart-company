<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            if (! Schema::hasColumn('leads', 'whatsapp_payment_thank_you_sent_at')) {
                $table->timestamp('whatsapp_payment_thank_you_sent_at')->nullable()->after('ga4_purchase_sent_error');
            }

            if (! Schema::hasColumn('leads', 'whatsapp_payment_thank_you_status')) {
                $table->string('whatsapp_payment_thank_you_status')->nullable()->after('whatsapp_payment_thank_you_sent_at');
            }

            if (! Schema::hasColumn('leads', 'whatsapp_payment_thank_you_error')) {
                $table->text('whatsapp_payment_thank_you_error')->nullable()->after('whatsapp_payment_thank_you_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            foreach ([
                'whatsapp_payment_thank_you_error',
                'whatsapp_payment_thank_you_status',
                'whatsapp_payment_thank_you_sent_at',
            ] as $column) {
                if (Schema::hasColumn('leads', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
