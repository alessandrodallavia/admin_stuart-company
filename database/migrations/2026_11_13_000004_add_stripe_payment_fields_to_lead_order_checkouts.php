<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_order_checkouts', function (Blueprint $table) {
            $table->string('stripe_payment_intent_id')->nullable()->unique()->after('payment_method');
            $table->string('stripe_payment_status', 40)->nullable()->after('stripe_payment_intent_id');
            $table->string('stripe_payment_method_type', 40)->nullable()->after('stripe_payment_status');
            $table->text('stripe_last_error')->nullable()->after('stripe_payment_method_type');
            $table->timestamp('paid_at')->nullable()->after('details_completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('lead_order_checkouts', fn (Blueprint $table) => $table->dropColumn([
            'stripe_payment_intent_id', 'stripe_payment_status', 'stripe_payment_method_type',
            'stripe_last_error', 'paid_at',
        ]));
    }
};
