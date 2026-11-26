<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('ecommerce_orders', function (Blueprint $table) {
            $table->decimal('shipping_gross', 10, 2)->default(0);
            $table->string('stripe_payment_intent_id')->nullable()->unique();
            $table->string('stripe_payment_status')->nullable();
            $table->text('stripe_last_error')->nullable();
            $table->timestamp('paid_at')->nullable();
        });
    }
    public function down(): void {
        Schema::table('ecommerce_orders', function (Blueprint $table) { $table->dropUnique(['stripe_payment_intent_id']); $table->dropColumn(['shipping_gross', 'stripe_payment_intent_id', 'stripe_payment_status', 'stripe_last_error', 'paid_at']); });
    }
};
