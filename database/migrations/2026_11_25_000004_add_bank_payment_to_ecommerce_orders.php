<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::table('ecommerce_orders', function(Blueprint $table) { $table->string('payment_method')->default('online'); $table->unsignedBigInteger('admin_document_id')->nullable()->index(); $table->timestamp('proforma_sent_at')->nullable(); }); }
 public function down(): void { Schema::table('ecommerce_orders', fn(Blueprint $table) => $table->dropColumn(['payment_method','admin_document_id','proforma_sent_at'])); }
};
