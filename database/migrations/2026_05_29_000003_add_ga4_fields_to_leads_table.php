<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            if (! Schema::hasColumn('leads', 'ga_client_id')) {
                $table->string('ga_client_id')->nullable()->after('fbclid');
            }

            if (! Schema::hasColumn('leads', 'ga_session_id')) {
                $table->string('ga_session_id')->nullable()->after('ga_client_id');
            }

            if (! Schema::hasColumn('leads', 'ga4_quote_sent_at')) {
                $table->timestamp('ga4_quote_sent_at')->nullable();
            }

            if (! Schema::hasColumn('leads', 'ga4_quote_sent_status')) {
                $table->string('ga4_quote_sent_status')->nullable()->after('ga4_quote_sent_at');
            }

            if (! Schema::hasColumn('leads', 'ga4_quote_sent_error')) {
                $table->text('ga4_quote_sent_error')->nullable()->after('ga4_quote_sent_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            foreach ([
                'ga4_quote_sent_error',
                'ga4_quote_sent_status',
                'ga4_quote_sent_at',
                'ga_session_id',
                'ga_client_id',
            ] as $column) {
                if (Schema::hasColumn('leads', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
