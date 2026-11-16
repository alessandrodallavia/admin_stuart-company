<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('lead_order_checkouts', function (Blueprint $table) {
            $table->string('fulfillment_method', 30)->nullable()->after('order_status');
            $table->string('fulfillment_status', 30)->default('pending')->after('fulfillment_method');
            $table->string('fulfillment_carrier')->nullable()->after('fulfillment_status');
            $table->string('fulfillment_tracking')->nullable()->after('fulfillment_carrier');
            $table->text('fulfillment_notes')->nullable()->after('fulfillment_tracking');
            $table->timestamp('fulfilled_at')->nullable()->after('fulfillment_notes');
        });
    }

    public function down(): void
    {
        Schema::table('lead_order_checkouts', fn (Blueprint $table) => $table->dropColumn([
            'fulfillment_method', 'fulfillment_status', 'fulfillment_carrier',
            'fulfillment_tracking', 'fulfillment_notes', 'fulfilled_at',
        ]));
    }
};
