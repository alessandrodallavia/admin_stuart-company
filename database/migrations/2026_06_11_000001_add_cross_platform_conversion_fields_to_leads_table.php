<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            foreach (['quote_sent', 'payment_link_sent', 'purchase'] as $event) {
                $table->timestamp("google_ads_{$event}_at")->nullable();
                $table->string("google_ads_{$event}_status")->nullable();
                $table->text("google_ads_{$event}_error")->nullable();
            }

            $table->timestamp('meta_lead_sent_at')->nullable();
            $table->string('meta_lead_status')->nullable();
            $table->text('meta_lead_error')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn([
                'google_ads_quote_sent_at',
                'google_ads_quote_sent_status',
                'google_ads_quote_sent_error',
                'google_ads_payment_link_sent_at',
                'google_ads_payment_link_sent_status',
                'google_ads_payment_link_sent_error',
                'google_ads_purchase_at',
                'google_ads_purchase_status',
                'google_ads_purchase_error',
                'meta_lead_sent_at',
                'meta_lead_status',
                'meta_lead_error',
            ]);
        });
    }
};
