<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            if (! Schema::hasColumn('leads', 'ga4_purchase_sent_at')) {
                $table->timestamp('ga4_purchase_sent_at')->nullable()->after('ga4_payment_link_sent_error');
            }

            if (! Schema::hasColumn('leads', 'ga4_purchase_sent_status')) {
                $table->string('ga4_purchase_sent_status')->nullable()->after('ga4_purchase_sent_at');
            }

            if (! Schema::hasColumn('leads', 'ga4_purchase_sent_error')) {
                $table->text('ga4_purchase_sent_error')->nullable()->after('ga4_purchase_sent_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            foreach ([
                'ga4_purchase_sent_error',
                'ga4_purchase_sent_status',
                'ga4_purchase_sent_at',
            ] as $column) {
                if (Schema::hasColumn('leads', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
