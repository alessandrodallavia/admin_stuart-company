<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach (['ecommerce_orders', 'lead_order_checkouts'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->unsignedBigInteger('offline_document_id')->nullable()->index());
        }
    }

    public function down(): void
    {
        foreach (['ecommerce_orders', 'lead_order_checkouts'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn('offline_document_id'));
        }
    }
};
