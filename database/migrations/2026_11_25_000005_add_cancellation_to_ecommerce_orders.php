<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::table('ecommerce_orders', function(Blueprint $table) { $table->timestamp('cancelled_at')->nullable(); $table->unsignedBigInteger('cancelled_by')->nullable(); $table->text('cancellation_reason')->nullable(); }); }
 public function down(): void { Schema::table('ecommerce_orders', fn(Blueprint $table) => $table->dropColumn(['cancelled_at','cancelled_by','cancellation_reason'])); }
};
